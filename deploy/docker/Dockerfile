# Multi-stage build: a thin runtime image with the bot's source mounted at
# /app and dependencies installed via composer. Targets the polling echo
# bot by default; override CMD for webhook deployments.
#
# Webhook mode (`examples/echo_bot_webhook.php`) needs `amphp/http-server`,
# which lives in `require-dev`. The deps stage uses `composer install` with
# dev dependencies INCLUDED so the same image can serve both polling and
# webhook entrypoints. If you only want the polling bot and want a leaner
# image, change to `--no-dev` here and drop `examples/echo_bot_webhook.php`
# from the COPY step below.

FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --ignore-platform-req=ext-mongodb

FROM php:8.5-cli-alpine AS runtime
RUN apk add --no-cache \
        icu-libs \
        libsodium \
        oniguruma \
    && docker-php-ext-install \
        mbstring \
        sodium \
    && rm -rf /var/cache/apk/*

WORKDIR /app
COPY --from=deps /app/vendor /app/vendor
COPY src/      /app/src/
COPY examples/ /app/examples/
COPY composer.json composer.lock /app/

# Non-root user. UID/GID 65532 matches gcr.io/distroless conventions so the
# image can sit behind a same-UID Kubernetes pod spec.
RUN addgroup -g 65532 -S bot \
    && adduser  -u 65532 -G bot -S bot \
    && chown -R bot:bot /app
USER bot:bot

# Telegram polling by default. Override at `docker run` time for webhook:
#   docker run -e BOT_TOKEN=... -p 8080:8080 phpbotgram/echo \
#     php examples/echo_bot_webhook.php
ENV BOT_TOKEN=""
CMD ["php", "examples/echo_bot.php"]
