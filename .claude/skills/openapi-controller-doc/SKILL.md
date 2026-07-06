---
name: openapi-controller-doc
description: |
  Generates exhaustive, accurate OpenAPI documentation **inside the controllers** of an existing API project — Symfony (Nelmio API Doc Bundle, any version) or FastAPI — by deeply analyzing the source code to recover every parameter, including the ones the original author left implicit (body fields read via `$request->getContent()`, query params with default values, custom headers checked in the middle of the method, file uploads, validation rules expressed as inline `if` checks, etc.).

  Use this skill whenever the user wants to document, audit, complete, fix, generate, or improve API documentation in a project that already has routes — Nelmio annotations, Nelmio PHP attributes, OpenAPI / Swagger, FastAPI decorators, or any "OpenAPI in the controller" setup. Trigger on phrases like: "document this API", "generate Nelmio doc", "add OpenAPI annotations", "complete the Swagger doc", "the Nelmio doc is incomplete / wrong / outdated", "écris la doc OpenAPI / Nelmio / Swagger / FastAPI", "documente cette route / ce controller / ce projet", "fais une vraie doc API", "audit my API documentation", "retrouve les paramètres cachés", "find the body schema", "the request body isn't documented", "this route forwards to another service and I don't know the params". Trigger even when the user names only one route — the skill scales from one route to a whole project.

  Trigger when the project clearly is a Symfony API (composer.json contains `nelmio/api-doc-bundle`, `friendsofsymfony/rest-bundle`, `zircote/swagger-php`, or `symfony/framework-bundle` with controllers exposing JSON) or a FastAPI / Starlette project (pyproject.toml or requirements.txt contains `fastapi`).

  Do NOT trigger for: writing a brand-new API from scratch (no existing routes), generating a separate `openapi.yaml` file as the primary output (this skill writes inline, in the controller), pure client-side OpenAPI consumption (generating a client from a spec), or non-HTTP RPC / GraphQL / gRPC documentation.
---

# OpenAPI Controller Documentation Generator

## What this skill does

Produces **exhaustive, in-controller OpenAPI documentation** for an existing API project. Annotations live next to the route handler (Symfony controller method or FastAPI path operation), never as a standalone `openapi.yaml`.

The skill's defining trait is **deep code analysis**: it reads handler bodies, recursively follows private helpers, and reconstructs parameters that were never formally declared — body fields accessed after `json_decode($request->getContent())`, query params read mid-method with default values, headers checked in helpers, validation expressed as inline `if` checks. Anything a real client must send to call the route gets documented.

## Core rules

- **Improve, don't overwrite.** When existing doc is reasonable, complete the missing pieces. Rewrite only when the existing block is materially wrong (lies about the schema, references parameters that don't exist).
- **The doc must be true.** Better to mark a field `nullable` / `required: false` with a short note than to guess. Document only response codes the code can actually emit.
- **Detect the syntax once, commit to it.** PHP 7 / Nelmio 3 → doctrine annotations. PHP 8 + Nelmio 4 → attributes. Pick before writing the first line.
- **Prose language follows the project.** Identifiers, schema references and enum values stay canonical. Human prose (`summary`, `description`, response descriptions) goes in the project's working language. Even in non-English prose, keep canonical HTTP/REST/OpenAPI vocabulary in English: `body`, `query`, `header`, `path`, `request`, `response`, `payload`, `schema`, `model`, `enum`, `JSON`, `URL`, `UUID`, `ID`, `regex`. Translating those breaks the parallel with the OpenAPI field names a developer consuming the spec is searching for.
- **Reuse the project's type/format constants.** Before writing any `type:` / `format:` value (Nelmio attributes), grep `src/` for constant classes like `App\Model\OAType` / `App\Model\OAFormat`. If they exist, using them is mandatory — never hard-code `'string'`, `'integer'`, `'date-time'`, etc. See `references/nelmio-attributes.md`.
- **Break long attributes across lines.** An attribute (`OA\Property`, `OA\Parameter`, `OA\Response`, …) carrying multiple arguments where one is long — a full-sentence `description`, an `enum`, `items:`, nested `oneOf`/`properties:` — gets one named argument per line, closing `)]` on its own line. Short single-argument attributes stay on one line. Match the file's existing indentation.

