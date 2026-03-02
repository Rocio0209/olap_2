"""Authentication middleware for FastAPI endpoints.

This module enforces Bearer token authentication against values configured
in environment variables:
- API_TOKENS: comma-separated list of valid tokens
- API_TOKEN: single token fallback
"""

from __future__ import annotations

import os
import secrets
from dotenv import load_dotenv
from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

# Load environment variables from .env if present.
load_dotenv(override=False)


def _load_tokens() -> list[str]:
    """Read and validate API tokens from environment variables."""
    raw = os.getenv("API_TOKENS") or os.getenv("API_TOKEN") or ""
    tokens = [t.strip() for t in raw.split(",") if t.strip()]
    if not tokens:
        raise RuntimeError("Invalid configuration: define API_TOKENS or API_TOKEN.")
    return tokens


TOKENS = _load_tokens()

# auto_error=False lets us return custom 401/403 responses.
bearer_scheme = HTTPBearer(auto_error=False)


async def verify_token(
    creds: HTTPAuthorizationCredentials | None = Depends(bearer_scheme),
) -> None:
    """FastAPI dependency that authorizes requests via Bearer token.

    Behavior:
    - Missing Authorization header -> 401
    - Invalid token -> 403
    - Valid token -> request is authorized
    """
    if creds is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Authorization header",
            headers={"WWW-Authenticate": "Bearer"},
        )

    token = creds.credentials
    if not any(secrets.compare_digest(token, t) for t in TOKENS):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Invalid token",
        )
