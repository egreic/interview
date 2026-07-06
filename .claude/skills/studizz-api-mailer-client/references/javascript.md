# JavaScript / TypeScript client for studizz-api-mailer

Read `api-spec.md` first. This file covers Node, React, and Next.js usage.

## Where can this code safely live?

**Sending mail or SMS must happen server-side.** The payload contains provider credentials (OVH login/password, Primotexto API key, etc.) and the API has no per-user auth — exposing either to the browser leaks credentials and lets any visitor send mail / SMS on your dime.

- ✅ Node scripts, plain Express/Fastify routes, Next.js **Route Handlers** / API routes / Server Actions / `getServerSideProps`, NestJS controllers, Worker / cron processes.
- ❌ React components, Vite browser bundles, anything imported by client-side code, anything that reads `NEXT_PUBLIC_*` env vars.

If a React/Next.js component needs to trigger a send, the pattern is: the component calls your own backend route, your backend route calls `studizz-api-mailer`. Never proxy directly from the browser.

## Which HTTP client?

1. **Native `fetch`** — best default. Available in Node ≥ 18, all browsers (but you should not be in a browser, see above). Zero dependencies.
2. **`axios`** — use it if the project already depends on it. No reason to add it just for this skill.
3. **`undici`** — for performance-critical Node code that already uses it.

If `package.json` has axios, use axios. Otherwise use `fetch`.

## Configuration

```
# .env (Node)  or  .env.local (Next.js)
STUDIZZ_API_MAILER_URL=https://<mailer-host>

# only if sending SMS via OVH
OVH_SMS_ACCOUNT=sms-xxx-1
OVH_SMS_LOGIN=ovh-user-login
OVH_SMS_PASSWORD=ovh-user-password
```

**Do not prefix with `NEXT_PUBLIC_`** — those are exposed to the browser. The mailer URL and provider credentials are server-only.

Load with `dotenv` in plain Node, or rely on Next.js's automatic `.env.local` loading. In Vite/Astro server-only modules use `import.meta.env` for server-only env (or `process.env` in Astro endpoints).

## Reference implementation — TypeScript + native `fetch`

File: `src/clients/studizz-mailer.ts`

```ts
type MailRecipient = {
  Email: string;
  Name?: string;
  Vars?: Record<string, string | number>;
};

export type MailPayload = {
  provider?: 'mailjet' | 'aws';
  subject: string;
  htmlPart: string;
  textPart?: string;
  fromEmail: string;
  fromName: string;
  recipients: MailRecipient[];
  replyTo?: string;
  attachments?: Array<{ Filename: string; 'Content-type': string; content: string }>;
  bcc?: Array<{ Email: string; Name?: string }>;
  cc?: Array<{ Email: string; Name?: string }>;
  vars?: Record<string, unknown>;
};

export type SmsCredentialsOvh =       { smsAccount: string; login: string; password: string };
export type SmsCredentialsPrimotexto = { apiKey: string };
export type SmsCredentials123Sms =    { email: string; pass: string; from: string };

export type SmsPayload =
  | {
      provider: 'ovh';
      text: string;
      from: string;
      to: string[];
      credentials: SmsCredentialsOvh;
      campaignId?: string;
      needStop?: boolean;
    }
  | {
      provider: 'primotexto';
      text: string;
      from: string;
      to: string[];
      credentials: SmsCredentialsPrimotexto;
      campaignId?: string;
      needStop?: boolean;
    }
  | {
      provider: '123sms';
      text: string;
      from: string;
      to: string[];
      credentials: SmsCredentials123Sms;
      campaignId?: string;
      needStop?: boolean;
    };

export type MailerResponse = {
  code: number;
  message?: string;
  messageID?: string;
  MessageUUID?: string;
  responses?: Array<{ code: number; message: string }>;
  invalids?: string[];
  errors?: Array<{ code: number; message: string; recipient: string }>;
  [key: string]: unknown;
};

export class StudizzMailerError extends Error {
  constructor(
    public readonly path: string,
    public readonly httpStatus: number,
    public readonly body: unknown,
  ) {
    const code = (body as MailerResponse | null)?.code ?? httpStatus;
    const message = (body as MailerResponse | null)?.message ?? '(no message)';
    super(`studizz-api-mailer rejected ${path} (http=${httpStatus}, code=${code}): ${message}`);
    this.name = 'StudizzMailerError';
  }
}

export class StudizzMailerClient {
  private readonly baseUrl: string;

  constructor(baseUrl: string = process.env.STUDIZZ_API_MAILER_URL ?? '') {
    if (!baseUrl) {
      throw new Error('STUDIZZ_API_MAILER_URL is not configured.');
    }
    this.baseUrl = baseUrl.replace(/\/+$/, '');
  }

  sendMail(payload: MailPayload): Promise<MailerResponse> {
    return this.post('/mail', payload);
  }

  sendSms(payload: SmsPayload): Promise<MailerResponse> {
    return this.post('/sms/send', payload);
  }

  private async post(path: string, payload: unknown): Promise<MailerResponse> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const body = (await response.json().catch(() => null)) as MailerResponse | null;

    // Trust body.code over HTTP status — the gateway sometimes returns 200/201
    // with an inner error code (e.g. SMS partial-failure → http=201, body.code=400).
    const innerCode = body?.code ?? response.status;
    if (innerCode >= 400) {
      throw new StudizzMailerError(path, response.status, body);
    }
    return body ?? ({ code: response.status } as MailerResponse);
  }
}
```

