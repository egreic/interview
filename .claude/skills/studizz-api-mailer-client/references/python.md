# Python client for studizz-api-mailer

Read `api-spec.md` first. This file covers FastAPI, Flask, and standalone Python scripts.

## Which HTTP client?

1. **`httpx`** — best default. Sync + async, modern, often already installed in FastAPI projects (`httpx` is in the FastAPI test extras).
2. **`requests`** — fine fallback when the project already uses it. No reason to add it on top of `httpx`.
3. **`urllib`** — only for zero-dependency scripts. Verbose; avoid unless asked.

Inspect `pyproject.toml` / `requirements.txt` first and reuse whatever is there.

## Configuration

```
# .env
STUDIZZ_API_MAILER_URL=https://<mailer-host>

# only if sending SMS via OVH
OVH_SMS_ACCOUNT=sms-xxx-1
OVH_SMS_LOGIN=ovh-user-login
OVH_SMS_PASSWORD=ovh-user-password
```

Load via `python-dotenv`, Pydantic Settings, or Django's `os.environ` — match the project's existing pattern.

## Reference implementation — `httpx` (sync)

File: `studizz_mailer.py`

```python
from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Any

import httpx


class StudizzMailerError(RuntimeError):
    def __init__(self, path: str, http_status: int, body: Any) -> None:
        code = (body or {}).get("code", http_status) if isinstance(body, dict) else http_status
        message = (body or {}).get("message", "(no message)") if isinstance(body, dict) else "(no message)"
        super().__init__(
            f"studizz-api-mailer rejected {path} (http={http_status}, code={code}): {message}"
        )
        self.path = path
        self.http_status = http_status
        self.body = body


@dataclass
class StudizzMailerClient:
    base_url: str = ""
    timeout: float = 15.0

    def __post_init__(self) -> None:
        url = self.base_url or os.environ.get("STUDIZZ_API_MAILER_URL", "")
        if not url:
            raise RuntimeError("STUDIZZ_API_MAILER_URL is not configured.")
        self.base_url = url.rstrip("/")

    def send_mail(self, payload: dict[str, Any]) -> dict[str, Any]:
        """Send a transactional email. See api-spec.md for the payload schema."""
        return self._post("/mail", payload)

    def send_sms(self, payload: dict[str, Any]) -> dict[str, Any]:
        """Send an SMS. See api-spec.md — payload must include provider, text, from, to, credentials."""
        return self._post("/sms/send", payload)

    def _post(self, path: str, payload: dict[str, Any]) -> dict[str, Any]:
        try:
            response = httpx.post(
                f"{self.base_url}{path}",
                json=payload,
                headers={"Accept": "application/json"},
                timeout=self.timeout,
            )
        except httpx.HTTPError as exc:
            raise RuntimeError(f"studizz-api-mailer transport failure on {path}: {exc}") from exc

        try:
            body = response.json()
        except ValueError:
            body = None

        inner_code = (body or {}).get("code", response.status_code) if isinstance(body, dict) else response.status_code
        if inner_code >= 400:
            raise StudizzMailerError(path, response.status_code, body)
        return body if isinstance(body, dict) else {"code": response.status_code}
```

## Reference implementation — `httpx` (async, for FastAPI)

```python
import os
from typing import Any
import httpx


class StudizzMailerClient:
    def __init__(self, base_url: str | None = None, timeout: float = 15.0) -> None:
        url = base_url or os.environ.get("STUDIZZ_API_MAILER_URL", "")
        if not url:
            raise RuntimeError("STUDIZZ_API_MAILER_URL is not configured.")
        self._base_url = url.rstrip("/")
        self._timeout = timeout

    async def send_mail(self, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._post("/mail", payload)

    async def send_sms(self, payload: dict[str, Any]) -> dict[str, Any]:
        return await self._post("/sms/send", payload)

    async def _post(self, path: str, payload: dict[str, Any]) -> dict[str, Any]:
        async with httpx.AsyncClient(timeout=self._timeout) as client:
            response = await client.post(
                f"{self._base_url}{path}",
                json=payload,
                headers={"Accept": "application/json"},
            )

        try:
            body = response.json()
        except ValueError:
            body = None

        inner_code = (body or {}).get("code", response.status_code) if isinstance(body, dict) else response.status_code
        if inner_code >= 400:
            raise RuntimeError(
                f"studizz-api-mailer rejected {path} (http={response.status_code}, code={inner_code}): "
                f"{(body or {}).get('message', '(no message)') if isinstance(body, dict) else body!r}"
            )
        return body if isinstance(body, dict) else {"code": response.status_code}
```

