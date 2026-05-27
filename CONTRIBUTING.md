# Contributing to phpbotgram

Thank you for your interest in contributing. This project is a PHP 8.5 port of the aiogram Telegram bot framework; the design specs and implementation plans live under [`docs/superpowers/`](https://github.com/Gruven/phpbotgram/tree/master/docs/superpowers).

## Opening an issue

- Search existing issues first; duplicates get closed quickly.
- Include the framework version (`composer info gruven/phpbotgram | head -3`), PHP version (`php -v`), and a minimal reproducer.
- For Telegram-API integration bugs, attach the raw request/response if you can. Strip bot tokens.

## Pull request workflow

1. Branch from `master`: `git checkout -b feature/short-name master`.
2. Make changes; keep each commit focused. Conventional Commits-style prefix (`feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`) appreciated.
3. Run the full local check:

   ```bash
   composer test               # PHPUnit
   composer coverage-gate      # per-module coverage floors
   composer stan               # PHPStan level 9
   composer lint               # php-cs-fixer dry-run
   composer docs-api           # narrative + API build + gates
   ```

   All five must pass before review.
4. Push the branch and open a PR. CI replicates the local checks plus `markdownlint-cli2` on narrative Markdown.

## Coding standards

- **PHP 8.5** features encouraged: readonly classes, asymmetric visibility, property hooks, enums, attributes.
- **PHPStan level 9** — no `@phpstan-ignore`, no `assert()`, no inline `@var` to silence type errors. Fix the underlying issue.
- **php-cs-fixer** rules in `.php-cs-fixer.dist.php` are enforced.
- **No commented-out code**, no debug-print statements in committed code.
- **Tests required** for new behaviour. Aim for the existing per-module coverage floors (`scripts/coverage-gate.php`).

## Documentation contributions

Narrative docs live under `docs/guide/en/`. The build:

- `composer docs-api` renders narrative + API into `build/docs/api/`.
- `npx markdownlint-cli2@0.22.1 'docs/guide/en/**/*.md'` enforces style.
- `scripts/lint-docs.php` validates `` ```php `` fenced blocks (auto-prepends `<?php\n`) and forbids inline raw HTML in narrative pages.
- Guide → API cross-links use the sentinel host `https://api.phpbotgram.local/<Namespace-with-dashes>-<Class>.html`, rewritten post-build to `classes/...`.

### Fenced-block conventions

Two fence languages carry meaning for the lint gate:

- **`` ```php ``** — a complete, parseable PHP snippet. `scripts/lint-docs.php` auto-prepends `<?php\n` and runs `php -l`. Parse errors fail the build. Use this fence for full files, top-level scripts, and any snippet that forms a syntactically valid program on its own.
- **`` ```php-fragment ``** — a class-body or method-body fragment that cannot be parsed standalone (e.g. `public function send(): void { ... }` without a class wrapper). `scripts/lint-docs.php` skips these blocks; authors are responsible for keeping the code correct.

The Phase 10 pilot pass (Task 1 Step 4b, notes at `docs/superpowers/notes/2026-05-15-phase-10-pilot.md`) recorded how phpDocumentor's renderer treats each fence. If the pilot notes say the two render identically (no syntax-highlight distinction), prefer `php` and wrap fragments in a synthetic class — that keeps the lint gate active even for snippet authors.

Other languages (`yaml`, `bash`, `json`, `dot`, `text`, …) are allowed and pass through the linter unchanged.

## Commit message convention

```text
<type>: <imperative summary, lowercase, <72 chars>

Optional body paragraph(s). Wrap at ~72 cols. Explain *why*, not *what*
(the diff already shows what).

Co-Authored-By: <co-author> <email>  # if applicable
```

Common types: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`, `ci`, `perf`, `style`.

## Reporting security issues

Email <igruven@gmail.com>. Do not file a public issue for security-sensitive bugs.

## License

By contributing you agree your work is licensed under the project's MIT license (see [LICENSE](LICENSE)).
