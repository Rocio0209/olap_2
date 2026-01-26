from dotenv import load_dotenv
load_dotenv()

import os
import re
from fastapi import FastAPI, Depends
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware


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
                if m and int(m.group(1)) >= 2019:
                    permitidos.append(name)
                    continue

                m = sinba_regex.match(name)
                if m and int(m.group(1)) >= 2019:
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

@app.get("/cubos_en_catalogo2222", dependencies=[Depends(verify_token)])
def cubos_en_catalogo2222(catalogo: str):
    try:
        catalogo = (catalogo or "").strip()

        def consulta(conn):
            rows = openschema_rows(conn, 20, [catalogo, None, None])

            sample_keys = list(rows[0].keys()) if rows else []
            sample_rows = rows[:2] if rows else []

            return {
                "catalogo": catalogo,
                "rows_total": len(rows),
                "sample_keys": sample_keys,
                "sample_rows": sample_rows,
            }

        return ejecutar_conexion_olap(consulta, catalogo=catalogo)

    except Exception as e:
        return JSONResponse(status_code=500, content={"error": str(e)})


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
def cubos_en_catalogo(catalogo: str):
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

            # filtra aquí (en vez de IS NOT NULL)
            out = []
            for r in rows:
                cube_name = (r.get("CUBE_NAME") or "").strip()
                if not cube_name:
                    continue
                out.append({
                    "catalogo": r.get("CATALOG_NAME"),
                    "cubo": cube_name,
                    "caption": r.get("CUBE_CAPTION"),
                    "cube_type": r.get("CUBE_TYPE"),
                    "cube_source": r.get("CUBE_SOURCE"),
                    "description": r.get("DESCRIPTION"),
                })

            out.sort(key=lambda x: x["cubo"])
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
            ORDER BY [LEVEL_NUMBER]
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
