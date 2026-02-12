from dotenv import load_dotenv
load_dotenv()

import os
import re
from fastapi import FastAPI, Depends, Body
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware

import unicodedata
import pythoncom
import win32com.client
from typing import Callable, Any

from middlewares.auth import verify_token
from config import get_connection_string
from collections import defaultdict

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

def crear_conexion(connection_string: str):
    """Crea conexión ADODB a SSAS / OLAP"""
    conn = win32com.client.Dispatch("ADODB.Connection")
    conn.Open(connection_string)
    return conn

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

def sql_lit(s: str) -> str:
    """Escapa comillas simples para DMVs ($system.*)."""
    return (s or "").replace("'", "''").strip()

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

DIMENSIONES_POR_ANIO = {
    2019: {"unidad_base": "[Clues].[Unidad médica]",    "vars_base": "[Variable].[Apartado y variable]"},
    2020: {"unidad_base": "[DIM_UNIDADES].[Unidad Médica]",    "vars_base": "[DIM VARIABLES].[Apartado y Variable]"},
    2021: {"unidad_base": "[DIM_UNIDADES].[Unidad Médica]",    "vars_base": "[DIM VARIABLES].[Apartado y Variable]"},
    2022: {"unidad_base": "[DIM UNIDADES].[Unidad Médica]",    "vars_base": "[DIM VARIABLES].[Apartado y Variable]"},
    2023: {"unidad_base": "[DIM UNIDAD].[Unidad Médica]",      "vars_base": "[DIM VARIABLES].[Apartado y Variable]"},
    2024: {"unidad_base": "[DIM UNIDAD].[Unidad Médica]",      "vars_base": "[DIM VARIABLES].[Apartado y Variable]"},
    2025: {"unidad_base": "[DIM UNIDADES2025].[Unidad Médica]","vars_base": "[DIM VARIABLES2025].[Apartado y Variable]"},
}

def detectar_anio(catalogo: str, cubo: str) -> int:
    txt = f"{catalogo or ''} {cubo or ''}"
    m = re.search(r"(19|20)\d{2}", txt)
    if not m:
        raise ValueError("No pude detectar el año desde catalogo/cubo")
    return int(m.group(0))

def get_bases_por_anio(catalogo: str, cubo: str) -> dict:
    anio = detectar_anio(catalogo, cubo)
    cfg = DIMENSIONES_POR_ANIO.get(anio)
    if not cfg:
        raise ValueError(f"No hay configuración para el año {anio}")
    return cfg

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

def mdx_key(s: str) -> str:
    """Para armar .&[KEY]. (escapa ])"""
    return (s or "").strip().replace("]", "]]")

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

def mdx_str(s: str) -> str:
    """Escapa comillas dobles para meter texto en ' "..." ' dentro de MDX."""
    return (s or "").replace('"', '""').strip()

def _norm_txt(s: str) -> str:
    s = (s or "").strip().upper()
    s = "".join(
        c for c in unicodedata.normalize("NFKD", s)
        if not unicodedata.combining(c)
    )
    return " ".join(s.split())

def es_migrante(nombre_variable: str) -> bool:
    return "MIGRANTE" in _norm_txt(nombre_variable)

def normalizar_apartado(nombre):
    nombre = (nombre or "").upper()
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

INSTITUCION_POR_PREFIJO = {
    "HGIMB": "IMB",
    "HGSSA": "SSA",
}

def detectar_institucion_por_clues(clues: str) -> str | None:
    c = (clues or "").strip().upper()
    if len(c) < 5:
        return None
    pref = c[:5]
    return INSTITUCION_POR_PREFIJO.get(pref)


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
                if m and int(m.group(1)) >= 2019:
                    estandarizados.add(f"SIS {m.group(1)}")
                    continue

                m = sinba_regex.match(name)
                if m and int(m.group(1)) >= 2019:
                    estandarizados.add(f"SIS {m.group(1)}")

            return sorted(estandarizados)

        return {"cubos": ejecutar_conexion_olap(consulta)}

    except Exception:
        return JSONResponse(status_code=500, content={"error": "Error interno"})


