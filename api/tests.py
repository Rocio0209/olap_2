from dotenv import load_dotenv
load_dotenv()

import os
from fastapi import Query
import re
from fastapi import FastAPI, Depends, Query, Body
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import unicodedata
from collections import defaultdict

import pythoncom
import win32com.client
from typing import Callable, Any

from middlewares.auth import verify_token
from config import get_connection_string


app = FastAPI()

# =========================
# CORS (Front -> FastAPI)
# =========================
raw = os.getenv("CORS_ORIGINS", "")  
origins = [o.strip() for o in raw.split(",") if o.strip()]

if origins:
    app.add_middleware(
        CORSMiddleware,
        allow_origins=origins,
        allow_credentials=False,  # Bearer token -> no cookies
        allow_methods=["GET", "POST", "OPTIONS"],  
        allow_headers=["Authorization", "Content-Type", "Accept"],
    )

# =========================
# OLAP helpers (EN ESTE ARCHIVO)
# =========================
def recordset_to_rows(rs):
    cols = [rs.Fields(i).Name for i in range(rs.Fields.Count)]
    out = []

    while not rs.EOF:
        row = {}
        for i, c in enumerate(cols):
            key = c.strip().upper()        # 🔴 NORMALIZA EL NOMBRE
            v = rs.Fields(i).Value
            row[key] = None if v is None else str(v)
        out.append(row)
        rs.MoveNext()

    return out


def openschema_rows(conn, schema_id: int, restrictions=None):
    rs = None
    try:
        if restrictions is None:
            rs = conn.OpenSchema(schema_id)
        else:
            rs = conn.OpenSchema(schema_id, restrictions)

        return recordset_to_rows(rs)

    finally:
        try:
            if rs:
                rs.Close()
        except Exception:
            pass


def crear_conexion(connection_string: str):
    """Crea conexión ADODB a SSAS / OLAP"""
    conn = win32com.client.Dispatch("ADODB.Connection")
    conn.Open(connection_string)
    return conn

def _sql_str(s: str) -> str:
    # Escapa comillas simples para SQL en rowsets $system.*
    return (s or "").replace("'", "''")


def ejecutar_query_lista(conn, query: str, field: str):
    """Ejecuta query y regresa lista de valores de una columna"""
    rs = win32com.client.Dispatch("ADODB.Recordset")
    try:
        rs.Open(query, conn)
        resultados = []
        while not rs.EOF:
            resultados.append(str(rs.Fields(field).Value))
            rs.MoveNext()
        return resultados
    finally:
        try:
            rs.Close()
        except Exception:
            pass

def ejecutar_query_rows_execute(conn, query: str):
    """
    Ejecuta rowsets $system.* usando conn.Execute (evita que el provider lo parsee como MDX).
    Regresa lista de dicts (keys normalizados a UPPER).
    """
    rs = None
    try:
        rs, _ = conn.Execute(query)

        cols = [rs.Fields(i).Name for i in range(rs.Fields.Count)]
        out = []
        while not rs.EOF:
            row = {}
            for i, c in enumerate(cols):
                key = (c or "").strip().upper()
                v = rs.Fields(i).Value
                row[key] = None if v is None else str(v)
            out.append(row)
            rs.MoveNext()
        return out
    finally:
        try:
            if rs:
                rs.Close()
        except Exception:
            pass


def ejecutar_conexion_olap(fn: Callable[[Any], Any], catalogo: str | None = None):
    conn = None
    try:
        pythoncom.CoInitialize()
        conn = crear_conexion(get_connection_string(catalogo))
        return fn(conn)
    finally:
        if conn:
            try:
                conn.Close()
            except Exception:
                pass
        pythoncom.CoUninitialize()


def _to_int(v):
    try:
        return int(float(v))
    except Exception:
        return None

def _resolve_member_unique_name(
    conn,
    catalogo: str,
    cubo: str,
    jerarquia_unique_name: str,
    member_caption: str,
):
    """
    Busca el MEMBER_UNIQUE_NAME a partir del caption en una jerarquía específica.
    Esto evita adivinar el formato .&[...]
    """
    cat = _sql_str(catalogo)
    cube = _sql_str(cubo)
    hier = _sql_str(jerarquia_unique_name)
    cap = _sql_str(member_caption)

    q = f"""
    SELECT
      [MEMBER_UNIQUE_NAME],
      [MEMBER_CAPTION]
    FROM $system.MDSCHEMA_MEMBERS
    WHERE [CATALOG_NAME] = '{cat}'
      AND [CUBE_NAME] = '{cube}'
      AND [HIERARCHY_UNIQUE_NAME] = '{hier}'
      AND [MEMBER_CAPTION] = '{cap}'
    """

    rows = ejecutar_query_rows_execute(conn, q)

    # Si hay varios, toma el primero exacto
    for r in rows:
        mun = (r.get("MEMBER_UNIQUE_NAME") or "").strip()
        if mun:
            return mun

    return None

def ejecutar_mdx_recordset(conn, mdx: str):
    """
    Ejecuta MDX y lo lee como Recordset (OLE DB for OLAP suele devolver rowset tabular).
    """
    rs = win32com.client.Dispatch("ADODB.Recordset")
    try:
        rs.Open(mdx, conn)
        return recordset_to_rows(rs)
    finally:
        try:
            rs.Close()
        except Exception:
            pass

def _norm(s: str) -> str:
    """
    Normaliza para comparar texto ignorando acentos, mayúsculas y espacios extra.
    Ej: "APLICACIÓN  BIOLÓGICOS" -> "APLICACION BIOLOGICOS"
    """
    s = (s or "").strip().upper()
    s = "".join(
        c for c in unicodedata.normalize("NFKD", s)
        if not unicodedata.combining(c)
    )
    s = " ".join(s.split())
    return s

def _resolve_member_unique_name_fuzzy(
    conn,
    catalogo: str,
    cubo: str,
    jerarquia_unique_name: str,
    search_text: str,
    max_scan: int = 50000,   # cuidado: depende del tamaño de la jerarquía
):
    """
    Busca MEMBER_UNIQUE_NAME por match aproximado del caption (contains),
    normalizando acentos/espacios. Escanea miembros del rowset y filtra en Python.
    """
    cat = _sql_str(catalogo)
    cube = _sql_str(cubo)
    hier = _sql_str(jerarquia_unique_name)

    # Traemos captions + unique names de esa jerarquía dentro del cubo
    # (sin LIKE; filtramos en Python)
    q = f"""
    SELECT
      [MEMBER_UNIQUE_NAME],
      [MEMBER_CAPTION]
    FROM $system.MDSCHEMA_MEMBERS
    WHERE [CATALOG_NAME] = '{cat}'
      AND [CUBE_NAME] = '{cube}'
      AND [HIERARCHY_UNIQUE_NAME] = '{hier}'
    """

    rows = ejecutar_query_rows_execute(conn, q)

    needle = _norm(search_text)
    candidatos = []

    for r in rows[:max_scan]:
        cap = r.get("MEMBER_CAPTION") or ""
        mun = r.get("MEMBER_UNIQUE_NAME") or ""
        if not mun:
            continue

        if needle in _norm(cap):
            candidatos.append({"member_unique_name": mun, "member_caption": cap})

    # Si hay 1 match, listo
    if len(candidatos) == 1:
        return candidatos[0]["member_unique_name"], candidatos

    # Si hay varios o ninguno, lo regresamos para diagnóstico/selección
    return None, candidatos

