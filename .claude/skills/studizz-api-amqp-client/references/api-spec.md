# studizz-api-amqp — API Specification

Canonical contract for the Studizz AMQP REST gateway. Language-specific references derive from this file; if they conflict, this one wins.

## Base URL

Not fixed. Use a placeholder resolved from the environment:

```
STUDIZZ_API_AMQP_URL=https://<host-to-be-defined>
```

The client must tolerate a trailing slash on the base URL (strip it before appending `/api/...`).

## Authentication

**None.** The API relies on network-level trust. Do not send `Authorization`, `X-Api-Key`, or any other credential header — they will be ignored and their presence hints at a misunderstanding of the deployment model.

## Content type

Requests: `Content-Type: application/json` — required.
Responses: `application/json`.

## Endpoints

### `POST /api/task`

Publishes the request body to the `async-tasks` RabbitMQ exchange. Used for background tasks processed by internal workers.

- **Request body**: any valid JSON object. Shape is defined by the downstream consumer, not by this API.
- **Responses**:
  - `200 OK` → `{"code":200,"message":"ok"}` — message accepted and published.
  - `400 Bad Request` → `{"code":400,"message":"bad parameters"}` — body is empty, `null`, or not valid JSON.

### `POST /api/call`

Publishes the request body to the `async-call` RabbitMQ exchange. Used for asynchronous outbound calls (webhooks, notifications, third-party integrations).

- **Request body**: any valid JSON object. Shape is defined by the downstream consumer.
- **Responses**: same envelope as `/api/task`.

## What the gateway does NOT do

- **No schema validation.** The body is forwarded to RabbitMQ as-is. A malformed-but-parseable JSON body (e.g. missing required fields for the consumer) will return 200 here and then fail silently downstream.
- **No deduplication, no idempotency key.** Calling `POST /api/task` twice with the same body publishes two messages.
- **No retry.** If the broker is down, the call fails with a 5xx — it is the caller's responsibility to decide whether to retry.
- **No response from the consumer.** A 200 means the message was queued, nothing more. Results of the actual work (if any) surface elsewhere.

## OpenAPI / Swagger

A Swagger JSON document is exposed at `GET /api/doc.json`. The Swagger UI route is disabled in the current deployment. The JSON is useful for programmatic inspection but the contract above is small enough to code against directly.

## Status code semantics for clients

| Code    | Client behavior                                                              |
| ------- | ---------------------------------------------------------------------------- |
| 200     | Success. Continue.                                                           |
| 400     | Bug in the caller (empty / invalid JSON). Raise a clear error. Do not retry. |
| 404     | Wrong path / wrong base URL. Raise a config error.                           |
| 5xx     | Gateway or broker outage. Surface the error; let the caller decide on retry. |
| Network | Same treatment as 5xx — transient infra issue.                               |

## Minimal reference request (curl)

```bash
curl -sS -X POST "$STUDIZZ_API_AMQP_URL/api/task" \
  -H 'Content-Type: application/json' \
  -d '{"type":"send_email","to":"user@example.com","template":"welcome"}'
```

Expected response:

```json
{"code":200,"message":"ok"}
```
