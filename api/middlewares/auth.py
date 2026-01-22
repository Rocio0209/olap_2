# api/middlewares/auth.py
from __future__ import annotations

import os
import secrets
from dotenv import load_dotenv
from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

load_dotenv(override=False)

def _load_tokens() -> list[str]:
    raw = os.getenv("API_TOKENS") or os.getenv("API_TOKEN") or ""
    tokens = [t.strip() for t in raw.split(",") if t.strip()]
    if not tokens:
        raise RuntimeError("Config inválida: define API_TOKENS o API_TOKEN.")
    return tokens

TOKENS = _load_tokens()

bearer_scheme = HTTPBearer(auto_error=False)  # <- no truena solo por faltar header

async def verify_token(
    creds: HTTPAuthorizationCredentials | None = Depends(bearer_scheme),
) -> None:
    if creds is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Falta Authorization",
            headers={"WWW-Authenticate": "Bearer"},
        )

    token = creds.credentials  # lo que va después de "Bearer "
    if not any(secrets.compare_digest(token, t) for t in TOKENS):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Token inválido",
        )
