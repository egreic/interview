# FastAPI — in-handler documentation patterns

Use this reference when the project's manifest (`pyproject.toml`, `requirements.txt`, or `Pipfile`) declares `fastapi`. FastAPI's documentation lives **on the path operation function itself** and on the Pydantic models it references — exactly the in-controller philosophy this skill enforces.

Unlike Symfony, FastAPI generates most of its OpenAPI spec automatically from type hints and Pydantic models. The job here is to make those type hints and models complete and accurate, not to write hand-rolled annotation blocks. When in doubt, prefer "make the type hints richer" over "add docstrings".

## What "documenting" means in FastAPI

There are five places to put documentation, each with a clear purpose:

1. **Function signature type hints** — declare path/query/header/cookie/body parameters and their types.
2. **Pydantic models** — declare body schemas with field constraints and examples.
3. **Decorator arguments** — `summary`, `description`, `tags`, `response_model`, `status_code`, `responses`.
4. **`Annotated[…, Field(…)]` / `Annotated[…, Query(…)]`** — attach OpenAPI metadata to a parameter without changing its type.
5. **Docstring** — short prose summary used as `description` if the decorator doesn't override it.

Where each one lives matters for OpenAPI output. The skill's job is to put each piece of information in the right place.

## A complete documented endpoint

```python
from typing import Annotated
from fastapi import APIRouter, Header, HTTPException, status
from pydantic import BaseModel, EmailStr, Field

router = APIRouter(prefix="/contacts", tags=["contacts"])


class ContactCreate(BaseModel):
    email: EmailStr
    first_name: str = Field(min_length=1, max_length=80)
    last_name: str | None = Field(default=None, max_length=80)
    tags: list[str] = []
    metadata: dict | None = None


class ContactOut(BaseModel):
    id: str
    email: EmailStr
    first_name: str
    last_name: str | None
    created_at: str


@router.post(
    "",
    summary="Create a contact",
    response_model=ContactOut,
    status_code=status.HTTP_201_CREATED,
    responses={
        400: {"description": "Validation error"},
        409: {"description": "Email already exists"},
    },
)
async def create_contact(
    payload: ContactCreate,
    x_tenant_id: Annotated[str, Header(description="Tenant scoping the request")],
) -> ContactOut:
    """Create a new contact under the given tenant."""
    ...
```

Notice the parts:
- The body schema lives in `ContactCreate`. Field constraints (length, email format, optionality) are expressed once.
- The response shape lives in `ContactOut` plus `response_model=`.
- The success status is on the decorator, the error statuses go in `responses=`.
- The header is declared as a parameter with `Annotated[str, Header(...)]` — that's how it ends up in OpenAPI as a header parameter, not a query parameter.
- The docstring becomes the `description` field. The `summary=` overrides it for the short form.

## Parameter declaration patterns

### Path parameter

```python
@router.get("/{item_id}")
async def read_item(item_id: int):
    ...
```

`item_id: int` is enough. FastAPI infers `in: path`.

### Query parameter

```python
async def list_items(
    page: int = 1,
    limit: Annotated[int, Query(ge=1, le=100)] = 20,
    q: str | None = None,
):
    ...
```

A default value makes the param optional. `Annotated[…, Query(...)]` carries OpenAPI metadata: `ge`, `le`, `min_length`, `max_length`, `regex`, `description`, `example`, `deprecated`.

### Header parameter

```python
async def whoami(
    authorization: Annotated[str, Header()],
    x_tenant_id: Annotated[str, Header(alias="X-Tenant-Id")],
):
    ...
```

By default, FastAPI converts underscores to dashes for header names. Use `alias=` if the actual header name doesn't match the snake-case Python convention.

### Cookie parameter

```python
async def session(session_id: Annotated[str, Cookie()]):
    ...
```

### Body — the right way

Always declare the body as a Pydantic model. Use it as a function parameter:

```python
async def create_thing(payload: ThingCreate):
    ...
```

If multiple body parameters are needed, FastAPI nests them as a single JSON object with each name as a key. Usually you want one model that covers the whole body; document the nested object inside that model.

### Body — the messy way (what the skill must clean up)

```python
async def create_thing(request: Request):
    body = await request.json()
    name = body["name"]
    price = float(body["price"])
```

This bypasses Pydantic. The route exists, the body is required, but FastAPI's auto-generated OpenAPI shows nothing. The skill's deep analysis recovers the schema from `body[…]` accesses (see `deep-analysis-patterns.md`); the cleanest output is to:
1. Build the equivalent Pydantic model.
2. Put it in the file (or in `schemas.py` if the project has one).
3. Replace the `request.json()` access with `payload: NewModel`.

