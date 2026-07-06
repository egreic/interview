# Nelmio API Doc — doctrine annotations (Nelmio 3.x or any PHP 7.x project)

Doctrine-style annotations inside `/** */` doc-blocks placed immediately above the controller method. Use this reference when `nelmio/api-doc-bundle` is 3.x, OR PHP is below 8.0 (attributes don't work). For Nelmio 4 + PHP 8 → switch to `nelmio-attributes.md`.

## Two namespaces — match what the project already uses

The `zircote/swagger-php` library exposes two namespaces. Detect which is imported in the existing controllers and follow it:

- `use OpenApi\Annotations as OA;` → write `@OA\Get`, `@OA\Parameter`, `@OA\Response`, `@OA\JsonContent`, `@OA\Property` (this is the OpenAPI 3 style).
- `use Swagger\Annotations as SWG;` → write `@SWG\Get`, `@SWG\Parameter`, `@SWG\Response`, `@SWG\Schema`, `@SWG\Property` (this is the older Swagger 2 style; common in legacy Nelmio 3 projects).

When the project uses Nelmio 3 with FOSRest, the convention is to put the OpenAPI annotations inside a Nelmio `@Operation(...)` wrapper:

```php
use Nelmio\ApiDocBundle\Annotation\Operation;
use Nelmio\ApiDocBundle\Annotation\Model;
use Swagger\Annotations as SWG;

/**
 * @Rest\Get("/contacts/{id}")
 *
 * @Operation(
 *     tags={"contacts"},
 *     summary="Get a contact",
 *     @SWG\Parameter(name="id", in="path", required=true, type="string"),
 *     @SWG\Response(
 *         response=200,
 *         description="The contact",
 *         @Model(type=Contact::class)
 *     ),
 *     @SWG\Response(response=404, description="Contact not found")
 * )
 */
```

If imports are missing for the annotations you write, add them.

## Canonical examples

### GET — path + query params + 200 / 404

```php
/**
 * @OA\Get(
 *     summary="Get a contact",
 *     description="Returns a single contact, optionally hydrated with related accounts.",
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(
 *         name="hydrate", in="query", required=false,
 *         description="Comma-separated list of related entities",
 *         @OA\Schema(type="string", example="accounts,owner")
 *     ),
 *     @OA\Response(
 *         response=200, description="The contact",
 *         @OA\JsonContent(ref="#/components/schemas/Contact")
 *     ),
 *     @OA\Response(response=404, description="Contact not found")
 * )
 */
```

### POST — JSON body with required fields and a 400

```php
/**
 * @OA\Post(
 *     summary="Create a contact",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"email", "firstName"},
 *             @OA\Property(property="email",     type="string", format="email"),
 *             @OA\Property(property="firstName", type="string", maxLength=80),
 *             @OA\Property(property="tags",      type="array", @OA\Items(type="string")),
 *             @OA\Property(property="metadata",  type="object", nullable=true,
 *                 @OA\Property(property="source",     type="string"),
 *                 @OA\Property(property="campaignId", type="integer")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Created",
 *         @OA\JsonContent(ref="#/components/schemas/Contact")
 *     ),
 *     @OA\Response(response=400, description="Validation error",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="code",    type="integer", example=400),
 *             @OA\Property(property="message", type="string")
 *         )
 *     )
 * )
 */
```

### Multipart upload

```php
/**
 * @OA\Post(
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"avatar"},
 *                 @OA\Property(property="avatar",  type="string", format="binary"),
 *                 @OA\Property(property="caption", type="string", nullable=true)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Uploaded")
 * )
 */
```

### Header parameter

```php
* @OA\Parameter(
*     name="X-Tenant-Id", in="header", required=true,
*     @OA\Schema(type="string", format="uuid")
* )
```

## Reusable schemas — define once on the entity

```php
// In src/Entity/Contact.php
/**
 * @OA\Schema(
 *     schema="Contact", type="object",
 *     @OA\Property(property="id",    type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="firstName", type="string")
 * )
 */
class Contact { … }
```

Then reference: `@OA\JsonContent(ref="#/components/schemas/Contact")` (OA) or `@Model(type=Contact::class)` (Nelmio).

If the project uses serialization groups (`@Groups({"contact:read"})`), the schema should reflect only the fields exposed by the group used in the route. Check `$this->json($entity, …, ['groups' => […]])`.

## FOSRest interplay

`@Rest\QueryParam` / `@Rest\RequestParam` drive runtime binding — keep them. Add `@OA\Parameter` / `@OA\RequestBody` next to them for the spec.

```php
/**
 * @Rest\QueryParam(name="page",  default="1",  requirements="\d+")
 * @Rest\QueryParam(name="limit", default="20", requirements="\d+")
 *
 * @OA\Get(
 *     @OA\Parameter(name="page",  in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20))
 * )
 */
```

The duplication is the price of having FOSRest do binding while OpenAPI describes the spec — Nelmio 3 cannot bridge them automatically.

## Pitfalls

- `@OA\Response` → `@OA\JsonContent` → properties is the canonical chain. Don't put `@OA\Schema` directly under `@OA\Response`.
- Path params are always `required=true`. Forgetting it makes Nelmio's dump fail validation.
- `#[OA\…]` attribute syntax does nothing on PHP 7 / Nelmio 3. Stay in `/** */`.
- Project-wide auth (Authorization header) belongs in `securitySchemes`, not on every route.
