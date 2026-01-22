from dotenv import load_dotenv
load_dotenv()
import os

from fastapi import FastAPI, Depends
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware

import pythoncom
import win32com.client

from middlewares.auth import verify_token
from config import get_connection_string

app = FastAPI()

raw = os.getenv("CORS_ORIGINS""")
origins = [o.strip() for o in raw.split(",") if o.strip()]

if origins:  # solo activa CORS si hay lista definida
    app.add_middleware(
        CORSMiddleware,
        allow_origins=origins,
        allow_credentials=False,
        allow_methods=["GET", "POST"],
        allow_headers=["Authorization", "Content-Type"],
    )

# =========================
# OLAP helpers (EN ESTE ARCHIVO)
# =========================

def crear_conexion(connection_string: str):
    """
    Crea conexión ADODB a SSAS / OLAP
    """
    conn = win32com.client.Dispatch("ADODB.Connection")
    conn.Open(connection_string)
    return conn


def ejecutar_query_lista(conn, query: str, field: str):
    """
    Ejecuta query MDX / schema y regresa lista de valores
    """
    rs = win32com.client.Dispatch("ADODB.Recordset")
    rs.Open(query, conn)

    resultados = []
    while not rs.EOF:
        resultados.append(str(rs.Fields(field).Value))
        rs.MoveNext()

    rs.Close()
    return resultados


# =========================
# Endpoint de prueba
# =========================

@app.get("/cubos_disponibles", dependencies=[Depends(verify_token)])
def cubos_disponibles():
    """
    Endpoint básico de prueba:
    - Auth
    - Config
    - Conexión OLAP
    """
    conn = None
    try:
        pythoncom.CoInitialize()

        conn = crear_conexion(get_connection_string())
        cubos = ejecutar_query_lista(
            conn,
            "SELECT [CATALOG_NAME] FROM $system.DBSCHEMA_CATALOGS",
            "CATALOG_NAME",
        )

        return {"cubos": sorted(set(cubos))}

    except Exception as e:
        return JSONResponse(
            status_code=500,
            content={"error": str(e)},
        )

    finally:
        if conn:
            try:
                conn.Close()
            except Exception:
                pass
        pythoncom.CoUninitialize()
