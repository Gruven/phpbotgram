# Installation

phpbotgram requires PHP 8.5+ and the `sodium` extension (used by the
Web App / Login Widget signature verification). composer pulls in
the rest.

## composer require

```bash
composer require gruven/phpbotgram
```

The package is published on Packagist; no extra repository is needed.

## Required PHP extensions

phpbotgram declares these as hard requirements in `composer.json`:

- `ext-mbstring`
- `ext-json`
- `ext-sodium`

Standard PHP 8.5 distributions on Linux and macOS ship these by default.
On Alpine-based Docker images, install them via `apk add php85-mbstring
php85-sodium`.

## Verify the install

```bash
php -r "var_dump(class_exists('Gruven\\PhpBotGram\\Bot'));"
```

Expected output: `bool(true)`.

## Next step

[Build your first bot →](02-first-bot.md)
