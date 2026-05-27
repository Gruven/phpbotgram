# Getting started

Five short lessons. By the end you will have a running bot, you'll understand handlers and filters, you'll have built a stateful conversation, and you'll have a working production deployment recipe.

1. [Installation](01-installation.md) — composer require, PHP 8.5, ext-sodium.
2. [Your first bot](02-first-bot.md) — `BOT_TOKEN`, `runPolling`, echo handler.
3. [Handlers and filters](03-handlers-and-filters.md) — `Command`, `F`-DSL, returning kwargs.
4. [State](04-state.md) — inline FSM (`FsmContext`) without scenes.
5. [Deployment](05-deployment.md) — nginx + systemd from `deploy/`.

Time budget: ~30 minutes end-to-end. Each lesson is self-contained and ends with a runnable example in `examples/`.
