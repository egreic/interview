# Studizz API Mailer — Endpoint Spec

This file is the canonical contract for the two endpoints this skill targets. The language references (`php.md`, `javascript.md`, `python.md`) derive from this — if they ever look like they disagree, this spec wins.

## Base URL

Configured via `STUDIZZ_API_MAILER_URL` (canonical env var across all Studizz projects). Example: `https://api-mailer.studizz.internal`. No path prefix — endpoints are mounted directly under the host.

No authentication header. The gateway is internal.

## Common request rules

- `Content-Type: application/json` is **required** on both endpoints.
- Body must be a valid JSON object — empty / non-JSON bodies yield 400.
- Responses are always JSON. Always check **`response.body.code`** as the authoritative outcome, not just the HTTP status (some endpoints return 201 even when the inner `code` is 400).

---

## 1. `POST /mail` — send transactional email

### Provider routing

Selected via the body field `provider`:

| `provider`  | Backend                  | Notes |
| ----------- | ------------------------ | ----- |
| `mailjet`   | Mailjet Send API v3.1    | Default if `provider` is omitted. `bcc` / `cc` from the DTO are folded into Mailjet's `Recipients` list — they are NOT real SMTP Bcc. |
| `aws`       | AWS SES v2 SendEmail     | Per-recipient variables are substituted in `htmlPart` / `textPart` via the `[[var:name]]` or `[[var:name:"default"]]` syntax. |

### Request body

| Field         | Type    | Required | Description |
| ------------- | ------- | -------- | ----------- |
| `provider`    | string  | no       | `mailjet` (default) or `aws`. |
| `subject`     | string  | **yes**  | Email subject. |
| `htmlPart`    | string  | **yes**  | HTML body. May contain `[[var:name]]` placeholders (AWS) or Mailjet variable syntax. |
| `textPart`    | string  | no       | Plain-text body. Falls back to `htmlPart` server-side if omitted. |
| `fromEmail`   | string  | **yes**  | Sender email. Must belong to a sender/domain verified at the provider. |
| `fromName`    | string  | **yes**  | Display name for the sender. |
| `recipients`  | array   | **yes**  | List of `{Email, Name, Vars}` objects. `Vars` is an object of per-recipient template variables. |
| `replyTo`     | string  | no       | Reply-To address. |
| `attachments` | array   | no       | List of `{Filename, "Content-type", content}` objects. `content` is base64. |
| `bcc`         | array   | no       | List of `{Email, Name}` objects (folded into Recipients on Mailjet). |
| `cc`          | array   | no       | List of `{Email, Name}` objects. |
| `vars`        | object  | no       | Global variables shared by all recipients. |

### Successful response (200)

```json
{
  "status": 200,
  "code": 200,
  "message": "OK",
  "messageID": "...provider message id...",
  "MessageUUID": "...mailjet uuid or empty..."
}
```

- `messageID` is the Mailjet `MessageID` or the AWS `MessageId`.
- `MessageUUID` is Mailjet-only; AWS returns the same value as `messageID`.

### Failure response

Same shape with `status` / `code` set to a 4xx or 5xx value and a `message` string. AWS responses may add `error` and `requestId`. Read `body.code` for the real outcome — HTTP 200 with `body.code: 400` is possible on Mailjet failures.

### Minimal example body

```json
{
  "provider": "mailjet",
  "subject": "Welcome to Studizz",
  "htmlPart": "<p>Hi {{var:firstName}}, welcome!</p>",
  "fromEmail": "contact@studizz.fr",
  "fromName": "Studizz",
  "recipients": [
    { "Email": "user@example.com", "Name": "Jane Doe", "Vars": { "firstName": "Jane" } }
  ]
}
```

---

## 2. `POST /sms/send` — send SMS

### Provider routing

Selected via the body field `provider`:

| `provider`    | Backend                   | Notes |
| ------------- | ------------------------- | ----- |
| `ovh`         | OVH HTTP (`http2sms.cgi`) | If `credentials.smsAccount` starts with `smpp`, the gateway switches to SMPP transport with GSM 03.38 encoding. |
| `primotexto`  | Primotexto                | Uses `X-Primotexto-ApiKey` header internally. |
| `123sms`      | 123SMS                    | |

### Request body

| Field        | Type    | Required | Description |
| ------------ | ------- | -------- | ----------- |
| `provider`   | string  | **yes**  | `ovh`, `primotexto`, or `123sms`. |
| `text`       | string  | **yes**  | SMS body. OVH SMPP encodes as GSM 03.38; HTTP passes through (`smsCoding=1`). |
| `from`       | string  | **yes**  | Sender ID. Alphanumeric, ≤ 11 chars for OVH. Must be a validated Sender ID on Primotexto / 123SMS. |
| `to`         | array   | **yes**  | List of recipient phone numbers in E.164 format (`+33612345678`). Whitespace is stripped server-side. |
| `campaignId` | string  | no       | Tag/category for the message. Defaults to `studizz-crm` on Primotexto. |
| `needStop`   | boolean | no       | If `true`, indicates the STOP mention is NOT required (OVH `noStop=0`). Default `false` (STOP mention added). |
| `credentials`| object  | **yes**  | Provider-specific credentials — see below. |

### `credentials` shape per provider

**OVH HTTP** (most common):
```json
{
  "smsAccount": "sms-xxx-1",
  "login":      "ovh-user-login",
  "password":   "ovh-user-password"
}
```

**OVH SMPP** (prefix the `smsAccount` with `smpp`):
```json
{
  "smsAccount": "smpp-sms-xxx-1",
  "login":      "ovh-user-login",
  "password":   "ovh-user-password"
}
```

**Primotexto**:
```json
{
  "apiKey": "primotexto-api-key"
}
```

**123SMS**:
```json
{
  "email": "account@example.com",
  "pass":  "account-password",
  "from":  "validated-sender-id"
}
```

### Successful response (201)

```json
{
  "code": 200,
  "message": "ok",
  "responses": [
    { "code": 200, "message": "..." }
  ],
  "invalids": [],
  "errors": []
}
```

- **HTTP status is 201** even when the body's `code` is 400 (one or more recipients rejected). Always read `body.code` for the aggregate outcome.
- `responses[]` is per-recipient (OVH HTTP, 123SMS).
- `invalids[]` lists rejected numbers.
- `errors[]` (Primotexto) lists per-recipient error objects `{code, message, recipient}`.

### Minimal example body

```json
{
  "provider": "ovh",
  "text": "Hello from Studizz",
  "from": "Studizz",
  "to": ["+33612345678"],
  "credentials": {
    "smsAccount": "sms-xxx-1",
    "login": "ovh-login",
    "password": "ovh-password"
  }
}
```

---

## Defensive client patterns

These patterns apply to all language references:

1. **Trim the base URL.** Strip a trailing slash so `https://host` and `https://host/` both work. Build URLs as `${base}/mail`, not `${base}mail`.
2. **Set the JSON content type explicitly.** Some HTTP libraries default to `application/x-www-form-urlencoded` if you pass a form-encodable object — that yields a 400.
3. **Read `body.code` for the outcome.** Don't trust the HTTP status alone for SMS (always 201) and don't trust it alone for mail (Mailjet wraps errors in 200).
4. **Surface the full body on non-2xx HTTP, or on non-200 inner code.** Errors here are debugged by humans looking at provider messages — never collapse them into a generic "send failed".
5. **Don't log credentials.** When logging the request body, redact `credentials.password`, `credentials.apiKey`, `credentials.pass`, and any `apiSecret`.