## Description style — purpose at the route level, behavior at the field level

The OpenAPI fields where prose lives form a hierarchy. Each level answers a different question, and putting prose at the wrong level is the most common way to make a spec unreadable.

| Field                              | Answers                              | Length         |
|------------------------------------|--------------------------------------|----------------|
| Route `summary`                    | One-line label for menus / lists     | < 80 chars     |
| Route `description`                | What does calling this endpoint do?  | 1-2 sentences  |
| `@SWG\Parameter` / `@OA\Parameter` `description` | What does THIS parameter mean / accept? | as long as it needs |
| `@SWG\Property` / `@OA\Property` `description`   | What does THIS body field mean?       | as long as it needs |
| `@SWG\Response` / `@OA\Response` `description`   | What does THIS status code signify?   | 1 sentence usually   |

The route `description` is **business-level**. It does not name internal classes, methods, or storage mechanics. It does not narrate the handler step by step. It is what a developer surveying the API would read to decide whether this endpoint is the one they need.

Anything specific to one parameter, body field, or response goes on that field, not on the route. The reason: a consumer reading Swagger UI sees the route description first as a one-line caption, then expands a parameter to read its specifics. Burying field-level details in the route prose forces them to scan everything to find one fact.

The deep code analysis described in Phase 2 still happens — but its output goes into the **schema** (`required`, `type`, `enum`, `format`, response shapes) and into **per-field descriptions**, not into a narration of the code on the route.

### Good vs bad

**POST /contacts** — route description

❌ "Crée un contact. Le handler valide via FOSRest @ParamConverter avec le groupe Create (`lastname`, `email`, `source` requis), normalise le nom via `autoCorrectName()`, met `id` à null, défaut `lastActive` / `createdAt` / `updatedAt` à maintenant, défaut `locale` à `fr`, crée une première étape à partir de `source` si `steps` est absent, persiste via MongodbService."

✓ "Crée un contact."

✓ Per-property descriptions in the body schema:
- `id`: "Géré par le serveur. Ignoré si envoyé."
- `createdAt`, `updatedAt`, `lastActive`: "Géré par le serveur. Ignoré si envoyé."
- `locale`: "Code langue ISO. Défaut: `fr`."
- `steps`: "Si absent, une étape est créée automatiquement à partir de `source`."

**POST /contacts/list** — route description

❌ "Liste paginée des contacts. `startDate` plafonné à -3 ans sauf `nolimit=1`. `archived` est un tri-état où -1 désactive le filtre. Le `searchBy` dispatche en interne sur lastActive / lastIncomingActive via un switch dans MongodbService."

✓ "Liste paginée des contacts, filtrable par dates, archivage et critères composables."

✓ Per-parameter descriptions:
- `startDate`: "Borne basse. Plafonné automatiquement à -3 ans sauf si `nolimit=1`."
- `archived`: "`-1` désactive le filtre, `0` retourne les non-archivés (défaut), `1` retourne les archivés."
- `searchBy`: "Champ de date utilisé pour filtrer la fenêtre temporelle."

**GET /contacts/{contactId}** — route description

❌ "Récupère un contact. Si `contactId` contient `@`, recherche par email via une regex Mongo case-insensitive avec `preg_quote`. Sinon, lookup par ID."

✓ "Récupère un contact par ID ou par email."

✓ `contactId` parameter description: "ID du contact, OU email du contact (recherche insensible à la casse si la valeur contient `@`)."

### Things that NEVER appear in any description, at any level

- Internal class or method names: `MongodbService`, `setId(null)`, `autoCorrectName`, `loopFiltersExpr`, `persistDocument`.
- Storage / framework mechanics: `$or` Mongo query, `preg_quote`, MongoDB `$cond` aggregate, FOSRest `@ParamConverter`, `@View(StatusCode=201)`.
- Step-by-step narration of the handler ("the handler first decodes the body, then validates, then persists").