def _find_hierarchy_by_caption(rows, caption_norm: str):
    # rows vienen de MDSCHEMA_HIERARCHIES (keys en UPPER)
    for r in rows:
        cap = r.get("HIERARCHY_CAPTION") or r.get("HIERARCHY_NAME") or ""
        if _norm(cap) == caption_norm:
            return (r.get("HIERARCHY_UNIQUE_NAME") or "").strip()
    return None

def _find_measure_unique_name(conn, catalogo: str, cubo: str, contains_text: str = "TOTAL"):
    # opcional: si quieres auto-detectar la measure "Total"
    cat = _sql_str(catalogo); cube = _sql_str(cubo)
    q = f"""
    SELECT *
    FROM $system.MDSCHEMA_MEASURES
    WHERE [CATALOG_NAME] = '{cat}'
      AND [CUBE_NAME] = '{cube}'
    """
    rows = ejecutar_query_rows_execute(conn, q)
    needle = _norm(contains_text)
    for r in rows:
        cap = r.get("MEASURE_CAPTION") or r.get("MEASURE_NAME") or ""
        if needle in _norm(cap):
            mun = (r.get("MEASURE_UNIQUE_NAME") or "").strip()
            if mun:
                return mun
    return None


def sql_lit(s: str) -> str:
    """Escapa comillas simples para DMVs ($system.*)."""
    return (s or "").replace("'", "''").strip()

def mdx_str(s: str) -> str:
    """Escapa comillas dobles para meter texto en ' "..." ' dentro de MDX."""
    return (s or "").replace('"', '""').strip()

def mdx_key(s: str) -> str:
    """Para armar .&[KEY]."""
    return (s or "").strip()

def norm(s: str) -> str:
    """Normaliza (mayúsculas, sin acentos, espacios simples)."""
    s = (s or "").strip().upper()
    s = "".join(
        c for c in unicodedata.normalize("NFKD", s)
        if not unicodedata.combining(c)
    )
    return " ".join(s.split())


# -----------------------------
# MDX - leer miembros del Axis(0)
# -----------------------------
def mdx_members_axis0(conn: Any, mdx: str) -> list[dict]:
    """
    Ejecuta MDX que devuelve un SET en el eje 0 y regresa miembros con metadata.
    """
    cs = conn.Execute(mdx)
    axis = cs.Axes(0)

    out = []
    for pos in axis.Positions:
        mem = pos.Members(0)
        lvl = getattr(mem, "Level", None)
        out.append({
            "member_caption": getattr(mem, "Caption", None),
            "member_name": getattr(mem, "Name", None),
            "member_unique_name": getattr(mem, "UniqueName", None),
            "level_number": getattr(lvl, "LevelNumber", None),
            "level_unique_name": getattr(lvl, "UniqueName", None),
        })
    return out


# -----------------------------
# DMVs - resolver captions por unique name (con cache)
# -----------------------------
def dmv_member_info(conn, catalogo: str, cubo: str, member_unique_name: str, ejecutar_query_rows_execute) -> dict | None:
    cat = sql_lit(catalogo)
    cube = sql_lit(cubo)
    mun = sql_lit(member_unique_name)

    q = f"""
    SELECT TOP 1
      [MEMBER_UNIQUE_NAME],
      [MEMBER_CAPTION],
      [MEMBER_NAME],
      [LEVEL_NUMBER],
      [LEVEL_UNIQUE_NAME]
    FROM $system.MDSCHEMA_MEMBERS
    WHERE [CATALOG_NAME] = '{cat}'
      AND [CUBE_NAME] = '{cube}'
      AND [MEMBER_UNIQUE_NAME] = '{mun}'
    """
    rows = ejecutar_query_rows_execute(conn, q) or []
    return rows[0] if rows else None


def dmv_member_info_many(conn, catalogo: str, cubo: str, unique_names: list[str], ejecutar_query_rows_execute) -> dict[str, dict | None]:
    """
    Resuelve unique_names -> info (cache en memoria por llamada).
    """
    cache: dict[str, dict | None] = {}
    out: dict[str, dict | None] = {}
    for un in unique_names:
        un = (un or "").strip()
        if not un:
            continue
        if un not in cache:
            cache[un] = dmv_member_info(conn, catalogo, cubo, un, ejecutar_query_rows_execute)
        out[un] = cache[un]
    return out


# -----------------------------
# Utilidades para tus rows MDX (dicts)
# -----------------------------
def mdx_rows_extract_unique_names(rows: list[dict]) -> list[str]:
    """
    Tus rows suelen venir como dict { "<unique_name>": "valor", ... }.
    Extrae solo keys únicas.
    """
    uniq, seen = [], set()
    for r in rows or []:
        if not isinstance(r, dict):
            continue
        for k in r.keys():
            k = (k or "").strip()
            if k and k not in seen:
                seen.add(k)
                uniq.append(k)
    return uniq


def build_ruta_detalle(unique_names: list[str], captions_map: dict[str, dict | None]) -> list[dict]:
    detalle = []
    for un in unique_names:
        info = captions_map.get(un) or {}
        detalle.append({
            "member_unique_name": un,
            "member_caption": info.get("MEMBER_CAPTION") or info.get("MEMBER_NAME") or un,
            "level_number": info.get("LEVEL_NUMBER"),
            "level_unique_name": info.get("LEVEL_UNIQUE_NAME"),
        })
    return detalle


def pick_caption_by_level(ruta_detalle: list[dict], level_unique_name: str) -> str | None:
    level_unique_name = (level_unique_name or "").strip()
    for r in ruta_detalle or []:
        if (r.get("level_unique_name") or "").strip() == level_unique_name:
            return r.get("member_caption")
    return None


def format_unidad_medica(ruta_detalle: list[dict], nombre_unidad: str | None, clue_input: str) -> dict:
    return {
        "clues": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[CLUES]") or clue_input,
        "nombre_unidad": nombre_unidad,
        "entidad": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Entidad]"),
        "jurisdiccion": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Jurisdicción]"),
        "municipio": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Municipio]"),
    }



def _norm_txt(s: str) -> str:
    s = (s or "").strip().upper()
    s = "".join(
        c for c in unicodedata.normalize("NFKD", s)
        if not unicodedata.combining(c)
    )
    return " ".join(s.split())

def es_migrante(nombre_variable: str) -> bool:
    return "MIGRANTE" in _norm_txt(nombre_variable)


