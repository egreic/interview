# Proxy / gateway routes — how to document what isn't local

A proxy or gateway route is one whose handler doesn't actually own the data shape. It receives a request, possibly tweaks it, and forwards it to another HTTP service via Guzzle, Symfony HttpClient, `requests`, `httpx`, `fetch`, etc. The response comes back from that other service, often passed through unchanged.

For these routes, deep analysis of the local code reveals **almost nothing useful** about the parameters: the body is read with `getContent()` and forwarded raw, the query string is forwarded raw, the response is whatever the upstream sent. Documenting only what the local code does would produce something like "body: any object" — true but useless.

The right strategy is to **bring the upstream OpenAPI spec into the loop** and use it as the source of truth for the parameters and responses, then write the resulting documentation in the local controller using the local syntax.

## How to recognise a proxy / gateway route

Strong signals, any one is enough:

- The handler builds a URL on another host and calls `httpClient->request(...)`, `Http::get(...)`, `Guzzle\Client::send(...)`, `requests.get(...)`, `httpx.AsyncClient.get(...)`, `fetch(...)`, etc.
- The handler immediately returns the upstream response body (`return new JsonResponse($response->toArray())`, `return JSONResponse(content=resp.json())`).
- The local code never accesses individual fields of the body — it forwards `$request->getContent()` or equivalent unchanged.
- The route name / path mirrors a route that exists in another known service (e.g. local `GET /api/contacts/{id}` → upstream `GET /contacts/{id}` on a CRM service).

A handler that calls another service to enrich a response but otherwise builds its own data shape is **not** a pure proxy — document it locally, normally. The proxy strategy applies only when the local code is mostly a forwarder.

## Procedure

When a subagent identifies a proxy route, it must NOT invent the schema. Instead:

1. **Stop and emit the marker.** The subagent ends its run with a short report including a structured marker the main agent can detect:

   ```
   NEEDS_UPSTREAM_DOC
   route: GET /api/contacts/{id}
   inferred_upstream_call: GET {CRM_BASE_URL}/contacts/{id}
   reason: handler forwards $request->getContent() to crmClient and returns the response unchanged
   ```

2. **Main agent asks the user.** The main agent (which can talk to the user) requests the upstream OpenAPI JSON URL:

   > This route forwards to another service. Please give me the URL of the upstream service's OpenAPI JSON (e.g. `https://crm.example.com/api/doc.json` for Nelmio, or `https://crm.example.com/openapi.json` for FastAPI). I'll fetch it and use the upstream spec to document the parameters here.

3. **Fetch and parse the upstream spec.** Use `WebFetch` (or `curl` + parse) to retrieve the JSON. Locate the matching path + method:
   - Match on path **after** stripping the local prefix. Local `/api/contacts/{id}` likely maps to upstream `/contacts/{id}` — confirm by reading the upstream URL pattern in the local code.
   - Match the HTTP method exactly.
   - If multiple candidates exist, ask the user which upstream path is correct rather than guessing.

4. **Translate the upstream spec into the local syntax.** This is mechanical:
   - Every upstream `parameters` entry → a `@OA\Parameter` annotation / `#[OA\Parameter]` attribute / FastAPI signature parameter.
   - Upstream `requestBody` → local `@OA\RequestBody` / `#[OA\RequestBody]` / Pydantic model.
   - Upstream `responses` → local `@OA\Response` / `#[OA\Response]` / `responses={...}` mapping.
   - Upstream `components/schemas` referenced by the route → either reproduce the schema inline locally, or — if the user wants reuse — define it once in the local `components/schemas` (on a DTO or entity class) and `ref` it.

5. **Augment with locally-added behaviour.** A proxy is rarely a perfect pass-through. The local handler may:
   - Inject extra headers (e.g. `X-Tenant-Id`) before calling upstream → document those locally even if the upstream spec doesn't mention them.
   - Strip / redact fields from the upstream response → adjust the response schema accordingly.
   - Add its own validation (e.g. tenant scoping) and emit local error codes (403, 404 for cross-tenant access) → add those response entries.
   The local doc must reflect what the **local route** accepts and returns, which is upstream ± local modifications.

6. **Write a short note about the upstream source.** In the doc, add a one-liner so future maintainers know where the schema came from:

   ```
   description="… Schema mirrors GET /contacts/{id} on the CRM service (source: https://crm.example.com/api/doc.json, fetched 2026-05-06)."
   ```

   This is not noise — it's the only thing that lets someone fix drift later. Drift is inevitable; signposting helps.

## When the user can't provide the upstream JSON URL

Sometimes the upstream service is undocumented or unreachable. Two fallbacks:

- **Manual schema input.** Ask the user to paste a sample request and a sample response. Reconstruct the schema from those, mark it as "based on examples, not on a formal spec" in the description.
- **Skip the route, document only what's local.** Last resort. Document the path, method, and any locally-injected headers / response codes, with a description noting that the body schema is forwarded as-is to an undocumented upstream and is not validated locally. Do not invent fields.

Never invent fields. A proxy doc that lists `email`, `firstName`, `lastName` because those are common contact fields — when the local code never confirms them — is worse than no doc.

## Worked example

Local handler in a Symfony 4 + Nelmio 3 gateway project:

```php
public function getContact(string $id, Request $request): JsonResponse
{
    $tenantId = $request->headers->get('X-Tenant-Id');
    if (!$tenantId) {
        throw new BadRequestHttpException('missing tenant');
    }
    $upstream = $this->crmClient->request('GET', '/contacts/' . $id, [
        'headers' => ['X-Tenant-Id' => $tenantId],
    ]);
    if ($upstream->getStatusCode() === 404) {
        throw new NotFoundHttpException();
    }
    return new JsonResponse($upstream->toArray(), 200);
}
```

After the subagent flags `NEEDS_UPSTREAM_DOC`, the user provides `https://crm.internal/api/doc.json`. The main agent fetches it, finds `GET /contacts/{id}`, and sees the upstream defines an `id` path param (string) and a response schema referencing `Contact`. The local doc becomes:

```php
/**
 * @OA\Get(
 *     summary="Get a contact by id (gateway)",
 *     description="Forwards to the CRM service. Schema mirrors GET /contacts/{id} on the CRM (source: https://crm.internal/api/doc.json, fetched 2026-05-06).",
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="X-Tenant-Id", in="header", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="The contact",
 *         @OA\JsonContent(ref="#/components/schemas/Contact")
 *     ),
 *     @OA\Response(response=400, description="Missing tenant"),
 *     @OA\Response(response=404, description="Contact not found")
 * )
 */
public function getContact(string $id, Request $request): JsonResponse { … }
```

Note what was added beyond the upstream spec: the `X-Tenant-Id` header (locally enforced) and the `400` response (locally raised). The `404` came from both upstream and local short-circuiting; one entry covers both.

The `Contact` schema reference assumes the entity exists locally with `@OA\Schema(schema="Contact", …)`. If it doesn't, copy the upstream's `components/schemas/Contact` definition into a local file (e.g. `src/Doc/Schemas.php` with a class carrying `@OA\Schema` annotations) and reference it. Mention the upstream origin in the schema description too.
