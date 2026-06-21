# AGENTS.md

This file defines how coding agents should work in `phpbotgram`.

## Scope and defaults

- Repository: `gruven/phpbotgram`.
- Main branch: `master`.
- Runtime target: PHP 8.5+.
- Current upstream baseline: aiogram 3.29.0 / Telegram Bot API 10.1.
- This is a library. Treat public classes, method signatures, generated DTOs, shortcuts, docs, and examples as user-facing API.
- Keep diffs focused. Do not mix unrelated refactors, formatting churn, or generated noise into task changes.

## Project map

- `src/Bot.php` - generated bot facade.
- `src/Methods/`, `src/Types/`, `src/Enums/` - generated Telegram API surface.
- `src/Client/Serializer.php` - runtime serializer/deserializer boundary for Telegram wire payloads.
- `tools/generator/` - generator code and templates.
- `.butcher/` - schema/config inputs for generated API metadata, aliases, defaults, replaces, and union hints.
- `tests/` - PHPUnit suite, mirrored by subsystem.
- `docs/guide/en/` - narrative docs rendered by phpDocumentor.
- `CHANGELOG.md` and `README.md` - release/user-facing source docs.
- `docs/superpowers/` - historical specs/plans. Do not rewrite these to "update history" unless explicitly asked.

## Before editing

1. Inspect the current git state with `git status --short --branch`.
2. Read the relevant source-of-truth files before changing code:
   - User-facing behavior: `README.md`, `CHANGELOG.md`, relevant `docs/guide/en/*`.
   - Bot API/codegen behavior: `.butcher/`, `tools/generator/`, generated output, and tests.
   - Runtime wire behavior: `src/Client/Serializer.php` and related tests.
3. Prefer `rg` / `rg --files` for search.
4. Preserve user changes. Never revert unrelated dirty files.

## Codegen rules

Generated files are part of the committed API, but they should normally be changed through their inputs:

- Prefer editing `.butcher/**/*.yml`, generator code, or generator templates instead of hand-editing generated `src/Bot.php`, `src/Methods/*`, `src/Types/*`, or `src/Enums/*`.
- If a generated output needs a manual emergency fix, explain why the generator path was not used and add a follow-up note/test so the fix is not lost on the next regeneration.
- Shortcuts and aliases belong in `.butcher/types/*/aliases.yml`.
- Method defaults/replacements belong in `.butcher/methods/*/default.yml` and `.butcher/methods/*/replace.yml`.
- Union/discriminator behavior belongs in `.butcher/types/*/subtypes.yml`, `tools/generator/`, or the serializer when PHP cannot express the Telegram wire shape directly.
- After changing generator inputs or templates, regenerate the affected output and update generator tests.

## Breaking changes policy

The project is still before its first stable `1.0` release, but the goal is to converge on a stable architecture rather than normalize API churn.

Treat these as potential breaking changes:

- Removing or renaming public classes, methods, properties, enum cases, constants, namespaces, examples, or documented APIs.
- Reordering public constructor or method parameters when callers may use positional arguments.
- Tightening public parameter or return types in a way that rejects previously valid user code.
- Changing serializer behavior for existing Telegram payload shapes.
- Changing dispatcher, router, filter, FSM, storage, middleware, webhook, or bot-default semantics.
- Changing generated DTO construction patterns or shortcut method names.

Required escalation:

- If a breaking change looks technically recommended, stop and ask the repository owner before implementing it.
- If you see a design change that would avoid likely future breaking changes, propose it explicitly before implementation.
- Explain the tradeoff: short-term compatibility cost, long-term stability benefit, migration path, and test coverage needed.
- For Bot API schema bumps, prefer additive compatibility. If Telegram/upstream forces a non-additive change, document the reason in `CHANGELOG.md` and protect the intended behavior with tests.

Compatibility guardrails:

- Prefer named arguments in examples and docs for long generated method/DTO signatures.
- Preserve common positional calls for popular APIs and shortcuts when feasible; add regression tests when a schema bump adds parameters near the front of a signature.
- For generated methods, keep existing first-parameter ergonomics stable when possible, especially `sendMessage`, `editMessageText`, and message shortcut methods.
- Do not paper over a compatibility issue only in one generated file. Fix the generator/defaults/serializer layer when the pattern can recur.

## Upstream sync workflow

When syncing from aiogram or Telegram Bot API:

1. Pull and inspect upstream aiogram commits, not just titles.
2. Update schema/config inputs from upstream.
3. Regenerate and inspect the generated diff.
4. Identify non-additive signature, serializer, or shortcut changes.
5. Add or update tests for new wire shapes and compatibility-sensitive calls.
6. Update `README.md`, `CHANGELOG.md`, and narrative docs when the user-facing surface changes.
7. Run the relevant checks before commit or completion.

For the aiogram 3.29.0 / Bot API 10.1 sync, important compatibility lessons were:

- Rich text uses a recursive wire union: `string`, a `RichText*` object, or a list mixing strings and rich-text segments.
- Generated `array` parameters may need PHPDoc-backed list hydration, for example `list<RichBlock>`.
- `editMessageText` / `Message::editText` positional compatibility matters; preserve the existing `$text`-first ergonomics while adding `richMessage`.

## Documentation rules

- Root `CHANGELOG.md` is the source. `docs/guide/en/changelog.md` is generated by `scripts/copy-root-docs.php`.
- Do not edit generated root-doc copies directly unless you are testing the copy script.
- If docs or changelog change, run:

```bash
php scripts/copy-root-docs.php
VERSION=0.1.0-dev composer docs-api
```

- New narrative pages should follow the existing style: "When to use this", working code, explanation, and "Pitfalls".
- Update `docs/guide/en/how-to/index.md` or concept indexes when adding pages.
- Historical `docs/superpowers/specs/*` and `docs/superpowers/plans/*` can mention older upstream versions; do not rewrite them as current docs.

## Verification

Choose checks based on touched areas:

```bash
composer test
composer stan
composer lint
git diff --check
```

For docs:

```bash
VERSION=0.1.0-dev composer docs-api
git diff --check
```

For coverage-sensitive or release work:

```bash
composer coverage-gate
```

External-service tests are env-gated:

- `PHPBOTGRAM_TEST_REDIS_DSN` for Redis storage.
- `PHPBOTGRAM_TEST_MONGO_DSN` for Mongo storage.

If a check cannot be run, state the reason and the residual risk.

## Commit hygiene

- Commit only when asked or when the active task explicitly requires it.
- Use a message that reflects the actual scope. Do not label mixed code/schema/test changes as docs-only.
- Before committing, inspect staged files with `git diff --cached --stat` and `git diff --cached --check`.
- Keep generated output, generator inputs, tests, and docs in the same commit when they are one coherent change.
