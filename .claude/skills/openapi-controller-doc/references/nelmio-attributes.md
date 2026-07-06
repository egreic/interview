# Nelmio API Doc — PHP attribute syntax (Nelmio 4+, PHP 8+)

Use this reference when `nelmio/api-doc-bundle` is at version 4.0 or higher AND PHP is 8.0 or higher. The syntax is **PHP attributes** (`#[OA\…]`) directly above the controller method. Cleaner than annotations, type-checked by the IDE, and the canonical style on modern Symfony.

If the project is older (Nelmio 3.x or PHP 7.x), use `nelmio-annotations.md` instead.

## The required namespace import

```php
use OpenApi\Attributes as OA;
```

This is **`Attributes`**, not `Annotations`. Confuse the two and the attributes silently do nothing. Add the import to every controller file you edit; if the file already imports `OpenApi\Annotations as OA`, replace it.

For routing, modern Symfony uses:

```php
use Symfony\Component\Routing\Attribute\Route;   // Symfony 6.4+
// or:
use Symfony\Component\Routing\Annotation\Route;  // older Symfony, still attribute-compatible
```

## Anatomy of a documented method

### A read-only GET with path + query params

```php
#[Route('/contacts/{id}', methods: ['GET'])]
#[OA\Get(
    summary: 'Get a contact by id',
    description: 'Returns a single contact, optionally hydrated with related accounts.',
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Contact id',
            schema: new OA\Schema(type: 'string'),
        ),
        new OA\Parameter(
            name: 'hydrate',
            in: 'query',
            required: false,
            description: 'Comma-separated list of related entities to include',
            schema: new OA\Schema(type: 'string', example: 'accounts,owner'),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'The contact',
            content: new OA\JsonContent(ref: '#/components/schemas/Contact'),
        ),
        new OA\Response(response: 404, description: 'Contact not found'),
    ],
)]
#[OA\Tag(name: 'contacts')]
public function show(string $id, Request $request): JsonResponse { … }
```

Notice: parameters are passed as a PHP array of `new OA\Parameter(...)` instances, not as repeated attribute lines. Same for responses.

### A POST with a JSON body

```php
#[Route('/contacts', methods: ['POST'])]
#[OA\Post(
    summary: 'Create a contact',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['email', 'firstName'],
            properties: [
                new OA\Property(property: 'email',     type: 'string', format: 'email'),
                new OA\Property(property: 'firstName', type: 'string', maxLength: 80),
                new OA\Property(property: 'lastName',  type: 'string', maxLength: 80, nullable: true),
                new OA\Property(property: 'tags',      type: 'array',
                    items: new OA\Items(type: 'string'),
                ),
                new OA\Property(property: 'metadata',  type: 'object', nullable: true,
                    properties: [
                        new OA\Property(property: 'source',     type: 'string'),
                        new OA\Property(property: 'campaignId', type: 'integer'),
                    ],
                ),
            ],
        ),
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Contact created',
            content: new OA\JsonContent(ref: '#/components/schemas/Contact'),
        ),
        new OA\Response(
            response: 400,
            description: 'Validation error',
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'code',    type: 'integer', example: 400),
                    new OA\Property(property: 'message', type: 'string',  example: 'email invalid'),
                ],
            ),
        ),
    ],
)]
public function create(Request $request): JsonResponse { … }
```

### A multipart upload

```php
#[OA\Post(
    summary: 'Upload an avatar',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                required: ['avatar'],
                properties: [
                    new OA\Property(property: 'avatar',  type: 'string', format: 'binary'),
                    new OA\Property(property: 'caption', type: 'string', nullable: true),
                ],
            ),
        ),
    ),
    responses: [new OA\Response(response: 200, description: 'Uploaded')],
)]
```

### A header parameter

```php
new OA\Parameter(
    name: 'X-Tenant-Id',
    in: 'header',
    required: true,
    description: 'Tenant scoping the query',
    schema: new OA\Schema(type: 'string', format: 'uuid'),
),
```

## Reusable schemas on the entity

Same idea as Nelmio 3, with attribute syntax:

```php
#[OA\Schema(
    schema: 'Contact',
    type: 'object',
    properties: [
        new OA\Property(property: 'id',        type: 'string'),
        new OA\Property(property: 'email',     type: 'string', format: 'email'),
        new OA\Property(property: 'firstName', type: 'string'),
        new OA\Property(property: 'lastName',  type: 'string', nullable: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
    ],
)]
class Contact { … }
```

If the project uses Symfony Serializer groups (`#[Groups(['contact:read'])]`), the schema for a route's response must reflect the group used in `$this->json($entity, …, ['groups' => [...]])`. Read the entity, list properties tagged with that group, and document only those.

## Argument resolvers and `#[MapRequestPayload]`

Symfony 6.3+ introduced `#[MapRequestPayload]` and `#[MapQueryString]`, which bind the body or query string to a typed object directly:

```php
public function create(
    #[MapRequestPayload] CreateContactDto $dto,
): JsonResponse { … }
```

When the handler uses these resolvers, the **body schema is the DTO class**. Document it once with `#[OA\Schema]` on the DTO, then reference it from the route:

```php
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(ref: '#/components/schemas/CreateContactDto'),
)]
```

This is by far the cleanest pattern and you should encourage its use when the user is open to small refactors. Do **not** apply the refactor unsolicited — only document what's there.

## Validation constraints from attributes

When the request DTO carries `#[Assert\…]` attributes:

```php
class CreateContactDto {
    public function __construct(
        #[Assert\NotBlank, Assert\Email]
        public string $email,
        #[Assert\Length(min: 1, max: 80)]
        public string $firstName,
        #[Assert\Length(max: 80)]
        public ?string $lastName = null,
    ) {}
}
```

Translate these into the schema attached to the DTO: `NotBlank` → required, `Email` → `format: email`, `Length(max: 80)` → `maxLength: 80`, `Choice(['a','b'])` → `enum: ['a','b']`, `Positive` → `minimum: 1`. Each constraint that can return a 4xx is also a hint that the route emits a `422 Unprocessable Entity` (Symfony's default for failed payload validation) — document that response.

## Project type / format constants

Before writing any `type:` or `format:` value, check whether the project already defines
constants for OpenAPI types and formats — typically classes like `App\Model\OAType` and
`App\Model\OAFormat` holding `const STRING = 'string'`, `const DATETIME = 'date-time'`, etc.
Grep `src/` for `OAType`, `OAFormat`, or any class exposing `'string' | 'integer' | 'object'`
constants.

If such constants exist, they are **mandatory** — never hard-code the string literal. Use the
constant and import the class:

```php
use App\Model\OAType;
use App\Model\OAFormat;

new OA\Property(property: 'email', type: OAType::STRING, format: OAFormat::EMAIL)
new OA\Property(property: 'tags',  type: OAType::ARRAY, items: new OA\Items(type: OAType::STRING))
```

Typical mapping (match the constant names the project actually defines):

| Literal       | Constant            |
|---------------|---------------------|
| `'string'`    | `OAType::STRING`    |
| `'integer'`   | `OAType::INT`       |
| `'number'`    | `OAType::NUMBER`    |
| `'boolean'`   | `OAType::BOOL`      |
| `'array'`     | `OAType::ARRAY`     |
| `'object'`    | `OAType::HASH`      |
| `'date-time'` | `OAFormat::DATETIME`|
| `'email'`     | `OAFormat::EMAIL`   |
| `'float'`     | `OAFormat::FLOAT`   |
| `'double'`    | `OAFormat::DOUBLE`  |

Only add a `use` for a constant class you actually reference in the file. Do **not** invent a
constant that the project doesn't define — if a needed value has no constant, add it to the
constant class if that fits the project, otherwise fall back to the literal and note it. When the
project defines no such constants at all, the plain string literals are fine.

When a property has no `type:` at all because Nelmio infers it from the PHP property type, leave
it inferred — this rule only governs `type:` / `format:` values you actually write.

## Long attributes: one argument per line

When an attribute carries several arguments and at least one is long (a full-sentence
`description`, an `enum`, an `items:`, a nested `oneOf` / `properties:`), break it across lines —
one named argument per line, trailing comma after the last, closing `)]` on its own line:

```php
#[OA\Property(
    description: 'Historique des messages précédents.',
    type: OAType::ARRAY,
    items: new OA\Items(ref: new Model(type: MessageInput::class))
)]
public array $previousMessages = [];
```

Keep short attributes (one argument, or two trivial ones) on a single line:

```php
#[OA\Property(description: 'Marque la conversation comme lue.', example: false)]
public bool $read = false;
```

Rule of thumb: if the single-line form would run past ~100-120 characters, or mixes a long
`description` with other arguments, expand it. Match whatever indentation the file uses (tabs or
spaces). The same applies to `OA\Parameter`, `OA\Response`, `OA\RequestBody`, `OA\Schema` — any
OA attribute with long or multiple arguments.

## Common mistakes the skill must NOT make

- **Importing `OpenApi\Annotations as OA` instead of `OpenApi\Attributes as OA`.** This is the single most common silent failure with Nelmio 4. The attributes parse without errors but produce nothing.
- **Mixing both styles in the same file.** Pick one — attributes — and stay in it.
- **Forgetting that `parameters:` and `responses:` are arrays.** Each entry must be `new OA\Parameter(...)` / `new OA\Response(...)`. Bare property maps don't work.
- **Documenting `#[Security]` / `#[IsGranted]` requirements as request parameters.** They belong in `securitySchemes`, not in `parameters`.
- **Hard-coding `'string'` / `'integer'` / `'date-time'` when the project defines `OAType` / `OAFormat` constants.** Grep for them first; if they exist, use the constant.
- **Cramming a long `description` + `enum` + `items` onto one line.** Multi-argument attributes with a long argument go one-per-line.

## Validating the result

```bash
php bin/console nelmio:apidoc:dump --format=json > /tmp/openapi-check.json
```

If the dump succeeds and your route appears under `paths`, the attributes are valid. Errors in the output identify the offending file and line — fix them before declaring the route done.
