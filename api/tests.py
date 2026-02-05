from dotenv import load_dotenv
load_dotenv()

import os
from fastapi import Query
import re
from fastapi import FastAPI, Depends
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import unicodedata


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


from fastapi import Query, Depends
from fastapi.responses import JSONResponse

def ejecutar_mdx_members_axis0(conn, mdx: str):
    """
    Ejecuta MDX que regresa un SET en el eje 0 y extrae miembros y propiedades.
    Compatible con Cellset COM (MSOLAP/ADOMD).
    """
    cs = conn.Execute(mdx)
    axis = cs.Axes(0)

    out = []
    for pos in axis.Positions:
        # normalmente hay 1 miembro por posición en axis 0
        mem = pos.Members(0)
        # Propiedades típicas
        out.append({
            "member_caption": getattr(mem, "Caption", None),
            "member_name": getattr(mem, "Name", None),
            "member_unique_name": getattr(mem, "UniqueName", None),
            "level_number": getattr(getattr(mem, "Level", None), "LevelNumber", None),
            "level_unique_name": getattr(getattr(mem, "Level", None), "UniqueName", None),
            "member_key": None,  # a veces no viene por MDX axis; si lo necesitas, se puede pedir por PROPERTIES
        })
    return out

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


from fastapi import Body, Depends
from fastapi.responses import JSONResponse