@app.post("/biologicos_por_clues_con_unidad", dependencies=[Depends(verify_token)])
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

        # -----------------------------
        # Resolver bases por año
        # OJO: usa TU get_bases_por_anio() (ya lo tienes)
        # -----------------------------
        cfg = get_bases_por_anio(catalogo, cubo)
        unidad_base = cfg["unidad_base"]   # ej: [DIM UNIDAD].[Unidad Médica]
        vars_base = cfg["vars_base"]       # ej: [DIM VARIABLES].[Apartado y Variable]

        # Levels internos (por tu evidencia son constantes en nombre, cambia el prefijo)
        LVL_ENTIDAD = f"{unidad_base}.[Entidad]"
        LVL_JURIS   = f"{unidad_base}.[Jurisdicción]"
        LVL_MUN     = f"{unidad_base}.[Municipio]"
        LVL_CLUES   = f"{unidad_base}.[CLUES]"
        LVL_NOMBRE  = f"{unidad_base}.[Nombre de la Unidad Médica]"  # (no siempre lo usas directo)

        def consulta(conn):
            # cache DMV por request: MEMBER_UNIQUE_NAME -> info
            dmv_cache: dict[str, dict | None] = {}

            # ============================================================
            # Helper: member_info_many con cache (firma correcta)
            # ============================================================
            def dmv_member_info_many_cached(
                unique_names: list[str],
            ) -> dict[str, dict | None]:
                out: dict[str, dict | None] = {}
                for un in unique_names or []:
                    un = (un or "").strip()
                    if not un:
                        continue
                    if un not in dmv_cache:
                        # IMPORTANTE: tu dmv_member_info pide ejecutar_query_rows_execute
                        dmv_cache[un] = dmv_member_info(conn, catalogo, cubo, un, ejecutar_query_rows_execute)
                    out[un] = dmv_cache[un]
                return out

            # ============================================================
            # A) UNIDAD POR CLUE (con NOMBRE DE LA UNIDAD como hijo de CLUES)
            # ============================================================
            def get_unidad_por_clue(clue: str) -> dict:
                clue_key = mdx_key(clue)  # usa tu mdx_key existente
                cubo_safe = cubo.replace("]", "]]")

                # Member CLUES en la jerarquía de unidad por año
                clues_member = f"{LVL_CLUES}.&[{clue_key}]"

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
                        "municipio": None
                    }

                nombre_rows = ejecutar_query_rows_execute(conn, mdx_nombre) or []

                ruta_unique = mdx_rows_extract_unique_names(ruta_rows)
                nombre_unique = mdx_rows_extract_unique_names(nombre_rows)

                # pedir captions de ruta + nombre
                all_unique: list[str] = []
                seen = set()
                for u in (ruta_unique + nombre_unique):
                    if u and u not in seen:
                        seen.add(u)
                        all_unique.append(u)

                captions_map = dmv_member_info_many_cached(all_unique)
                ruta_detalle = build_ruta_detalle(ruta_unique, captions_map)

                # Nombre UM (caption del hijo del CLUES)
                nombre = None
                if nombre_unique:
                    info = captions_map.get(nombre_unique[0]) or {}
                    nombre = info.get("MEMBER_CAPTION") or info.get("MEMBER_NAME") or nombre_unique[0]

                entidad = pick_caption_by_level(ruta_detalle, LVL_ENTIDAD)
                jurisdiccion = pick_caption_by_level(ruta_detalle, LVL_JURIS)
                municipio = pick_caption_by_level(ruta_detalle, LVL_MUN)

                return {
                    "nombre": nombre,
                    "entidad": entidad,
                    "jurisdiccion": jurisdiccion,
                    "municipio": municipio,
                }

            unidad_por_clue: dict[str, dict] = {}
            for clue in clues_list:
                try:
                    unidad_por_clue[clue] = get_unidad_por_clue(clue)
                except Exception as e:
                    unidad_por_clue[clue] = {
                        "nombre": None,
                        "entidad": None,
                        "jurisdiccion": None,
                        "municipio": None,
                        "_error": str(e),  # para debug
                    }

            # ============================================================
            # B) MDX BIOLÓGICOS (vars_base por año)
            # ============================================================
            needle = mdx_str(search_text).upper()
            with_member = "MEMBER [Measures].[Total_0] AS COALESCEEMPTY([Measures].[Total], 0)" if incluir_ceros else ""
            measure_member = "[Measures].[Total_0]" if incluir_ceros else "[Measures].[Total]"

            # Detectar level de CLUES para el set literal (tu lógica original)
            cat = sql_lit(catalogo)
            cube = sql_lit(cubo)
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
            clues_literal = ", ".join(f"{clues_level_un}.&[{mdx_key(c)}]" for c in clues_list)
            cubo_safe = cubo.replace("]", "]]")

            apartado_members = f"{vars_base}.[Apartado].MEMBERS"
            hier_current = f"{vars_base}.CURRENTMEMBER"

            mdx_bio = f"""
WITH
{with_member}

SET [S_Clues] AS {{ {clues_literal} }}

SET [S_Apartados] AS
    FILTER(
    {apartado_members},
    VBA!InStr(1, VBA!UCase({hier_current}.NAME), "{needle}") > 0
    OR VBA!InStr(1, VBA!UCase({hier_current}.PROPERTIES("MEMBER_CAPTION")), "{needle}") > 0
    )

SET [S_VarsRaw] AS
    GENERATE([S_Apartados], DESCENDANTS({hier_current}, 1))

SET [S_Vars] AS HEAD([S_VarsRaw], {int(max_vars)})

SELECT
    {{ {measure_member} }} ON 0,
    CROSSJOIN([S_Clues], [S_Vars]) DIMENSION PROPERTIES MEMBER_CAPTION ON 1
FROM [{cubo_safe}]
""".strip()

            rows_raw = ejecutar_query_rows_execute(conn, mdx_bio) or []

            # ============================================================
            # C) LIMPIAR A PLANO
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
            # D) ARMAR RESULTADOS
            # ============================================================
            resultados_map = {
                clue: {
                    "clues": clue,
                    "unidad": {
                        **(unidad_por_clue.get(clue) or {
                            "nombre": None,
                            "entidad": None,
                            "jurisdiccion": None,
                            "municipio": None,
                            "institucion": None,
                        }),
                        # 👇 aquí lo calculas desde el CLUES
                        "institucion": detectar_institucion_por_clues(clue),
                    },
                    "biologicos": {},
                }
                for clue in clues_list
            }

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

                if es_migrante(variable):
                    bio[apartado]["migrantes_total"] += total
                    continue

                apartado_norm = normalizar_apartado(apartado)
                grupos_apartado = obtener_grupos_para_apartado(apartado_norm)
                grupo = asignar_grupo(variable, grupos_apartado)

                bio[apartado]["vars"].append({"variable": variable, "total": total, "grupo": grupo})

            resultados = []
            for clue in clues_list:
                item = resultados_map[clue]
                biologicos_list = []

                for apartado, data in item["biologicos"].items():
                    vars_list = data["vars"]

                    if data["migrantes_total"] != 0 or incluir_ceros:
                        vars_list.append({
                            "variable": f"TOTAL DE VACUNAS APLICADAS A MIGRANTES - {apartado}",
                            "total": data["migrantes_total"],
                            "grupo": "migrante",
                        })

                    apartado_norm = normalizar_apartado(apartado)
                    grupos_apartado = obtener_grupos_para_apartado(apartado_norm)

                    # migrante al final + edad + nombre
                    vars_list.sort(key=lambda v: (
                        1 if (v.get("grupo") == "migrante") else 0,
                        extraer_edad_inicial(v.get("variable") or ""),
                        (v.get("variable") or "").upper(),
                    ))

                    biologicos_list.append({
                        "apartado": apartado,
                        "grupos": agrupar_por_grupo(vars_list, grupos_apartado),
                    })

                item["biologicos"] = biologicos_list
                resultados.append(item)

            return {
                "catalogo": catalogo,
                "cubo": cubo,
                "anio_detectado": detectar_anio(catalogo, cubo),
                "unidad_base": unidad_base,
                "vars_base": vars_base,
                "resultados": resultados,
                "clues_detectado": clues_level,  # debug opcional
            }

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})
