# voice-gateway — placeholder (built at J4)

Python 3.11 FastAPI (ASGI) WebSocket gateway: browser audio → streaming STT →
Symfony engine API → streaming TTS, with barge-in. Stateless, no MongoDB
access — it only talks to the backend API. Dev: container; prod: systemd
service behind the Apache `mod_proxy_wstunnel` vhost (long timeouts,
20-minute hold test at J4).