These are debugging aids for the maintainer; they belong in code comments, not in the public API spec.

## Workflow — four phases

```
Phase 0  Detect stack + language     → choose syntax, choose prose language
Phase 1  Scope                       → name the routes to document
Phase 2  Deep analysis + write       → produce annotation blocks in place
Phase 3  Verify + report             → syntax check + summary
```

## Phase 0 — Detect stack + language

**Stack.** Run `scripts/detect_stack.sh` from the project root, or inspect inline:

- Symfony: read `composer.json`.
  - PHP ≥ 8.0 AND `nelmio/api-doc-bundle` ≥ 4.0 → **PHP attributes** (`#[OA\Get(…)]`). Read `references/nelmio-attributes.md`.
  - Anything older (Nelmio 3.x or PHP 7.x) → **doctrine annotations** (`@OA\…` or `@SWG\…` depending on installed `zircote/swagger-php` version — match what the project already uses). Read `references/nelmio-annotations.md`.
  - `friendsofsymfony/rest-bundle` present → FOSRest annotations (`@Rest\Get`, `@Rest\QueryParam`, …) are **complementary**: keep them, add OpenAPI annotations alongside.
- FastAPI: read `pyproject.toml` or `requirements.txt` for `fastapi`. Read `references/fastapi.md`.

If the project doesn't match a supported stack, stop and ask the user.

**Language.** In one sentence, ask the user which language to use for `summary` / `description`, primed with what you saw in existing prose (e.g. *"Quelle langue pour les summary / description ? (j'ai vu surtout du français dans la doc)"*). Apply the answer for the whole pass; don't re-ask per route. Skip the question if the user has already named a language in the request.

## Phase 1 — Scope

Goal: get a clear list of routes to document. Two cases.

**Targeted request** — the user named a specific route, function, or controller (e.g. *"document `getContact` in `ContactController.php`"*, *"document every route of `EmailController`"*). Use that selection directly. Do not re-enumerate the project.

**Project-wide request** — the user said *"document this API"* / *"audit the doc"* / scope unclear. Build the route inventory:
- Symfony: `php bin/console debug:router --format=json` from the project root. If that fails (broken bootstrap, missing DB), grep `src/Controller/` for `@Route(`, `#[Route(`, `@Rest\(Get|Post|Put|Patch|Delete)(`, `@(Get|Post|Put|Patch|Delete)(` — and warn the user the list may be incomplete.
- FastAPI: grep for `@app.(get|post|…)` and `@router.(get|post|…)` across the source tree, following `app.include_router(…, prefix=…)` to compute full paths.

Show the table grouped by controller — columns `method`, `path`, `handler`, `existing doc state` (`none` / `partial` / `complete` / `wrong`). Ask the user which routes to process. Acceptable answers: a list of numbers, "all", a controller name, a path prefix, "the ones marked partial / wrong / none".

## Phase 2 — Deep analysis + write

For each selected route, produce a complete annotation block in the chosen syntax, written in place above the handler.

**Read `references/deep-analysis-patterns.md`** — it lists the hidden-parameter patterns to look for, regardless of framework.

**Depth expectation.** A block that documents only what was already in the signature has not done its job. The skill's value is in tracing dispatch, recovering enums, surfacing discriminator fields hidden in service helpers. If a route's new doc names nothing the old one missed (an enum, an undocumented field, a corrected response shape), the analysis was too shallow — re-read the helper chain.

**Parallelism.** When several routes are selected and they live in different controllers, processing controllers in parallel is the right call: spawn one subagent per controller (target chunk size ≤ 10 routes per subagent — split bigger controllers into contiguous chunks), batch 5 subagents per turn. The brief below works for a single controller; tell each subagent which controller and which routes within it.

When the entire selection lives in one controller, do it inline — spawning a subagent only to write back into the same file the parent already has loaded is overhead with no upside. Routes within the same controller almost always share helpers, DTOs and a response envelope; reading them once and applying a consistent vocabulary across the group is the cheapest path to good output.

