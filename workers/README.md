# workers — placeholder (built at J2)

Python 3.11 + pika transformer workers (chunks, persona sheet, structured
JSON, synthesis). Consume RabbitMQ queues namespaced `interview.*`; the
backend publishes through its `JobDispatcher` interface (studizz-api-amqp
gateway). Deployed by git + `deploy/install-workers.sh` (one systemd service
per worker directory).