## Usage — mail

```python
from studizz_mailer import StudizzMailerClient

mailer = StudizzMailerClient()

mailer.send_mail({
    "subject": "Welcome to Studizz",
    "htmlPart": "<p>Hi {{var:firstName}}, welcome!</p>",
    "fromEmail": "contact@studizz.fr",
    "fromName": "Studizz",
    "recipients": [
        {"Email": "jane@example.com", "Name": "Jane Doe", "Vars": {"firstName": "Jane"}},
    ],
})
```

## Usage — SMS (OVH)

```python
import os

mailer.send_sms({
    "provider": "ovh",
    "text": "Votre code Studizz : 4821",
    "from": "Studizz",
    "to": ["+33612345678"],
    "credentials": {
        "smsAccount": os.environ["OVH_SMS_ACCOUNT"],
        "login":      os.environ["OVH_SMS_LOGIN"],
        "password":   os.environ["OVH_SMS_PASSWORD"],
    },
})
```

## FastAPI — dependency-injected client

```python
from fastapi import FastAPI, Depends
from studizz_mailer import StudizzMailerClient

app = FastAPI()

def get_mailer() -> StudizzMailerClient:
    return StudizzMailerClient()

@app.post("/notify")
async def notify(email: str, mailer: StudizzMailerClient = Depends(get_mailer)):
    return await mailer.send_mail({
        "subject":   "Welcome",
        "htmlPart":  "<p>Welcome!</p>",
        "fromEmail": "contact@studizz.fr",
        "fromName":  "Studizz",
        "recipients": [{"Email": email}],
    })
```

> If you instantiate the client once at module load instead of per-request, pin the base URL explicitly (`StudizzMailerClient(base_url=settings.STUDIZZ_API_MAILER_URL)`) so it survives env reloads in tests.

## Flask example

```python
from flask import Flask, request, jsonify
from studizz_mailer import StudizzMailerClient

app = Flask(__name__)
mailer = StudizzMailerClient()

@app.post("/notify")
def notify():
    data = request.get_json()
    result = mailer.send_mail({
        "subject":   "Welcome",
        "htmlPart":  f"<p>Hi {data['first_name']}, welcome!</p>",
        "fromEmail": "contact@studizz.fr",
        "fromName":  "Studizz",
        "recipients": [{"Email": data["email"]}],
    })
    return jsonify(result)
```

## `requests` fallback

If the project already standardises on `requests`:

```python
import os
import requests

class StudizzMailerClient:
    def __init__(self, base_url: str | None = None, timeout: float = 15.0) -> None:
        url = base_url or os.environ.get("STUDIZZ_API_MAILER_URL", "")
        if not url:
            raise RuntimeError("STUDIZZ_API_MAILER_URL is not configured.")
        self._base_url = url.rstrip("/")
        self._timeout = timeout

    def send_mail(self, payload): return self._post("/mail", payload)
    def send_sms(self, payload):  return self._post("/sms/send", payload)

    def _post(self, path, payload):
        r = requests.post(
            f"{self._base_url}{path}", json=payload,
            headers={"Accept": "application/json"}, timeout=self._timeout,
        )
        body = None
        try:
            body = r.json()
        except ValueError:
            pass
        inner = (body or {}).get("code", r.status_code) if isinstance(body, dict) else r.status_code
        if inner >= 400:
            raise RuntimeError(f"studizz-api-mailer {path} http={r.status_code} code={inner}: {body}")
        return body or {"code": r.status_code}
```

## Mistakes to avoid

- **Don't use `data=` with `json.dumps(...)`.** Use the library's `json=payload` shortcut so the `Content-Type` header is set correctly. Manually setting `data=` without `Content-Type: application/json` produces a 400 from the gateway.
- **Don't reuse a global `httpx.AsyncClient` opened in module scope** unless you're inside a long-lived ASGI app — it ties the client to the loop that imported it and breaks in tests.
- **Don't retry on inner-code 400.** That's a payload issue.
- **Don't log payloads as-is.** Redact `credentials.password`, `credentials.apiKey`, `credentials.pass`, and any `apiSecret` before logging.
- **Use `body["code"]` for the outcome,** not `response.status_code` — especially for SMS partial failures (HTTP 201 + body code 400).
