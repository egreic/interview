# Deployment — OVH VPS (Apache + git + systemd)

Production model (decided in the scaffold/CDC arbitration; docker-compose is
dev-only and never deployed):

- **Backend** — git pull on the VPS + `composer install --no-dev` +
  `cache:clear`. Apache vhost DocumentRoot points at `backend/public`.
- **Frontends** — built locally (`npm run build`), tar + scp to the Apache
  docroot with timestamped backup (script added when the apps exist, J3/J6).
- **Workers** — git pull + `install-workers.sh` (venv + one instanced systemd
  unit per worker directory, prefix `interview-worker@<name>`). Added at J2.
- **Voice gateway** — systemd service (uvicorn) behind the Apache vhost.
  **Requirement**: `mod_proxy_wstunnel` enabled with long timeouts
  (`ProxyTimeout` ≥ 1800, WebSocket upgrade on `/ws/`), 20-minute hold test
  at J4. Added at J4.

## Deployment checklist (grows with each milestone)

- [ ] `studizz-api-amqp` gateway reachable ONLY from the internal network —
      never exposed publicly (arbitration #6). Verify from an external host
      that the gateway URL does not answer.
- [ ] Apache vhost: `mod_proxy_wstunnel` enabled, long timeouts set (J4).
- [ ] Secrets provided via environment only — no secret in the repo.