def extraer_edad_inicial(nombre_variable: str) -> int:
    nombre = nombre_variable.upper()

    if "RECIÉN NACIDO" in nombre or "24 HORAS" in nombre:
        return 0

    match = re.search(r"(\d+)\s*A\s*(\d+)\s*D[IÍ]AS?", nombre)
    if match:
        return int(match.group(1))

    match = re.search(r"(\d+)\s*A\s*(\d+)\s*MESES?", nombre)
    if match:
        return int(match.group(1)) * 30

    match = re.search(r"(\d+)\s*A\s*(\d+)\s*A[NÑ]OS?", nombre)
    if match:
        return int(match.group(1)) * 365

    match = re.search(r"(\d+)\s*(D[IÍ]AS?|MESES?|A[NÑ]OS?)", nombre)
    if match:
        valor = int(match.group(1))
        unidad = match.group(2)
        if "DÍA" in unidad or "DIA" in unidad:
            return valor
        elif "MES" in unidad:
            return valor * 30
        elif "AÑO" in unidad:
            return valor * 365

    match = re.search(r"(\d+)\s*Y\s*M[AÁ]S\s*A[NÑ]OS", nombre)
    if match:
        return int(match.group(1)) * 365

    return 9999


def normalizar_apartado(nombre):
    nombre = nombre.upper()
    nombre = nombre.replace("Ó", "O").replace("Í", "I").replace("É", "E").replace("Á", "A").replace("Ú", "U")
    nombre = nombre.replace("  ", " ")
    nombre = nombre.strip()
    return nombre
GRUPOS_APARTADOS = {
    normalizar_apartado("127 APLICACIÓN DE BIOLÓGICOS SRP TRIPLE VIRAL"): [
        "PARA INICIAR O COMPLETAR ESQUEMA"
    ],
    normalizar_apartado("129 APLICACIÓN DE BIOLÓGICOS VPH"): [
        "NIÑAS Y/O ADOLESCENTES VÍCTIMAS DE VIOLACIÓN SEXUAL",
        "MUJERES CIS Y TRANS DE 11 A 49 AÑOS QUE VIVEN CON VIH",
        "HOMBRES CIS Y TRANS DE 11 A 49 AÑOS QUE VIVEN CON VIH"
    ],
    normalizar_apartado("344 APLICACIÓN DE BIOLÓGICOS COVID-19"): [
        "5 A 11 AÑOS",
        "FACTORES DE RIESGO",
        "60 AÑOS Y MÁS",
        "EMBARAZADAS",
        "PERSONAL DE SALUD",
        "OTROS GRUPOS DE BAJA PRIORIDAD"
    ],
    normalizar_apartado("274 APLICACIÓN DE BIOLÓGICOS ROTAVIRUS RV1"): [
        "PARA INICIAR O COMPLETAR ESQUEMA"
    ],
    normalizar_apartado("275 APLICACIÓN DE BIOLÓGICOS HEXAVALENTE"): [
        "INICIAR O COMPLETAR ESQUEMA"
    ],
    normalizar_apartado("132 APLICACIÓN DE BIOLÓGICOS Td"): [
        "PRIMERA EMBARAZADAS",
        "SEGUNDA EMBARAZADAS",
        "TERCERA EMBARAZADAS",
        "REFUERZO EMBARAZADAS",
        "PRIMERA MUJERES NO EMBARAZADAS",
        "PRIMERA HOMBRES",
        "SEGUNDA MUJERES NO EMBARAZADAS",
        "SEGUNDA HOMBRES",
        "TERCERA MUJERES NO EMBARAZADAS",
        "TERCERA HOMBRES",
        "REFUERZO MUJERES",
        "REFUERZO HOMBRES"
    ],
}

# ================================
# FUNCIÓN PARA OBTENER GRUPOS
# ================================
def obtener_grupos_para_apartado(apartado_nombre):
    """
    apartado_nombre → string EXACTO del JSON
    Ej: "127 APLICACIÓN DE BIOLÓGICOS SRP TRIPLE VIRAL"
    """

    # Obtenemos los grupos definidos para este apartado
    grupos_definidos = GRUPOS_APARTADOS.get(apartado_nombre, [])

    # Insertar "sin grupo" al inicio
    grupos_finales = ["sin grupo"] + grupos_definidos + ["migrante"]

    return grupos_finales

# ================================
# FUNCIÓN PARA ASIGNAR GRUPO A UNA VARIABLE
# ================================
def asignar_grupo(nombre_variable: str, grupos_apartado: list) -> str:
    """
    Asigna el grupo correcto según:
      - Si contiene la palabra MIGRANTE → 'migrante'
      - Si contiene parcialmente el texto de un grupo → ese grupo
      - Si no coincide con ningún grupo → 'sin grupo'
    """

    nombre = nombre_variable.upper()

    # 1. Detectar migrantes
    if "MIGRANTE" in nombre:
        return "migrante"

    # 2. Buscar coincidencia parcial con grupos
    for grupo in grupos_apartado:
        g = grupo.upper()
        if g in nombre:
            return grupo   # devuelve el grupo original

    # 3. Sin coincidencias
    return "sin grupo"

def agrupar_por_grupo(variables, grupos_apartado=None):
    """
    Agrupa las variables por grupo respetando el ORDEN EXACTO definido en GRUPOS_APARTADOS.
    """
    grupos = defaultdict(list)

    # Construir contenedor
    for var in variables:
        grupos[var["grupo"]].append(var)

    grupos_finales = []

    # 1. SIN GRUPO primero (si existe)
    if "sin grupo" in grupos:
        grupos_finales.append({
            "grupo": "sin grupo",
            "variables": grupos["sin grupo"]
        })

    # 2. Grupos definidos en el diccionario, EN EL ORDEN EXACTO
    if grupos_apartado:
        for g in grupos_apartado:
            if g not in ("sin grupo", "migrante") and g in grupos:
                grupos_finales.append({
                    "grupo": g,
                    "variables": grupos[g]
                })

    # 3. MIGRANTE al final (si existe)
    if "migrante" in grupos:
        grupos_finales.append({
            "grupo": "migrante",
            "variables": grupos["migrante"]
        })

    return grupos_finales




# =========================
# Endpoint de prueba
# =========================
@app.get("/cubos_disponibles", dependencies=[Depends(verify_token)])
def cubos_disponibles():
    try:
        def consulta(conn):
            return ejecutar_query_lista(
                conn,
                "SELECT [CATALOG_NAME] FROM $system.DBSCHEMA_CATALOGS",
                "CATALOG_NAME",
            )

        cubos = ejecutar_conexion_olap(consulta)
        return {"cubos": sorted(set(cubos))}

    except Exception:
        return JSONResponse(status_code=500, content={"error": "Error interno"})


@app.get("/cubos_sis", dependencies=[Depends(verify_token)])
def cubos_sis():
    try:
        def consulta(conn):
            cubos = ejecutar_query_lista(
                conn,
                "SELECT [CATALOG_NAME] FROM $system.DBSCHEMA_CATALOGS",
                "CATALOG_NAME",
            )

            sis_regex = re.compile(r"^SIS_(\d{4})")
            sinba_regex = re.compile(r"^Cubo solo sinba (\d{4})")

            permitidos = []
            for c in set(cubos):
                name = (c or "").strip()

                m = sis_regex.match(name)
                if m and int(m.group(1)) >= 2020:
                    permitidos.append(name)
                    continue

                m = sinba_regex.match(name)
                if m and int(m.group(1)) >= 2020:
                    permitidos.append(name)

            return permitidos

        return {"cubos": ejecutar_conexion_olap(consulta)}

    except Exception:
        return JSONResponse(status_code=500, content={"error": "Error interno"})


