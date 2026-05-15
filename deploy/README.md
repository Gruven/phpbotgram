# Deployment configs

Production-ready templates for the three common deployment shapes. Each file
is a self-contained starting point — copy, edit the paths/tokens, and
deploy.

## Layouts

| Path | Use when |
| --- | --- |
| `systemd/phpbotgram-polling.service` | Long-polling bot on a bare Linux host. Single process, restarts on failure, hardened with the standard systemd sandbox flags. |
| `nginx/phpbotgram-webhook.conf` | Webhook bot behind nginx. TLS termination at the edge, Telegram CIDR allow-list, secret-token header forwarded to the framework's `SimpleRequestHandler`. |
| `docker/Dockerfile` + `docker/compose.yaml` | Containerised bot for local development or Kubernetes. Multi-stage build trims the runtime image to `php:8.5-cli-alpine` + `vendor/` + `src/` + `examples/`. |

## Edit before deploying

- `systemd/phpbotgram-polling.service` — `User`, `WorkingDirectory`,
  `EnvironmentFile`, `ExecStart`. The default points at
  `examples/echo_bot.php`; change to your own entrypoint.
- `nginx/phpbotgram-webhook.conf` — `server_name`, TLS cert paths, upstream
  `127.0.0.1:8080` if you bind a different port, and the `allow` CIDRs if
  Telegram has rotated their ranges (check
  <https://core.telegram.org/bots/webhooks#the-short-version>).
- `docker/Dockerfile` — `CMD` if you want webhook mode instead of polling.
  The compose file already wires `BOT_TOKEN` from the host environment.

## Health

The nginx config exposes `/healthz` returning 200 plaintext for
load-balancer probes. It does not touch the bot, so a hung framework will
still pass — pair with a `/metrics` style endpoint inside the bot if you
need real liveness.

## Hardening notes

- The systemd unit drops all capabilities, locks down the namespace, and
  applies `SystemCallFilter` with `@system-service`. Bots that need raw
  sockets, ICMP, or `clock_settime` will need looser filters.
- The Docker image runs as UID/GID 65532 (matches `gcr.io/distroless`)
  so the same image can run unprivileged under Kubernetes without
  `runAsUser` overrides.
- nginx's `client_max_body_size 16k` is intentionally tight — Telegram
  webhook payloads are JSON-serialised metadata, not file blobs (any
  attachment comes through as a `file_id` you later fetch via
  `Bot::downloadFile`). 16k accommodates the largest realistic
  Update + entities + reply markup; if you see legitimate 413s in
  your logs, raise to 32k or 64k.
