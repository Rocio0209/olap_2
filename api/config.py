# api/config.py
from __future__ import annotations

import os
import re
from dotenv import load_dotenv

# Carga .env solo si existe (útil en dev, no estorba en prod)
load_dotenv(override=False)

# =========================
# Variables OLAP
# =========================

OLAP_USER = os.getenv("OLAP_USER")
OLAP_PASSWORD = os.getenv("OLAP_PASSWORD")
OLAP_SERVER = os.getenv("OLAP_SERVER", "pwidgis03.salud.gob.mx")
OLAP_PROVIDER = os.getenv("OLAP_PROVIDER", "MSOLAP.8")

# =========================
# Validaciones tempranas
# =========================

if not OLAP_USER:
    raise RuntimeError("OLAP_USER no está definido en variables de entorno")

if not OLAP_PASSWORD:
    raise RuntimeError("OLAP_PASSWORD no está definido en variables de entorno")

# Solo permitimos catálogos seguros (evita inyección por connection string)
_CATALOG_RE = re.compile(r"^[A-Za-z0-9 _.\-]+$")


# =========================
# Connection string
# =========================

def get_connection_string(catalog: str | None = None) -> str:
    """
    Construye la cadena de conexión para SSAS / OLAP (ADODB).
    - Valida configuración
    - Sanitiza catálogo
    - No loguear el resultado (contiene password)
    """

    conn = (
        f"Provider={OLAP_PROVIDER};"
        f"Data Source={OLAP_SERVER};"
        f"User ID={OLAP_USER};"
        f"Password={OLAP_PASSWORD};"
        "Persist Security Info=True;"
        "Connect Timeout=60;"
    )

    if catalog:
        if not _CATALOG_RE.fullmatch(catalog):
            raise ValueError(
                "Catálogo OLAP inválido. "
                "Solo se permiten letras, números, espacio, guion, punto y guion bajo."
            )
        conn += f"Initial Catalog={catalog};"

    return conn