@app.get("/cubos_sis_estandarizados", dependencies=[Depends(verify_token)])
def cubos_sis_estandarizados():
    try:
        def consulta(conn):
            cubos = ejecutar_query_lista(
                conn,
                "SELECT [CATALOG_NAME] FROM $system.DBSCHEMA_CATALOGS",
                "CATALOG_NAME",
            )

            sis_regex = re.compile(r"^SIS_(\d{4})")
            sinba_regex = re.compile(r"^Cubo solo sinba (\d{4})")

            estandarizados = set()

            for c in set(cubos):
                name = (c or "").strip()

                m = sis_regex.match(name)
                if m and int(m.group(1)) >= 2020:
                    estandarizados.add(f"SIS {m.group(1)}")
                    continue

                m = sinba_regex.match(name)
                if m and int(m.group(1)) >= 2020:
                    estandarizados.add(f"SIS {m.group(1)}")

            return sorted(estandarizados)

        return {"cubos": ejecutar_conexion_olap(consulta)}

    except Exception:
        return JSONResponse(status_code=500, content={"error": "Error interno"})

@app.get("/catalogos_tablas", dependencies=[Depends(verify_token)])
def catalogos_tablas():
    try:
        def consulta(conn):
            # 20 = schema TABLES (lo que ya te está devolviendo TABLE_CATALOG)
            rows = openschema_rows(conn, 20)

            catalogos = sorted({
                (r.get("TABLE_CATALOG") or "").strip()
                for r in rows
                if r.get("TABLE_CATALOG")
            })

            return {"total": len(catalogos), "catalogos": catalogos}

        return ejecutar_conexion_olap(consulta)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.get("/tablas_en_catalogo", dependencies=[Depends(verify_token)])
def tablas_en_catalogo(table_catalog: str):
    try:
        table_catalog = (table_catalog or "").strip()

        def consulta(conn):
            # Restricciones típicas para TABLES: [TABLE_CATALOG, TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE]
            rows = openschema_rows(conn, 20, [table_catalog, None, None, None])

            # Solo regreso campos útiles
            out = [{
                "table_catalog": r.get("TABLE_CATALOG"),
                "table_schema": r.get("TABLE_SCHEMA"),
                "table_name": r.get("TABLE_NAME"),
                "table_type": r.get("TABLE_TYPE"),
                "table_olap_type": r.get("TABLE_OLAP_TYPE"),
            } for r in rows]

            return {"table_catalog": table_catalog, "total": len(out), "tablas": out[:500]}

        return ejecutar_conexion_olap(consulta)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})

@app.get("/cubos_en_catalogo", dependencies=[Depends(verify_token)])
def cubos_en_catalogo(
    catalogo: str,
    incluir_dimensiones: bool = Query(False, description="Incluye cubos $DIM_* (dimension cubes)")
):
    try:
        catalogo = (catalogo or "").strip()

        def consulta(conn):
            cat = _sql_str(catalogo)

            q = f"""
            SELECT
              [CATALOG_NAME],
              [CUBE_NAME],
              [CUBE_CAPTION],
              [CUBE_TYPE],
              [CUBE_SOURCE],
              [DESCRIPTION]
            FROM $system.MDSCHEMA_CUBES
            WHERE [CATALOG_NAME] = '{cat}'
            """

            rows = ejecutar_query_rows_execute(conn, q)

            out = []
            for r in rows:
                cube_name = (r.get("CUBE_NAME") or "").strip()
                if not cube_name:
                    continue

                cube_source = (r.get("CUBE_SOURCE") or "").strip()

                # Si NO quieres dimension cubes, solo deja source=1
                if not incluir_dimensiones and cube_source != "1":
                    continue

                out.append({
                    "catalogo": r.get("CATALOG_NAME"),
                    "cubo": cube_name,
                    "caption": r.get("CUBE_CAPTION"),
                    "cube_type": r.get("CUBE_TYPE"),
                    "cube_source": cube_source,  # "1" real, "2" dimensión
                    "description": r.get("DESCRIPTION"),
                    "es_dimension_cube": cube_source == "2" or cube_name.startswith("$"),
                })

            out.sort(key=lambda x: (x["cube_source"], x["cubo"] or ""))
            return {"catalogo": catalogo, "total": len(out), "cubos": out}

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})

