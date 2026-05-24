# Deployment

phpbotgram ships ready-made deployment templates under
[`deploy/`](https://github.com/Gruven/phpbotgram/tree/master/deploy):
nginx + systemd + Docker compose. Pick the shape you want.

## Long-polling bot (systemd)

Best for low-traffic bots running on a single VM.

1. Install `deploy/systemd/phpbotgram-polling.service` to
   `/etc/systemd/system/`.
2. Edit the `User`, `WorkingDirectory`, `EnvironmentFile`, `ExecStart`
   paths.
3. `systemctl daemon-reload && systemctl enable --now phpbotgram-polling`.

The unit drops capabilities, applies `SystemCallFilter`, and uses
`ProtectSystem=strict`. Read [`deploy/README.md`](https://github.com/Gruven/phpbotgram/blob/master/deploy/README.md)
for hardening notes (e.g. the `MemoryDenyWriteExecute` + PHP JIT
interaction).

## Webhook bot (nginx + amphp/http-server)

Best for higher traffic or when you want subsecond latency.

1. Install `deploy/systemd/phpbotgram-webhook.service` to
   `/etc/systemd/system/`. It runs
   [`examples/echo_bot_webhook.php`](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot_webhook.php)
   on `127.0.0.1:8080`. Webhook mode needs the suggested dependency:
   `composer require amphp/http-server`.
2. `systemctl daemon-reload && systemctl enable --now phpbotgram-webhook`.
3. Install `deploy/nginx/phpbotgram-webhook.conf` to
   `/etc/nginx/sites-available/` and link from `sites-enabled/`.
4. Provision a TLS cert (Let's Encrypt). The provided config terminates
   TLS and proxies to the loopback.
5. Register the webhook with Telegram via `setWebhook` (the framework's
   [`Setup::register()`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-Setup.html#method_register)
   helper handles this).

The nginx config restricts inbound to Telegram's CIDR ranges. The
framework's [`IpFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-IpFilter.html)
middleware can repeat the check in defense-in-depth; wire it via
`AmphpServer::run(..., ipFilter: IpFilter::default())`.

## Docker

`deploy/docker/Dockerfile` + `deploy/docker/compose.yaml` ship a
multi-stage build producing a `php:8.5-cli-alpine` runtime with
`vendor/` + `src/` + `examples/`.

```bash
BOT_TOKEN=… docker compose -f deploy/docker/compose.yaml up --build
```

The image runs as UID 65532. For webhook mode in a container, change
`host: '127.0.0.1'` to `'0.0.0.0'` in
`examples/echo_bot_webhook.php` so `docker run -p 8080:8080` can reach
it.

## What's next

Browse the [cookbook](../how-to/index.md) for task-oriented recipes
or the [concepts pages](../concepts/index.md) for the architectural
deep dive.
