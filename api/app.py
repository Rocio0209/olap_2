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