@app.get("/dimensiones_disponibles", dependencies=[Depends(verify_token)])
def dimensiones_disponibles(catalogo: str, cubo: str):
    try:
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()

        def consulta(conn):
            cat = _sql_str(catalogo)
            cube = _sql_str(cubo)

            q = f"""
            SELECT
              [CATALOG_NAME],
              [CUBE_NAME],
              [DIMENSION_UNIQUE_NAME],
              [DIMENSION_NAME],
              [DIMENSION_CAPTION],
              [DIMENSION_TYPE],
              [DESCRIPTION]
            FROM $system.MDSCHEMA_DIMENSIONS
            WHERE [CATALOG_NAME] = '{cat}'
              AND [CUBE_NAME] = '{cube}'
            """

            rows = ejecutar_query_rows_execute(conn, q)

            out = []
            for r in rows:
                out.append({
                    "dimension_unique_name": r.get("DIMENSION_UNIQUE_NAME"),
                    "dimension_name": r.get("DIMENSION_NAME"),
                    "caption": r.get("DIMENSION_CAPTION"),
                    "type": r.get("DIMENSION_TYPE"),
                    "description": r.get("DESCRIPTION"),
                })

            out.sort(key=lambda x: (x["caption"] or x["dimension_name"] or ""))
            return {"catalogo": catalogo, "cubo": cubo, "total": len(out), "dimensiones": out}

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.get("/jerarquias_disponibles", dependencies=[Depends(verify_token)])
def jerarquias_disponibles(catalogo: str, cubo: str):
    try:
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()

        def consulta(conn):
            cat = _sql_str(catalogo)
            cube = _sql_str(cubo)

            q = f"""
            SELECT
              [CATALOG_NAME],
              [CUBE_NAME],
              [DIMENSION_UNIQUE_NAME],
              [HIERARCHY_UNIQUE_NAME],
              [HIERARCHY_NAME],
              [HIERARCHY_CAPTION],
              [HIERARCHY_CARDINALITY],
              [DESCRIPTION]
            FROM $system.MDSCHEMA_HIERARCHIES
            WHERE [CATALOG_NAME] = '{cat}'
              AND [CUBE_NAME] = '{cube}'
            """

            rows = ejecutar_query_rows_execute(conn, q)

            out = []
            for r in rows:
                hun = (r.get("HIERARCHY_UNIQUE_NAME") or "").strip()
                if not hun:
                    continue
                out.append({
                    "dimension_unique_name": r.get("DIMENSION_UNIQUE_NAME"),
                    "hierarchy_unique_name": hun,
                    "hierarchy_name": r.get("HIERARCHY_NAME"),
                    "caption": r.get("HIERARCHY_CAPTION"),
                    "cardinality": r.get("HIERARCHY_CARDINALITY"),
                    "description": r.get("DESCRIPTION"),
                })

            out.sort(key=lambda x: (x["dimension_unique_name"] or "", x["caption"] or x["hierarchy_name"] or ""))
            return {"catalogo": catalogo, "cubo": cubo, "total": len(out), "jerarquias": out}

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.get("/niveles_jerarquia", dependencies=[Depends(verify_token)])
def niveles_jerarquia(catalogo: str, cubo: str, jerarquia_unique_name: str):
    try:
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()
        jerarquia_unique_name = (jerarquia_unique_name or "").strip()

        def consulta(conn):
            cat = _sql_str(catalogo)
            cube = _sql_str(cubo)
            hier = _sql_str(jerarquia_unique_name)

            q = f"""
            SELECT
              [CATALOG_NAME],
              [CUBE_NAME],
              [HIERARCHY_UNIQUE_NAME],
              [LEVEL_UNIQUE_NAME],
              [LEVEL_NAME],
              [LEVEL_CAPTION],
              [LEVEL_NUMBER],
              [LEVEL_CARDINALITY],
              [DESCRIPTION]
            FROM $system.MDSCHEMA_LEVELS
            WHERE [CATALOG_NAME] = '{cat}'
              AND [CUBE_NAME] = '{cube}'
              AND [HIERARCHY_UNIQUE_NAME] = '{hier}'
            """

            rows = ejecutar_query_rows_execute(conn, q)

            out = []
            for r in rows:
                out.append({
                    "level_unique_name": r.get("LEVEL_UNIQUE_NAME"),
                    "level_name": r.get("LEVEL_NAME"),
                    "caption": r.get("LEVEL_CAPTION"),
                    "level_number": r.get("LEVEL_NUMBER"),
                    "cardinality": r.get("LEVEL_CARDINALITY"),
                    "description": r.get("DESCRIPTION"),
                })

            return {"catalogo": catalogo, "cubo": cubo, "jerarquia_unique_name": jerarquia_unique_name, "total": len(out), "niveles": out}

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.get("/medidas_disponibles", dependencies=[Depends(verify_token)])
def medidas_disponibles(catalogo: str, cubo: str):
    try:
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()

        def consulta(conn):
            cat = _sql_str(catalogo)
            cube = _sql_str(cubo)

            q = f"""
            SELECT
              [CATALOG_NAME],
              [CUBE_NAME],
              [MEASURE_UNIQUE_NAME],
              [MEASURE_NAME],
              [MEASURE_CAPTION],
              [MEASUREGROUP_NAME],
              [DATA_TYPE],
              [DEFAULT_FORMAT_STRING],
              [DESCRIPTION]
            FROM $system.MDSCHEMA_MEASURES
            WHERE [CATALOG_NAME] = '{cat}'
              AND [CUBE_NAME] = '{cube}'
            """

            rows = ejecutar_query_rows_execute(conn, q)

            out = []
            for r in rows:
                mun = (r.get("MEASURE_UNIQUE_NAME") or "").strip()
                if not mun:
                    continue
                out.append({
                    "measure_unique_name": mun,
                    "measure_name": r.get("MEASURE_NAME"),
                    "caption": r.get("MEASURE_CAPTION"),
                    "measuregroup": r.get("MEASUREGROUP_NAME"),
                    "data_type": r.get("DATA_TYPE"),
                    "format": r.get("DEFAULT_FORMAT_STRING"),
                    "description": r.get("DESCRIPTION"),
                })

            out.sort(key=lambda x: (x["measuregroup"] or "", x["caption"] or x["measure_name"] or ""))
            return {"catalogo": catalogo, "cubo": cubo, "total": len(out), "medidas": out}

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.get("/debug_buscar_texto_en_variables", dependencies=[Depends(verify_token)])
def debug_buscar_texto_en_variables(
    catalogo: str,
    cubo: str,
    search: str = Query("APLICACIÓN DE BIOLÓGICOS", description="Texto a buscar (normalizado)"),
    max_rows: int = Query(200, description="Máximo de matches a devolver"),
    scan_limit_per_hierarchy: int = Query(50000, description="Máximo de miembros a escanear por jerarquía"),
    page_size: int = Query(5000, description="Tamaño de página TOP N por jerarquía"),
):
    try:
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()

        def consulta(conn):
            cat = _sql_str(catalogo)
            cube = _sql_str(cubo)

            # 1) Jerarquías
            qh = f"""
            SELECT *
            FROM $system.MDSCHEMA_HIERARCHIES
            WHERE [CATALOG_NAME] = '{cat}'
              AND [CUBE_NAME] = '{cube}'
            """
            hier_rows = ejecutar_query_rows_execute(conn, qh) or []

            candidates = []
            for h in hier_rows:
                hun = (h.get("HIERARCHY_UNIQUE_NAME") or "").strip()
                cap = (h.get("HIERARCHY_CAPTION") or h.get("HIERARCHY_NAME") or "").strip()
                if hun and "DIM VARIABLES" in hun.upper():
                    candidates.append({"caption": cap, "hun": hun})

            needle = _norm(search)

            # 2) Levels (para entender 1.0 -> ???)
            levels_by_hier = {}
            for h in candidates:
                hun = h["hun"]
                ql = f"""
                SELECT
                  [LEVEL_NUMBER],
                  [LEVEL_NAME],
                  [LEVEL_CAPTION],
                  [LEVEL_UNIQUE_NAME]
                FROM $system.MDSCHEMA_LEVELS
                WHERE [CATALOG_NAME] = '{cat}'
                  AND [CUBE_NAME] = '{cube}'
                  AND [HIERARCHY_UNIQUE_NAME] = '{_sql_str(hun)}'
                ORDER BY [LEVEL_NUMBER]
                """
                try:
                    lvl_rows = ejecutar_query_rows_execute(conn, ql) or []
                except Exception:
                    lvl_rows = []
                levels_by_hier[hun] = lvl_rows

            matches = []
            scanned_counts = {c["hun"]: 0 for c in candidates}

            def lvl_str(x):
                return str(x).strip() if x is not None else ""

            def fetch_children_level2_by_dmv(hier_unique_name: str, parent_unique_name: str):
                """
                Intenta hijos usando PARENT_UNIQUE_NAME (si el provider lo soporta).
                """
                q_children = f"""
                SELECT TOP 5000
                  [LEVEL_NUMBER],
                  [MEMBER_CAPTION],
                  [MEMBER_NAME],
                  [MEMBER_UNIQUE_NAME],
                  [MEMBER_KEY],
                  [PARENT_UNIQUE_NAME]
                FROM $system.MDSCHEMA_MEMBERS
                WHERE [CATALOG_NAME] = '{cat}'
                  AND [CUBE_NAME] = '{cube}'
                  AND [HIERARCHY_UNIQUE_NAME] = '{_sql_str(hier_unique_name)}'
                  AND [PARENT_UNIQUE_NAME] = '{_sql_str(parent_unique_name)}'
                """
                try:
                    kids = ejecutar_query_rows_execute(conn, q_children) or []
                except Exception:
                    return []
                return [{
                    "level_number": k.get("LEVEL_NUMBER"),
                    "member_caption": k.get("MEMBER_CAPTION"),
                    "member_name": k.get("MEMBER_NAME"),
                    "member_key": k.get("MEMBER_KEY"),
                    "member_unique_name": k.get("MEMBER_UNIQUE_NAME"),
                    "parent_unique_name": k.get("PARENT_UNIQUE_NAME"),
                } for k in kids]

            def fetch_children_next_level_by_mdx(parent_unique_name: str):
                """
                Fallback real: hijos por MDX usando .CHILDREN
                """
                mdx = f"""
                SELECT
                  ( {parent_unique_name} ).CHILDREN
                ON 0
                FROM [{cube}]
                """
                try:
                    return ejecutar_mdx_members_axis0(conn, mdx)
                except Exception:
                    return []

            # 3) Scan paginado por jerarquía
            for h in candidates:
                if len(matches) >= max_rows:
                    break

                hun = h["hun"]
                last_unique = ""
                total_scanned = 0

                while total_scanned < scan_limit_per_hierarchy and len(matches) < max_rows:
                    extra = f" AND [MEMBER_UNIQUE_NAME] > '{_sql_str(last_unique)}' " if last_unique else ""

                    qm = f"""
                    SELECT TOP {int(page_size)}
                      [LEVEL_NUMBER],
                      [MEMBER_CAPTION],
                      [MEMBER_NAME],
                      [MEMBER_UNIQUE_NAME],
                      [MEMBER_KEY]
                    FROM $system.MDSCHEMA_MEMBERS
                    WHERE [CATALOG_NAME] = '{cat}'
                      AND [CUBE_NAME] = '{cube}'
                      AND [HIERARCHY_UNIQUE_NAME] = '{_sql_str(hun)}'
                      {extra}
                    """
                    rows = ejecutar_query_rows_execute(conn, qm) or []
                    if not rows:
                        break

                    total_scanned += len(rows)
                    scanned_counts[hun] = total_scanned
                    last_unique = (rows[-1].get("MEMBER_UNIQUE_NAME") or "").strip() or last_unique

                    for m in rows:
                        if len(matches) >= max_rows:
                            break

                        txt = _norm((m.get("MEMBER_NAME") or "") + " " + (m.get("MEMBER_CAPTION") or ""))
                        if needle not in txt:
                            continue

                        member_un = (m.get("MEMBER_UNIQUE_NAME") or "").strip()
                        lvl = lvl_str(m.get("LEVEL_NUMBER"))

                        item = {
                            "hierarchy_caption": h["caption"],
                            "hierarchy_unique_name": hun,
                            "level_number": m.get("LEVEL_NUMBER"),
                            "member_caption": m.get("MEMBER_CAPTION"),
                            "member_name": m.get("MEMBER_NAME"),
                            "member_key": m.get("MEMBER_KEY"),
                            "member_unique_name": member_un,
                            "children_level_2": [],
                            "children_level_2_source": None,
                        }

                        # Si es nivel 1.0, intenta sacar hijos:
                        if lvl in ("1", "1.0"):
                            kids = fetch_children_level2_by_dmv(hun, member_un)
                            if kids:
                                item["children_level_2"] = kids
                                item["children_level_2_source"] = "MDSCHEMA_MEMBERS:PARENT_UNIQUE_NAME"
                            else:
                                kids_mdx = fetch_children_next_level_by_mdx(member_un)
                                item["children_level_2"] = kids_mdx
                                item["children_level_2_source"] = "MDX:.CHILDREN"

                        matches.append(item)

            return {
                "catalogo": catalogo,
                "cubo": cubo,
                "search": search,
                "total_matches": len(matches),
                "matches": matches,
                "scanned_hierarchies": [c["hun"] for c in candidates],
                "scanned_counts": scanned_counts,
                "levels_by_hierarchy": levels_by_hier,
                "nota": (
                    "No usa LIKE. Escanea por páginas (MEMBER_UNIQUE_NAME) y, "
                    "si no puede sacar hijos con PARENT_UNIQUE_NAME, usa fallback MDX (.CHILDREN)."
                ),
            }

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})



