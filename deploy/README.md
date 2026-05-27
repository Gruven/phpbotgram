# Deployment configs

Production-ready templates for the common deployment shapes. Each file is a self-contained starting point — copy, edit the paths/tokens, and deploy.

## Layouts

| Path | Use when |
| --- | --- |
| `systemd/phpbotgram-polling.service` | Long-polling bot on a bare Linux host. Single process, restarts on failure, hardened with the standard systemd sandbox flags. |
| `systemd/phpbotgram-webhook.service` | Webhook bot on a bare Linux host: runs the amphp/http-server listener on `127.0.0.1:8080`. Pair with the `nginx/` template below for TLS termination. Same sandbox as the polling unit; needs `composer require amphp/http-server` (a polling-only install does not pull it). |
| `nginx/phpbotgram-webhook.conf` | Webhook bot behind nginx. TLS termination at the edge, Telegram CIDR allow-list, secret-token header forwarded to the framework's `SimpleRequestHandler`. |
| `docker/Dockerfile` + `docker/compose.yaml` | Containerised bot for local development or Kubernetes. Multi-stage build produces a `php:8.5-cli-alpine` runtime carrying `src/`, `examples/`, and the full `vendor/` (including `amphp/http-server` for webhook mode). Trim to polling-only with a one-line `--no-dev` edit — see the inline comment in the Dockerfile. |

## Edit before deploying

- `systemd/phpbotgram-polling.service` — `User`, `WorkingDirectory`, `EnvironmentFile`, `ExecStart`. The default points at `examples/echo_bot.php`; change to your own entrypoint.
- `systemd/phpbotgram-webhook.service` — same fields as the polling unit (`User`, `WorkingDirectory`, `EnvironmentFile`, `ExecStart`); the default `ExecStart` runs `examples/echo_bot_webhook.php` on `127.0.0.1:8080`. Run `composer require amphp/http-server` first — it is not in a polling-only install.
- `nginx/phpbotgram-webhook.conf` — `server_name`, TLS cert paths, upstream `127.0.0.1:8080` if you bind a different port, and the `allow` CIDRs if Telegram has rotated their ranges (check <https://core.telegram.org/bots/webhooks#the-short-version>).
- `docker/Dockerfile` — `CMD` if you want webhook mode instead of polling. Webhook mode also needs the example to bind a host-reachable interface: inside the container, change `host: '127.0.0.1'` in `examples/echo_bot_webhook.php` to `host: '0.0.0.0'` (the loopback default is safe-for-reverse-proxy but unreachable from `docker run -p`). The compose file already wires `BOT_TOKEN` from the host environment.

## Health

The nginx config exposes `/healthz` returning 200 plaintext for load-balancer probes. It does not touch the bot, so a hung framework will still pass — pair with a `/metrics` style endpoint inside the bot if you need real liveness.

## Hardening notes

- The systemd unit drops all capabilities, locks down the namespace, and applies `SystemCallFilter` with `@system-service`. Bots that need raw sockets, ICMP, or `clock_settime` will need looser filters.
- `MemoryDenyWriteExecute=true` in the systemd unit is incompatible with PHP opcache JIT. If you turn JIT on (`opcache.jit=tracing` with a non-zero `opcache.jit_buffer_size`), either set `opcache.jit_buffer_size=0` or comment the directive out — otherwise the PHP runtime is killed at boot with `SIGSYS`.
- The Docker image runs as UID/GID 65532 (matches `gcr.io/distroless`) so the same image can run unprivileged under Kubernetes without `runAsUser` overrides.
- nginx's `client_max_body_size 16k` is intentionally tight — Telegram webhook payloads are JSON-serialised metadata, not file blobs (any attachment comes through as a `file_id` you later fetch via `Bot::downloadFile`). 16k accommodates the largest realistic Update + entities + reply markup; if you see legitimate 413s in your logs, raise to 32k or 64k.