**Per-route loop.**
1. Read the full handler body, top to bottom.
2. Recursively read every private helper / service method the handler calls. Hidden parameter access is frequently 1-2 calls deep.
3. Trace every read off the request object — see `references/deep-analysis-patterns.md`.
4. Reconstruct the body schema by listing every key accessed on the decoded payload. Infer types from how each field is used.
5. Detect validation: framework validators, manual `if` checks that throw 400/422, regex / length / format constraints. Each maps to a schema constraint.
6. Detect every response branch: every `JsonResponse(…, code)`, every `raise HTTPException(...)`, every exception the framework converts to an HTTP code. Document them with examples drawn from the code where available.
7. If the handler is a **proxy / gateway** (forwards to another service via `httpClient` / Guzzle / `requests` / `httpx`), follow `references/proxy-gateway-strategy.md` and ask the user for the upstream OpenAPI JSON URL — do not invent the schema.
8. Apply the existing-doc rule: complete and improve what's there, only rewrite if materially wrong.
9. Write the annotation block in place.

**Consistency across a group.** When the same field, response shape, or error envelope appears in several routes of the same controller, document it the same way every time. Inconsistency between routes that clearly share a structure is a bug — pick one form and use it everywhere.

**Subagent brief** (when dispatching one controller per subagent):

```
You are documenting a group of routes in ONE controller of an existing API project. The subagent sees none of this conversation, so the brief is self-contained.

Project root:        <absolute path>
Stack:               <Symfony 4 + Nelmio 3 annotations | Symfony 6 + Nelmio 4 attributes | FastAPI>
Reference:           <absolute path to references/{nelmio-annotations|nelmio-attributes|fastapi}.md>
Patterns reference:  <absolute path to references/deep-analysis-patterns.md>
Controller file:     <relative path>
Prose language:      <fr | en | …>

Target routes (do them all, in this order):
  1. <METHOD> <path>   — <Class>::<method>   (line <N>)
  2. <METHOD> <path>   — <Class>::<method>   (line <N>)
  ...

Pre-pass (read once, apply across all routes):
  a. Read the full controller file end-to-end.
  b. Read every private method of the class — these are shared helpers.
  c. Read the DTOs / entities / form classes referenced.
  d. Note the response envelope used by the controller (raw JSON? wrapper? FOSRest View? HTTPException?). Decide once.

Per-route pass (do this fully for EACH target route — depth applies per route):
  Follow steps 1-9 of Phase 2. If any handler is a proxy, emit a NEEDS_UPSTREAM_DOC marker for that route and continue with the others.

Write the documentation in place above each handler. Return a short report with one block per route (params found, body fields, responses, uncertainty). End with a NEEDS_UPSTREAM_DOC list if any.
```

## Phase 3 — Verify + report

- **Syntax check (mandatory).** Symfony: `php -l <file>` on every modified file. FastAPI: `python -m py_compile <file>`.
- **Spec dump (when feasible).** Nelmio: `php bin/console nelmio:apidoc:dump --format=json`. FastAPI: import the app and call `app.openapi()`. A clean dump is strong evidence the annotations are well-formed.
- **Report.** Per route: what was added, what was completed, what was rewritten, any uncertainty notes. Terse — the user wants the headlines, not the diff.

## Honest doc — pitfalls to remember

- `$data['x'] ?? null` → `required: false`. `$data['x']` with no guard → `required: true`.
- Document only response codes the code emits. A `404` on a route that never throws one is noise.
- Type inference from usage: `intval()` → integer; `Carbon::parse` → date-time string; `count()` → array; string-keyed iteration → object.
- Examples come from the code (hardcoded defaults, literal strings) — not from imagination.
- A `switch` / `match` / `if-elseif` chain dispatching on one value is an enum. Document it as `enum`, not as `type="string"` with a vague description.

## Output

After a pass:
1. Controller files edited in place, doc block above each handler.
2. Per-route report (added / completed / rewritten / uncertain).
3. Syntax-check result.
4. For proxy routes: which upstream OpenAPI spec was used.

Do not produce a side-by-side `openapi.yaml`. The doc lives in the controller; framework tooling (`nelmio:apidoc:dump`, FastAPI's `/openapi.json`) generates the spec from there.