@app.post("/unidad_medica_completa_por_clues", dependencies=[Depends(verify_token)])
def unidad_medica_completa_por_clues(
    catalogo: str = Body(...),
    cubo: str = Body(...),
    clues_list: list[str] = Body(..., description="Array de CLUES"),
):
    try:
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()
        clues_list = [c.strip().upper() for c in (clues_list or []) if (c or "").strip()]
        if not clues_list:
            return JSONResponse(status_code=400, content={"error": "clues_list viene vacío"})

        def consulta(conn):
            cube = sql_lit(cubo)

            resultados, no_encontradas = [], []

            for clue in clues_list:
                clue_sql = mdx_key(clue)
                clues_member = f"[DIM UNIDAD].[Unidad Médica].[CLUES].&[{clue_sql}]"

                mdx_ruta = f"""
                WITH SET [Ruta] AS {{
                    {clues_member},
                    {clues_member}.PARENT,
                    {clues_member}.PARENT.PARENT,
                    {clues_member}.PARENT.PARENT.PARENT,
                    {clues_member}.PARENT.PARENT.PARENT.PARENT
                }}
                SELECT [Ruta] ON 0
                FROM [{cube}]
                """.strip()

                mdx_nombre = f"""
                SELECT ( {clues_member} ).CHILDREN ON 0
                FROM [{cube}]
                """.strip()

                try:
                    ruta_rows = ejecutar_query_rows_execute(conn, mdx_ruta) or []
                    if not ruta_rows:
                        raise Exception("Ruta vacía: CLUES no existe o no tiene padres.")

                    nombre_rows = ejecutar_query_rows_execute(conn, mdx_nombre) or []

                    ruta_unique = mdx_rows_extract_unique_names(ruta_rows)
                    nombre_unique = mdx_rows_extract_unique_names(nombre_rows)

                    all_unique = []
                    seen = set()
                    for u in (ruta_unique + nombre_unique):
                        if u and u not in seen:
                            seen.add(u)
                            all_unique.append(u)

                    captions_map = dmv_member_info_many(conn, catalogo, cubo, all_unique, ejecutar_query_rows_execute)
                    ruta_detalle = build_ruta_detalle(ruta_unique, captions_map)

                    nombre_unidad = None
                    if nombre_unique:
                        info = captions_map.get(nombre_unique[0]) or {}
                        nombre_unidad = info.get("MEMBER_CAPTION") or info.get("MEMBER_NAME") or nombre_unique[0]

                    unidad = format_unidad_medica(ruta_detalle, nombre_unidad, clue)

                    resultados.append({"found": True, **unidad})

                except Exception as e:
                    no_encontradas.append(clue)
                    resultados.append({"found": False, "clues": clue, "error": str(e)})

            return {
                "catalogo": catalogo,
                "cubo": cubo,
                "jerarquia_usada": "[DIM UNIDAD].[Unidad Médica]",
                "total_clues": len(clues_list),
                "clues_no_encontradas": no_encontradas,
                "resultados": resultados,
            }

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})



