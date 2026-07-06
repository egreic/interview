# ReactJS / JavaScript client for studizz-api-amqp

Language-specific guide. Covers browser-side React (Vite, CRA, Next.js client components) and Node.js usage. Read `api-spec.md` first.

## Critical context: where will the client run?

The Studizz AMQP gateway has **no authentication**, which is only safe because it is meant to live on a trusted network. That reshapes the React answer:

- **Browser-side (React app directly calls the gateway)**: only OK if the gateway is reachable from the user's network *and* it is acceptable for any visitor to publish messages to the queue. In a public-facing SPA, this is rarely desirable — any user can spam your AMQP exchanges. Before generating browser-side code, confirm with the user that this is really what they want.
- **Server-side (Node / Next.js API route / Server Action / BFF)**: the typical safe choice. The browser talks to your own backend, which talks to the gateway. CORS and abuse stop being concerns.

When in doubt, default to the server-side pattern and expose a small API route from your own backend that proxies calls to the gateway.

## HTTP client choice

- **`fetch`** — built into browsers and modern Node (18+). Zero dependencies. Default choice.
- **`axios`** — use if the project already has it. Don't add it just for this.
- **`ky`** — nice ergonomic wrapper around fetch. Only if already in the project.

## Configuration

The base URL goes in an env var. **How** to name it depends on the framework:

| Framework           | Variable name                       | Exposure                             |
| ------------------- | ----------------------------------- | ------------------------------------ |
| Next.js (server)    | `STUDIZZ_API_AMQP_URL`              | Server-only. Never prefix `NEXT_PUBLIC_`. |
| Next.js (browser)   | `NEXT_PUBLIC_STUDIZZ_API_AMQP_URL`  | Exposed to the client bundle. Only if you really want direct browser calls. |
| Vite                | `VITE_STUDIZZ_API_AMQP_URL`         | Exposed to the client bundle.        |
| Create React App    | `REACT_APP_STUDIZZ_API_AMQP_URL`    | Exposed to the client bundle.        |
| Plain Node script   | `STUDIZZ_API_AMQP_URL`              | Server-only.                         |

Add the matching line to `.env.example` / `.env.local.example`.

## Reference implementation — generic JS client (works in browser and Node 18+)

File: `src/lib/studizzAmqpClient.ts` (drop the types for a `.js` version).

```ts
export class StudizzAmqpError extends Error {
  readonly status?: number;
  constructor(message: string, status?: number) {
    super(message);
    this.name = "StudizzAmqpError";
    this.status = status;
  }
}

export interface StudizzAmqpClientOptions {
  baseUrl: string;
  fetchImpl?: typeof fetch;
  timeoutMs?: number;
}

export class StudizzAmqpClient {
  private readonly baseUrl: string;
  private readonly fetchImpl: typeof fetch;
  private readonly timeoutMs: number;

  constructor({ baseUrl, fetchImpl = fetch, timeoutMs = 5000 }: StudizzAmqpClientOptions) {
    this.baseUrl = baseUrl.replace(/\/+$/, "");
    this.fetchImpl = fetchImpl;
    this.timeoutMs = timeoutMs;
  }

  publishTask(payload: Record<string, unknown>): Promise<void> {
    return this.post("/api/task", payload);
  }

  publishCall(payload: Record<string, unknown>): Promise<void> {
    return this.post("/api/call", payload);
  }

  private async post(path: string, payload: Record<string, unknown>): Promise<void> {
    if (!payload || Object.keys(payload).length === 0) {
      throw new StudizzAmqpError("Studizz AMQP payload cannot be empty.");
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), this.timeoutMs);

    try {
      const response = await this.fetchImpl(`${this.baseUrl}${path}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
        signal: controller.signal,
      });

      if (!response.ok) {
        const text = await response.text().catch(() => "");
        throw new StudizzAmqpError(
          `Studizz AMQP call to ${path} returned HTTP ${response.status}: ${text}`,
          response.status,
        );
      }
    } catch (err) {
      if (err instanceof StudizzAmqpError) throw err;
      const message = err instanceof Error ? err.message : String(err);
      throw new StudizzAmqpError(`Studizz AMQP call to ${path} failed: ${message}`);
    } finally {
      clearTimeout(timeout);
    }
  }
}
```

### Instantiation

Server-side (Next.js route handler, Node script, Express):

```ts
import { StudizzAmqpClient } from "./studizzAmqpClient";

const client = new StudizzAmqpClient({
  baseUrl: process.env.STUDIZZ_API_AMQP_URL!,
});

await client.publishTask({ type: "send_email", to: "user@example.com" });
```

Browser (Vite):

```ts
const client = new StudizzAmqpClient({
  baseUrl: import.meta.env.VITE_STUDIZZ_API_AMQP_URL,
});
```

## Pattern: Next.js API route as a safe proxy

Recommended when the React app runs in the browser.

```ts
// app/api/studizz/task/route.ts (Next.js App Router)
import { NextResponse } from "next/server";
import { StudizzAmqpClient } from "@/lib/studizzAmqpClient";

const client = new StudizzAmqpClient({ baseUrl: process.env.STUDIZZ_API_AMQP_URL! });

export async function POST(request: Request) {
  const payload = await request.json();

  // Add your own auth / validation / rate limiting here before publishing.
  if (!payload || typeof payload !== "object") {
    return NextResponse.json({ error: "invalid payload" }, { status: 400 });
  }

  try {
    await client.publishTask(payload);
    return NextResponse.json({ ok: true });
  } catch (err) {
    const message = err instanceof Error ? err.message : "unknown error";
    return NextResponse.json({ error: message }, { status: 502 });
  }
}
```

Then the browser calls `/api/studizz/task` on the same origin — no CORS, auth enforced by your app.

## Pattern: React hook for calling the proxy (or the gateway directly)

```tsx
import { useState } from "react";

export function usePublishTask() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function publish(payload: Record<string, unknown>) {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/studizz/task", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : "unknown error");
      throw err;
    } finally {
      setLoading(false);
    }
  }

  return { publish, loading, error };
}
```

If the project uses React Query / TanStack Query, wrap the call in a `useMutation` instead — it handles loading/error state and cache invalidation better than a hand-rolled hook.

## Install commands

The reference client has zero dependencies. Only install if the project prefers a library:

```bash
npm install axios     # if project already uses it
npm install ky        # if project already uses it
```

## Gotchas

- **CORS** only matters for direct browser calls. If you go that route, the gateway needs to send `Access-Control-Allow-Origin` for your frontend's origin. If it doesn't, you'll need the proxy pattern above — and that's usually the better choice anyway.
- **`AbortController` timeout**: fetch has no built-in timeout. The reference client wires one up. Don't skip it — a hanging request on a dead broker will freeze your UI indefinitely.
- **Don't leak the URL into the bundle accidentally**: in Next.js, only variables prefixed `NEXT_PUBLIC_` are shipped to the browser. Same idea for Vite (`VITE_`) and CRA (`REACT_APP_`). Server-side code should use the unprefixed name.
- **TypeScript**: the payload is typed as `Record<string, unknown>` because the gateway does not validate schemas. If the downstream consumer expects a specific shape, define that type in the caller and pass it in — don't bake it into the generic client.