def ejecutar_mdx_members_axis0(conn, mdx: str):
    """
    Ejecuta MDX que regresa un SET en el eje 0 y extrae miembros.
    Compatible con Cellset COM (MSOLAP/ADOMD).
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

def ejecutar_mdx_rows_execute(conn, mdx: str):
    """
    Ejecuta MDX y regresa rows como lista de dict.
    Debe funcionar con el mismo conn que usas en ejecutar_query_rows_execute.
    """
    rs = conn.Execute(mdx)  # <- en tu caso regresa tuple, agarramos el recordset
    # Algunos providers regresan (recordset, ) o (recordset, count)
    recordset = rs[0] if isinstance(rs, tuple) else rs

    # Armar columnas
    cols = [recordset.Fields(i).Name for i in range(recordset.Fields.Count)]

    out = []
    while not recordset.EOF:
        row = {}
        for i, c in enumerate(cols):
            row[c] = recordset.Fields(i).Value
        out.append(row)
        recordset.MoveNext()

    return out

from fastapi import Body, Depends
from fastapi.responses import JSONResponse

# -----------------------------
# Helpers seguros para DMVs
# -----------------------------
def _sql_lit(s: str) -> str:
    return (s or "").replace("'", "''")

def resolver_caption_unico(conn, catalogo: str, cubo: str, member_unique_name: str):
    """
    Resuelve MEMBER_UNIQUE_NAME -> MEMBER_CAPTION / MEMBER_NAME con DMV.
    Importante: SIN IN, SIN LIKE.
    """
    cat = _sql_lit(catalogo)
    cube = _sql_lit(cubo)
    mun = _sql_lit(member_unique_name)

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

def resolver_captions_lista(conn, catalogo: str, cubo: str, unique_names: list[str]):
    """
    Resuelve varios unique_names -> caption, 1-por-1 (con cache).
    """
    cache = {}
    out = {}
    for un in unique_names:
        un = (un or "").strip()
        if not un:
            continue
        if un in cache:
            out[un] = cache[un]
            continue
        info = resolver_caption_unico(conn, catalogo, cubo, un)
        cache[un] = info
        out[un] = info
    return out

def extraer_unique_names_de_rows_mdx(rows: list[dict]) -> list[str]:
    """
    Tus mdx rows llegan como:
      [ { "<unique_name_1>": "cantidad", "<unique_name_2>": "cantidad", ... } ]
    Extrae SOLO keys (unique_names).
    """
    uniq = []
    seen = set()
    for r in rows or []:
        if not isinstance(r, dict):
            continue
        for k in r.keys():
            k = (k or "").strip()
            if not k or k in seen:
                continue
            seen.add(k)
            uniq.append(k)
    return uniq

def pick_caption_by_level(ruta_detalle: list[dict], level_unique_name: str):
    for r in ruta_detalle or []:
        if (r.get("level_unique_name") or "").strip() == level_unique_name:
            return r.get("member_caption")
    return None

def formatear_unidad_medica(ruta_detalle: list[dict], nombre_unidad: str, clue_input: str):
    return {
        "clues": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[CLUES]") or clue_input,
        "nombre_unidad": nombre_unidad,
        "entidad": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Entidad]"),
        "jurisdiccion": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Jurisdicción]"),
        "municipio": pick_caption_by_level(ruta_detalle, "[DIM UNIDAD].[Unidad Médica].[Municipio]"),
    }

def ruta_detalle_desde_unique_names(unique_names: list[str], captions_map: dict) -> list[dict]:
    """
    Construye ruta_detalle (con level info) desde unique_names resueltos por DMV.
    """
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


# -----------------------------
# ENDPOINT
# -----------------------------
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

        def consulta(conn):
            cube = _sql_lit(cubo)

            resultados = []
            no_encontradas = []

            for clue in clues_list:
                clue_sql = _sql_lit(clue)

                # CLUES en la jerarquía que tú quieres
                clues_member = f"[DIM UNIDAD].[Unidad Médica].[CLUES].&[{clue_sql}]"

                # Ruta hacia arriba (4->3->2->1->0)
                mdx_ruta = f"""
                WITH
                SET [Ruta] AS {{
                  {clues_member},
                  {clues_member}.PARENT,
                  {clues_member}.PARENT.PARENT,
                  {clues_member}.PARENT.PARENT.PARENT,
                  {clues_member}.PARENT.PARENT.PARENT.PARENT
                }}
                SELECT
                  [Ruta]
                ON 0
                FROM [{cube}]
                """

                # Nombre unidad (nivel 5): hijos del CLUES
                mdx_nombre = f"""
                SELECT
                  ( {clues_member} ).CHILDREN
                ON 0
                FROM [{cube}]
                """

                try:
                    ruta_rows = ejecutar_query_rows_execute(conn, mdx_ruta) or []
                    if not ruta_rows:
                        raise Exception("Ruta vacía: CLUES no existe en [DIM UNIDAD].[Unidad Médica].[CLUES] o no tiene padres.")

                    nombre_rows = ejecutar_query_rows_execute(conn, mdx_nombre) or []

                    # 1) keys/unique_names (ignoramos cantidades)
                    ruta_unique = extraer_unique_names_de_rows_mdx(ruta_rows)
                    nombre_unique = extraer_unique_names_de_rows_mdx(nombre_rows)

                    # 2) resolver captions por DMV (1x1) con cache
                    all_unique = []
                    seen = set()
                    for u in (ruta_unique + nombre_unique):
                        if u and u not in seen:
                            seen.add(u)
                            all_unique.append(u)

                    captions_map = resolver_captions_lista(conn, catalogo, cubo, all_unique)

                    # 3) construir ruta_detalle con level info
                    ruta_detalle = ruta_detalle_desde_unique_names(ruta_unique, captions_map)

                    # 4) nombre de unidad (level 5) en texto
                    nombre_unidad = None
                    if nombre_unique:
                        info = captions_map.get(nombre_unique[0])
                        nombre_unidad = (info.get("MEMBER_CAPTION") or info.get("MEMBER_NAME") or nombre_unique[0]) if info else nombre_unique[0]

                    # 5) FORMATO FINAL QUE QUIERES
                    unidad = formatear_unidad_medica(
                        ruta_detalle=ruta_detalle,
                        nombre_unidad=nombre_unidad,
                        clue_input=clue
                    )

                    resultados.append({
                        "found": True,
                        **unidad
                    })

                except Exception as e:
                    no_encontradas.append(clue)
                    resultados.append({
                        "found": False,
                        "clues": clue,
                        "error": str(e),
                    })

            return {
                "catalogo": catalogo,
                "cubo": cubo,
                "jerarquia_usada": "[DIM UNIDAD].[Unidad Médica]",
                "total_clues": len(clues_list),
                "clues_no_encontradas": no_encontradas,
                "resultados": resultados,  # array listo con CLUES, NOMBRE, ENTIDAD, JURIS, MUNICIPIO
            }

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})