@app.post("/total_por_clues_variables_por_texto", dependencies=[Depends(verify_token)])
def total_por_clues_variables_por_texto(
    catalogo: str = Body(...),
    cubo: str = Body(...),
    clues_list: list[str] = Body(...),
    search_text: str = Body("APLICACIÓN DE BIOLÓGICOS"),
    max_vars: int = Body(5000),
    devolver_mdx: bool = Body(False),
):
    try:
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()
        search_text = (search_text or "").strip()
        clues_list = [c.strip().upper() for c in (clues_list or []) if (c or "").strip()]

        if not clues_list:
            return JSONResponse(status_code=400, content={"error": "clues_list viene vacío"})
        if not search_text:
            return JSONResponse(status_code=400, content={"error": "search_text viene vacío"})
        if not isinstance(max_vars, int) or max_vars <= 0:
            return JSONResponse(status_code=400, content={"error": "max_vars debe ser entero > 0"})

        def consulta(conn):
            clues_literal = ",\n    ".join(
                f"[DIM UNIDAD].[Unidad Médica].[CLUES].&[{mdx_key(c)}]"
                for c in clues_list
            )

            needle = mdx_str(search_text).upper()

            mdx = f"""
WITH
SET [S_Apartados] AS
  FILTER(
    [DIM VARIABLES].[Apartado y Variable].[Apartado].MEMBERS,
    VBA!InStr(1, VBA!UCase([DIM VARIABLES].[Apartado y Variable].CURRENTMEMBER.NAME), "{needle}") > 0
    OR VBA!InStr(1, VBA!UCase([DIM VARIABLES].[Apartado y Variable].CURRENTMEMBER.PROPERTIES("MEMBER_CAPTION")), "{needle}") > 0
  )

SET [S_VarsRaw] AS
  GENERATE(
    [S_Apartados],
    DESCENDANTS([DIM VARIABLES].[Apartado y Variable].CURRENTMEMBER, 1)
  )

SET [S_Vars] AS
  HEAD([S_VarsRaw], {int(max_vars)})

SELECT
  {{ [Measures].[Total] }} ON 0,
  NON EMPTY [S_Vars] ON 1
FROM (
  SELECT
    {{ {clues_literal} }} ON 0
  FROM [{cubo}]
)
""".strip()

            rows = ejecutar_query_rows_execute(conn, mdx) or []
            resp = {
                "catalogo": catalogo,
                "cubo": cubo,
                "search_text": search_text,
                "total_clues": len(clues_list),
                "max_vars": int(max_vars),
                "rows": rows,
            }
            if devolver_mdx:
                resp["mdx"] = mdx
            return resp

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


