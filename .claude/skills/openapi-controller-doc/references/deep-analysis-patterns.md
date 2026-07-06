# Deep analysis patterns — finding hidden parameters

A "hidden parameter" is anything a real client must (or can) send that is not declared in the route signature or framework binding. The whole point of this skill is to recover them by reading the handler body — and the helpers it calls.

## How to read a handler

1. **Read the full handler body**, top to bottom.
2. **Recurse into every helper / service method** the handler calls. Hidden parameter access is frequently 1-2 calls deep (`$this->buildFilters($request)`, `_extract_pagination(request)`).
3. **Track variable renames.** `$payload = json_decode($request->getContent(), true); $input = $payload['data'];` — accesses on `$input` are accesses on a sub-tree of the body. Follow the rename.

Only after this should you start cataloguing parameters.

## Symfony / PHP — what each pattern means

### Body via `json_decode($request->getContent(), true)`
Each key accessed on the decoded array (`$data['x']`) is a body field. Optionality:
- `$data['x'] ?? default` or `isset($data['x'])` guard → `required: false` (default visible)
- `$data['x']` with no guard → `required: true`

Type inference from usage:
- `intval()`, arithmetic, `count(other)` → `integer`
- `floatval()`, money math → `number`
- `Carbon::parse`, `new \DateTime` → `string` with `format: date-time`
- `foreach ($data['x'] as $...)` → `array`
- string-keyed iteration → `object` (recurse the analysis)
- `filter_var(..., FILTER_VALIDATE_EMAIL)` → `string` with `format: email`

### Body via `$request->request->get('x')`
Body is `application/x-www-form-urlencoded` or `multipart/form-data`, not JSON. Same field-by-field analysis.

### Query / headers / files
- `$request->query->get('page', 1)` — query param `page`, optional, default `1`. `getInt` / `getBoolean` give the type for free.
- `$request->headers->get('X-Tenant-Id')` — header parameter (skip Authorization unless its value is used in the method's logic; that one belongs in `securitySchemes`).
- `$request->files->get('avatar')` — multipart field, `type=string, format=binary`.

### Enum extraction (high-value)
Whenever the handler or any helper dispatches on a value, that is an enum — document it as `enum`, NOT as `type="string"` with a vague description.

```php
switch ($searchBy) {
    case 'createdAt':           …; break;
    case 'lastIncomingActive':  …; break;
    default:                    $field = 'lastActive';
}
```
→ `searchBy` is enum `{"createdAt", "lastIncomingActive", …}`, default `"lastActive"`.

Same logic applies to `if/elseif` chains, `in_array($x, [...])`, `array_key_exists($x, [...])`, and PHP 8 `match`.

**Operator-style fields are almost always enums.** A field named `operator`, `mode`, `type`, `kind`, `direction`, `status`, `state`, `category` is a strong signal. Find the dispatch and list the values.

**Recurse into helpers.** The dispatch may not be in the handler — open `$this->mongodb->getContactsList(...)` and look there.

### Discriminator fields hidden in service helpers
Filter / segment / criteria objects passed to helpers often carry fields the handler never touches:
- `group: "personal" | "network"` — chosen by an `if` in the helper.
- `mode: "intersection" | "union"` — alters join logic.
- `cascade: bool` — toggles deep traversal.

These are real input fields. Read the helper, document them.

### Validation
- Inline checks: `if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new BadRequestHttpException(...)` → required + `format: email` + 400 response.
- `if (strlen($data['password']) < 8)` → `minLength: 8`.
- Validator service / Doctrine `@Assert\…` on entity properties: translate constraints to OpenAPI. `NotBlank` → required; `Length(min=, max=)` → `minLength` / `maxLength`; `Email` → `format: email`; `Choice([...])` → `enum`; `Type('integer')` → `type: integer`.
- Validation groups (`groups={"Create"}`) — open the entity and only surface the constraints in the active group for the route.

### Response branches
- `return new JsonResponse([...], 201)` → 201 with that schema.
- `return $this->json($entity, 200, [], ['groups' => ['contact:read']])` → 200, schema = entity fields tagged with `contact:read`.
- `throw new NotFoundHttpException()` → 404. `BadRequestHttpException` → 400. `AccessDeniedException` → 403.
- Custom exceptions mapped via `fos_rest.yaml` `exception.codes` or a listener: open the config, find the HTTP code; document the actual envelope (`{code, message}` is the FOSRest default).

### Helpers that wrap response building
`return $this->errorResponse('NOT_FOUND', 404)` — open `errorResponse()`, document the actual `{code, message}` shape it returns.

## FastAPI — quick patterns

- Already-typed signature (`def f(item_id: int, q: str | None = None)`) → mostly complete, just verify the response.
- Body without a Pydantic model: scan `payload[...]` accesses on `await request.json()` like the Symfony case.
- `request.headers.get(...)` / `.cookies.get(...)` / `.query_params.get(...)` → header / cookie / query parameter.
- Pydantic `Field(min_length=, gt=, …)` constraints map directly to OpenAPI.
- `response_model=` and `status_code=` cover the success case; `raise HTTPException(404, ...)` calls cover error cases — add them to `responses={404: {"description": "..."}}`.

## Traps to avoid

- **Late default insertion.** `$data['status'] = $data['status'] ?? 'pending';` — looks like a write, actually documents that `status` is optional with default `'pending'`.
- **Authentication objects.** `$this->getUser()` is not a request parameter. The Authorization header may still be worth a project-level `securitySchemes` entry.
- **Listener / subscriber / `Depends(...)` magic.** A param injected before the handler runs is still a real input. If a listener reads a header and rejects without it, that header is required.
- **Spreading helpers.** `$filters = $this->parseFilters($request);` — open `parseFilters`, the params live there.
