# Python client for studizz-api-amqp

Language-specific guide. Read `api-spec.md` first.

## Which HTTP client?

Pick whichever is already a dependency of the target project:

1. **`httpx`** — modern, supports sync + async, recommended when there is a free choice.
2. **`requests`** — still the most common in Python codebases. Use it if it's already imported.
3. **`aiohttp`** — only if the project is already async-first and uses it elsewhere.
4. **`urllib.request`** (stdlib) — only if the project explicitly avoids third-party deps.

If the project uses FastAPI/Starlette or anything async-heavy, prefer `httpx` (`AsyncClient`). If it's a Django / Flask / script-style app, `requests` is fine.

## Configuration

Read the base URL from the environment:

```python
import os

STUDIZZ_API_AMQP_URL = os.environ["STUDIZZ_API_AMQP_URL"].rstrip("/")
```

Add to `.env.example` (if the project uses `python-dotenv` or similar):

```
STUDIZZ_API_AMQP_URL=https://<host>
```

For Django: put it in `settings.py` as `STUDIZZ_API_AMQP_URL = os.environ["STUDIZZ_API_AMQP_URL"].rstrip("/")` and import from there.

## Reference implementation — `requests` (sync)

File: `studizz_amqp_client.py`

```python
"""Client for the Studizz AMQP REST gateway (studizz-api-amqp)."""

from __future__ import annotations

import os
from typing import Any, Mapping

import requests


class StudizzAmqpError(RuntimeError):
    """Raised when the Studizz AMQP gateway returns a non-2xx response or is unreachable."""


class StudizzAmqpClient:
    def __init__(self, base_url: str | None = None, timeout: float = 5.0) -> None:
        url = base_url or os.environ["STUDIZZ_API_AMQP_URL"]
        self._base_url = url.rstrip("/")
        self._timeout = timeout
        self._session = requests.Session()

    def publish_task(self, payload: Mapping[str, Any]) -> None:
        """Publish a JSON payload to the `async-tasks` exchange."""
        self._post("/api/task", payload)

    def publish_call(self, payload: Mapping[str, Any]) -> None:
        """Publish a JSON payload to the `async-call` exchange."""
        self._post("/api/call", payload)

    def _post(self, path: str, payload: Mapping[str, Any]) -> None:
        if not payload:
            raise ValueError("Studizz AMQP payload cannot be empty.")

        try:
            response = self._session.post(
                f"{self._base_url}{path}",
                json=payload,
                headers={"Content-Type": "application/json"},
                timeout=self._timeout,
            )
        except requests.RequestException as exc:
            raise StudizzAmqpError(f"Studizz AMQP call to {path} failed: {exc}") from exc

        if response.status_code != 200:
            raise StudizzAmqpError(
                f"Studizz AMQP call to {path} returned HTTP {response.status_code}: {response.text}"
            )
```

Usage:

```python
from studizz_amqp_client import StudizzAmqpClient

client = StudizzAmqpClient()
client.publish_task({"type": "send_email", "to": "user@example.com", "template": "welcome"})
```

## Reference implementation — `httpx` (async)

```python
from __future__ import annotations

import os
from typing import Any, Mapping

import httpx


class StudizzAmqpError(RuntimeError):
    pass


class StudizzAmqpClient:
    def __init__(self, base_url: str | None = None, timeout: float = 5.0) -> None:
        url = base_url or os.environ["STUDIZZ_API_AMQP_URL"]
        self._client = httpx.AsyncClient(base_url=url.rstrip("/"), timeout=timeout)

    async def publish_task(self, payload: Mapping[str, Any]) -> None:
        await self._post("/api/task", payload)

    async def publish_call(self, payload: Mapping[str, Any]) -> None:
        await self._post("/api/call", payload)

    async def aclose(self) -> None:
        await self._client.aclose()

    async def _post(self, path: str, payload: Mapping[str, Any]) -> None:
        if not payload:
            raise ValueError("Studizz AMQP payload cannot be empty.")

        try:
            response = await self._client.post(
                path,
                json=payload,
                headers={"Content-Type": "application/json"},
            )
        except httpx.HTTPError as exc:
            raise StudizzAmqpError(f"Studizz AMQP call to {path} failed: {exc}") from exc

        if response.status_code != 200:
            raise StudizzAmqpError(
                f"Studizz AMQP call to {path} returned HTTP {response.status_code}: {response.text}"
            )
```

For FastAPI, expose the client through the app's lifespan so the `AsyncClient` is created once and closed on shutdown.

## Install commands

```bash
# requests (sync)
pip install requests

# httpx (sync or async)
pip install httpx
```

If the project uses `pyproject.toml` / Poetry / uv / Pipenv, add the dependency through the appropriate tool instead of `pip install` directly.

## Gotchas

- **`json=payload`** handles both serialization and the `Content-Type: application/json` header. The explicit header in the examples above is redundant for `requests` / `httpx` but is kept for clarity — you can drop it.
- **Session reuse**: creating a `requests.Session` (or a single `httpx.AsyncClient`) avoids reconnecting on every call. Matters under load. The reference client does this.
- **`requests.RequestException`** is the correct base class to catch — it covers both connection errors and timeouts. Don't catch bare `Exception`.
- **Booleans and `None`**: `{}` is falsy in Python, so the `if not payload` check correctly rejects empty dicts. It will also reject `None` and `[]`, which is what we want.