Only do step 3 if the user accepted code refactors. Otherwise, document the body via `Body(..., examples=[…])` and explanatory comments — this is a fallback, not the goal.

### Files

```python
async def upload_avatar(avatar: UploadFile):
    ...
```

`UploadFile` produces a multipart `binary` parameter automatically. For multiple fields:

```python
async def upload(
    avatar: UploadFile,
    caption: Annotated[str | None, Form()] = None,
):
    ...
```

`Form()` switches the body to `application/x-www-form-urlencoded` / `multipart/form-data`.

## Response documentation

Three layers, in this order of preference:

1. **`response_model=ModelOut`** on the decorator — covers the success-path body schema.
2. **`status_code=`** on the decorator — sets the success status (`200` by default).
3. **`responses={code: {"description": …, "model": …}}`** — covers the non-success cases.

Example with everything:

```python
@router.post(
    "/",
    response_model=ContactOut,
    status_code=201,
    responses={
        400: {"description": "Validation error", "model": ErrorResponse},
        409: {"description": "Email already exists", "model": ErrorResponse},
        503: {"description": "Upstream service unavailable"},
    },
)
async def create_contact(payload: ContactCreate) -> ContactOut:
    ...
```

The `model:` key is optional but valuable — it makes the error body discoverable in the spec.

When the handler raises `HTTPException(status_code=N, …)`, document `N` in `responses=`. When it raises any custom exception type that the framework converts to an HTTP code (via an exception handler), trace the handler and document the resulting code.

## Field constraints on Pydantic models

Pydantic v2 `Field(...)` carries everything OpenAPI needs:

```python
class CreateProduct(BaseModel):
    name: str = Field(min_length=1, max_length=120, examples=["Wireless mouse"])
    price: float = Field(gt=0, description="Price in EUR, excluding tax")
    sku: str = Field(pattern=r"^[A-Z0-9-]{4,16}$")
    tags: list[str] = Field(default_factory=list, max_length=10)
    weight_g: int | None = Field(default=None, ge=0, le=100_000)
```

- `min_length` / `max_length`, `ge` / `le` / `gt` / `lt`, `pattern` — all surface as OpenAPI constraints.
- `examples=[…]` is encouraged. Real example values from the code beat invented ones.
- `description=` per field is fine for non-obvious fields; do not write a description for `email: EmailStr`.

Pydantic v1 uses `Field(min_length=…)` similarly but spelled differently (`min_length` was `min_items` for collections, `regex` instead of `pattern`). Detect the version from `pydantic.__version__` or from the imports — adapt accordingly.

## Tags and grouping

```python
router = APIRouter(prefix="/contacts", tags=["contacts"])
```

Tags on the router apply to every route; tags on the decorator (`@router.get(..., tags=["search"])`) add to them. The skill should set tags at the **router level** when the whole module covers one resource, and at the **decorator level** for individual exceptions.

## Common mistakes the skill must NOT make

- **Forgetting `response_model=` on a route that returns a Pydantic model.** Without it, FastAPI returns the model fine but OpenAPI shows no response schema.
- **Returning a `dict` and documenting it as `response_model=SomeModel`.** The validation will silently strip fields the model doesn't know about. If the route really returns ad-hoc JSON, document via `responses={200: {"content": {"application/json": {"schema": …}}}}` instead, or refactor to a model.
- **Using `Header(...)` without `Annotated`.** `Annotated[str, Header()]` is the modern way; the bare `Header(...)` form has subtleties that hide parameters in OpenAPI under some versions.
- **Documenting framework-injected dependencies as parameters.** A `Depends(get_current_user)` is not a request parameter; it produces no OpenAPI input. Skip it. Headers / query params it reads internally are still inputs, though — document them on the route or in `dependencies=` notes.
- **Inventing response codes.** Only document codes the code can emit (a `return`, a `raise HTTPException(...)`, a known exception handler).

## Validating the result

The fastest sanity check is to import the app and dump the spec:

```python
# In a quick scratch script or a REPL:
from app.main import app
import json; print(json.dumps(app.openapi(), indent=2))
```

A clean dump that contains the route under `paths` is strong evidence the documentation is well-formed. If the project runs locally, hitting `GET /openapi.json` is equivalent.

`python -m py_compile <file>` catches plain syntax errors after edits.