@app.post("/biologicos_por_clues_con_unidad22222", dependencies=[Depends(verify_token)])
def biologicos_por_clues_con_unidad(
    catalogo: str = Body(...),
    cubo: str = Body(...),
    clues_list: list[str] = Body(...),
    search_text: str = Body("APLICACIÓN DE BIOLÓGICOS"),
    max_vars: int = Body(5000),
    incluir_ceros: bool = Body(True),
):
    try:
        # -----------------------------
        # Validación / normalización inputs
        # -----------------------------
        catalogo = (catalogo or "").strip()
        cubo = (cubo or "").strip()
        search_text = " ".join((search_text or "").strip().split())
        clues_list = [c.strip().upper() for c in (clues_list or []) if c and c.strip()]

        if not clues_list:
            return JSONResponse(status_code=400, content={"error": "clues_list viene vacío"})
        if not search_text:
            return JSONResponse(status_code=400, content={"error": "search_text viene vacío"})
        if not isinstance(max_vars, int) or max_vars <= 0:
            return JSONResponse(status_code=400, content={"error": "max_vars debe ser entero > 0"})

        def consulta(conn):
            cat = sql_lit(catalogo)
            cube = sql_lit(cubo)

            # ============================================================
            # A) Detectar level de CLUES (para armar set literal)
            # ============================================================
            q_levels = f"""
            SELECT
              [HIERARCHY_UNIQUE_NAME],
              [LEVEL_UNIQUE_NAME],
              [LEVEL_NAME],
              [LEVEL_CAPTION],
              [DIMENSION_UNIQUE_NAME]
            FROM $system.MDSCHEMA_LEVELS
            WHERE [CATALOG_NAME] = '{cat}'
              AND [CUBE_NAME] = '{cube}'
            """
            level_rows = ejecutar_query_rows_execute(conn, q_levels) or []

            clues_level = None
            for r in level_rows:
                lvl_cap = (r.get("LEVEL_CAPTION") or "").upper()
                lvl_name = (r.get("LEVEL_NAME") or "").upper()
                if "CLUES" in lvl_cap or "CLUES" in lvl_name:
                    clues_level = {
                        "hierarchy_unique_name": (r.get("HIERARCHY_UNIQUE_NAME") or "").strip(),
                        "level_unique_name": (r.get("LEVEL_UNIQUE_NAME") or "").strip(),
                        "dimension_unique_name": (r.get("DIMENSION_UNIQUE_NAME") or "").strip(),
                    }
                    break

            if not clues_level or not clues_level["level_unique_name"]:
                return {
                    "catalogo": catalogo,
                    "cubo": cubo,
                    "error": "No se encontró un LEVEL de CLUES en este cubo.",
                }

            clues_level_un = clues_level["level_unique_name"]

            # ============================================================
            # B) Construir mapa de UNIDAD por CLUES
            # ============================================================
            def get_unidad_por_clue(clue: str) -> dict:
                clue_sql = mdx_key(clue)

                # OJO: si tu cubo usa otra jerarquía para unidad médica, ajusta aquí.
                clues_member = f"[DIM UNIDAD].[Unidad Médica].[CLUES].&[{clue_sql}]"
                cubo_safe = cubo.replace("]", "]]")

                mdx_ruta = f"""
                WITH SET [Ruta] AS {{
                  {clues_member},
                  {clues_member}.PARENT,
                  {clues_member}.PARENT.PARENT,
                  {clues_member}.PARENT.PARENT.PARENT,
                  {clues_member}.PARENT.PARENT.PARENT.PARENT
                }}
                SELECT [Ruta] ON 0
                FROM [{cubo_safe}]
                """.strip()

                mdx_nombre = f"""
                SELECT ( {clues_member} ).CHILDREN ON 0
                FROM [{cubo_safe}]
                """.strip()

                ruta_rows = ejecutar_query_rows_execute(conn, mdx_ruta) or []
                if not ruta_rows:
                    return {
                        "nombre": None,
                        "entidad": None,
                        "jurisdiccion": None,
                        "municipio": None,
                        "idinstitucion": None,
                    }

                nombre_rows = ejecutar_query_rows_execute(conn, mdx_nombre) or []

                ruta_unique = mdx_rows_extract_unique_names(ruta_rows)
                nombre_unique = mdx_rows_extract_unique_names(nombre_rows)

                all_unique = []
                seen = set()
                for u in (ruta_unique + nombre_unique):
                    if u and u not in seen:
                        seen.add(u)
                        all_unique.append(u)

                captions_map = dmv_member_info_many(conn, catalogo, cubo, all_unique, ejecutar_query_rows_execute)
                ruta_detalle = build_ruta_detalle(ruta_unique, captions_map)

                # nombre (nivel hijo del CLUES)
                nombre = None
                if nombre_unique:
                    info = captions_map.get(nombre_unique[0]) or {}
                    nombre = info.get("MEMBER_CAPTION") or info.get("MEMBER_NAME") or nombre_unique[0]

                entidad = pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Entidad]")
                jurisdiccion = pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Jurisdicción]")
                municipio = pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Municipio]")

                idinstitucion = (
                    pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[IdInstitucion]")
                    or pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[ID INSTITUCION]")
                    or pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Institución]")
                    or pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[IDINSTITUCION]")
                )

                return {
                    "nombre": nombre,
                    "entidad": entidad,
                    "jurisdiccion": jurisdiccion,
                    "municipio": municipio,
                    "idinstitucion": idinstitucion,
                }

            unidad_por_clue: dict[str, dict] = {}
            for clue in clues_list:
                try:
                    unidad_por_clue[clue] = get_unidad_por_clue(clue)
                except Exception:
                    unidad_por_clue[clue] = {
                        "nombre": None,
                        "entidad": None,
                        "jurisdiccion": None,
                        "municipio": None,
                        "idinstitucion": None,
                    }

            # ============================================================
            # C) MDX de biológicos (CLUES x Variables)
            # ============================================================
            needle = mdx_str(search_text).upper()
            with_member = "MEMBER [Measures].[Total_0] AS COALESCEEMPTY([Measures].[Total], 0)" if incluir_ceros else ""
            measure_member = "[Measures].[Total_0]" if incluir_ceros else "[Measures].[Total]"

            clues_literal = ", ".join(f"{clues_level_un}.&[{mdx_key(c)}]" for c in clues_list)
            cubo_safe = cubo.replace("]", "]]")

            mdx_bio = f"""
WITH
{with_member}

SET [S_Clues] AS {{ {clues_literal} }}

SET [S_Apartados] AS
  FILTER(
    [DIM VARIABLES].[Apartado y Variable].[Apartado].MEMBERS,
    VBA!InStr(1, VBA!UCase([DIM VARIABLES].[Apartado y Variable].CURRENTMEMBER.NAME), "{needle}") > 0
    OR VBA!InStr(1, VBA!UCase([DIM VARIABLES].[Apartado y Variable].CURRENTMEMBER.PROPERTIES("MEMBER_CAPTION")), "{needle}") > 0
  )

SET [S_VarsRaw] AS
  GENERATE([S_Apartados], DESCENDANTS([DIM VARIABLES].[Apartado y Variable].CURRENTMEMBER, 1))

SET [S_Vars] AS HEAD([S_VarsRaw], {int(max_vars)})

SELECT
  {{ {measure_member} }} ON 0,
  CROSSJOIN([S_Clues], [S_Vars]) DIMENSION PROPERTIES MEMBER_CAPTION ON 1
FROM [{cubo_safe}]
""".strip()

            rows_raw = ejecutar_query_rows_execute(conn, mdx_bio) or []

            # ============================================================
            # D) Limpiar filas (keys feas) -> plano: {clues, apartado, variable, total}
            # ============================================================
            def get_by_suffix(row: dict, suffix: str):
                suf = suffix.upper()
                for k, v in row.items():
                    if (k or "").upper().endswith(suf):
                        return v
                return None

            def limpiar_row(row: dict) -> dict:
                return {
                    "clues": (
                        get_by_suffix(row, "].[CLUES].[MEMBER_CAPTION]")
                        or get_by_suffix(row, "].[CLUES].[CLUES].[MEMBER_CAPTION]")
                    ),
                    "apartado": get_by_suffix(row, "].[APARTADO].[MEMBER_CAPTION]"),
                    "variable": get_by_suffix(row, "].[VARIABLE].[MEMBER_CAPTION]"),
                    "total": get_by_suffix(row, "].[TOTAL_0]") or get_by_suffix(row, "].[TOTAL]"),
                }

            planos = [limpiar_row(r) for r in rows_raw]

            # ============================================================
            # E) Armar estructura:
            #     resultados[clue].biologicos[apartado] = {"vars":[], "migrantes_total": 0}
            # ============================================================
            resultados_map = {
                clue: {
                    "clues": clue,
                    "unidad": unidad_por_clue.get(clue) or {
                        "nombre": None, "entidad": None, "jurisdiccion": None, "municipio": None, "idinstitucion": None
                    },
                    "biologicos": {},
                }
                for clue in clues_list
            }

            # NOTA: se espera que ya existan en tu código:
            # - es_migrante(nombre_variable) -> bool
            # - normalizar_apartado(nombre_apartado)
            # - obtener_grupos_para_apartado(apartado_normalizado)
            # - asignar_grupo(nombre_variable, grupos_apartado)
            # - agrupar_por_grupo(vars_list, grupos_apartado)
            # - extraer_edad_inicial(nombre_variable) -> int

            for r in planos:
                clue = (r.get("clues") or "").strip().upper()
                apartado = (r.get("apartado") or "").strip()
                variable = (r.get("variable") or "").strip()
                total_raw = r.get("total")

                if not clue or clue not in resultados_map:
                    continue
                if not apartado or not variable:
                    continue

                try:
                    total = int(float(str(total_raw)))
                except Exception:
                    total = 0

                bio = resultados_map[clue]["biologicos"]
                if apartado not in bio:
                    bio[apartado] = {"vars": [], "migrantes_total": 0}

                # MIGRANTE: se suma a un solo total
                if es_migrante(variable):
                    bio[apartado]["migrantes_total"] += total
                    continue

                # Variables normales: asignar grupo
                apartado_norm = normalizar_apartado(apartado)
                grupos_apartado = obtener_grupos_para_apartado(apartado_norm)
                grupo = asignar_grupo(variable, grupos_apartado)

                bio[apartado]["vars"].append({
                    "variable": variable,
                    "total": total,
                    "grupo": grupo
                })

            # ============================================================
            # F) Convertir a lista final:
            #     - Agregar TOTAL MIGRANTES como variable con grupo migrante
            #     - Ordenar: migrante al final + edad + nombre
            #     - Agrupar por grupo en el orden de negocio
            # ============================================================
            resultados = []
            for clue in clues_list:
                item = resultados_map[clue]
                biologicos_list = []

                for apartado, data in item["biologicos"].items():
                    vars_list = data["vars"]

                    # Agregar variable "TOTAL MIGRANTES" con grupo migrante (para no romper 'grupo')
                    if data["migrantes_total"] != 0 or incluir_ceros:
                        vars_list.append({
                            "variable": f"TOTAL DE VACUNAS APLICADAS A MIGRANTES - {apartado}",
                            "total": data["migrantes_total"],
                            "grupo": "migrante"
                        })

                    apartado_norm = normalizar_apartado(apartado)
                    grupos_apartado = obtener_grupos_para_apartado(apartado_norm)

                    # Orden global antes de agrupar (migrante SIEMPRE al final)
                    vars_list.sort(key=lambda v: (
                        1 if (v.get("grupo") == "migrante") else 0,
                        extraer_edad_inicial(v.get("variable") or ""),
                        (v.get("variable") or "").upper()
                    ))

                    variables_agrupadas = agrupar_por_grupo(vars_list, grupos_apartado)

                    biologicos_list.append({
                        "apartado": apartado,
                        "grupos": variables_agrupadas
                    })

                item["biologicos"] = biologicos_list
                resultados.append(item)

            return {
                "catalogo": catalogo,
                "cubo": cubo,
                "resultados": resultados,
                "clues_detectado": clues_level,  # opcional (debug)
            }

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})