## Usage — mail

```ts
import { StudizzMailerClient } from './clients/studizz-mailer';

const mailer = new StudizzMailerClient();

await mailer.sendMail({
  subject: 'Welcome to Studizz',
  htmlPart: '<p>Hi {{var:firstName}}, welcome!</p>',
  fromEmail: 'contact@studizz.fr',
  fromName: 'Studizz',
  recipients: [
    { Email: 'jane@example.com', Name: 'Jane Doe', Vars: { firstName: 'Jane' } },
  ],
});
```

## Usage — SMS

```ts
await mailer.sendSms({
  provider: 'ovh',
  text: 'Votre code Studizz : 4821',
  from: 'Studizz',
  to: ['+33612345678'],
  credentials: {
    smsAccount: process.env.OVH_SMS_ACCOUNT!,
    login: process.env.OVH_SMS_LOGIN!,
    password: process.env.OVH_SMS_PASSWORD!,
  },
});
```

## Next.js — Route Handler example

File: `app/api/notify/route.ts`

```ts
import { NextResponse } from 'next/server';
import { StudizzMailerClient } from '@/clients/studizz-mailer';

const mailer = new StudizzMailerClient();

export async function POST(request: Request) {
  const { email, firstName } = await request.json();

  try {
    await mailer.sendMail({
      subject: 'Welcome',
      htmlPart: `<p>Hi ${firstName}, welcome!</p>`,
      fromEmail: 'contact@studizz.fr',
      fromName: 'Studizz',
      recipients: [{ Email: email, Vars: { firstName } }],
    });
    return NextResponse.json({ ok: true });
  } catch (err) {
    return NextResponse.json({ ok: false, error: String(err) }, { status: 502 });
  }
}
```

## Express example

```ts
import express from 'express';
import { StudizzMailerClient } from './clients/studizz-mailer';

const app = express();
app.use(express.json());

const mailer = new StudizzMailerClient();

app.post('/notify', async (req, res) => {
  try {
    const result = await mailer.sendMail({
      subject: req.body.subject,
      htmlPart: req.body.html,
      fromEmail: 'contact@studizz.fr',
      fromName: 'Studizz',
      recipients: [{ Email: req.body.to }],
    });
    res.json(result);
  } catch (err) {
    res.status(502).json({ error: String(err) });
  }
});
```

## Mistakes to avoid

- **Do not import this client from a React component or any browser-bundled module.** The payload carries credentials.
- **Do not prefix the env var with `NEXT_PUBLIC_`** — it must stay server-only.
- **Do not retry on inner-code 400.** That's a payload problem, not a transient one.
- **Don't log the full payload** in production. Redact `credentials.password`, `credentials.apiKey`, `credentials.pass`.
- **Use `body.code`, not `response.status`,** to decide whether the send succeeded — especially for SMS, where partial failures return HTTP 201.
