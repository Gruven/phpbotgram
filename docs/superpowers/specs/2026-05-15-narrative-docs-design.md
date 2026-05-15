# phpbotgram narrative documentation — design

> Status: draft, ready for review.
> Outcome: a docs site modelled after <https://docs.aiogram.dev/en/v3.28.2/>,
> served at `https://gruven.github.io/phpbotgram/en/<version>/`, integrated
> with the phpDocumentor v3 API reference already shipped in Phase 9.

## Goals

1. **Narrative parity with aiogram 3.28** — every concept a Telegram-bot
   author needs to ship a production bot is explained in prose, with
   examples, and cross-linked to the API reference.
2. **Diataxis structure** — clear separation of tutorials, how-to recipes,
   concept explanations, and reference (API). Newcomers, integrators, and
   maintainers each have a primary entry point.
3. **Single-tool pipeline** — one `composer docs-api` invocation builds
   both the narrative and the API reference into the same site, with
   working cross-references between them.
4. **Per-version site** — `/en/dev/`, `/en/v0.1.0/`, `/en/latest/` (latest
   tag), all live concurrently on `gh-pages` with a navbar switcher.
5. **i18n-ready layout** — sources stored under `docs/guide/en/` so a
   future `ru/` mirror can be added without touching the build pipeline.
6. **CI-enforced quality** — broken cross-refs, syntax-invalid PHP
   snippets, dead example links, and markdown lint violations fail the
   build.

## Non-goals (deliberate scope cuts)

- Russian (or any other non-English) translation in this phase. Layout
  reserves the slot; content is English-only.
- Sphinx-style internationalisation tooling (`.po` files, gettext message
  extraction, translation memory) — left to a later phase if RU lands.
- Migration guide (`migration_2_to_3.rst` equivalent). `v0.1.0` is the
  first release; nothing to migrate from. A `migration/` directory is
  reserved with a `.gitkeep` placeholder.
- Read the Docs hosting. We control the deploy pipeline through GitHub
  Actions + `gh-pages`.
- Auto-generated API tutorials (e.g. "for each method, render a tutorial
  page"). Method/type pages remain in phpDocumentor's auto-generated
  output.
- Watch-mode local dev server. `composer docs-api && open build/docs/...`
  is the dev loop; rebuild is fast enough.
- Screenshots or video walkthroughs.
- Live Telegram API calls during doc build.

## Architecture

### Build pipeline (single tool, two content roots)

`phpdocumentor/shim` (already shipped in Phase 9) drops the official
phpDocumentor v3 phar into `vendor/bin/phpdoc`. Phase 10 extends the
existing `phpdoc.dist.xml` with a `<guide>` block so the same phar
renders narrative Markdown alongside the API reference:

```xml
<phpdocumentor configVersion="3" xmlns="https://www.phpdoc.org">
  <title>phpbotgram</title>
  <paths>
    <output>build/docs/api</output>
    <cache>build/docs/.cache</cache>
  </paths>
  <version number="${VERSION}">
    <api format="php">
      <source dsn="file://."><path>src</path></source>
    </api>
    <guide format="md" output="en">
      <source dsn="file://."><path>docs/guide/en</path></source>
    </guide>
    <!-- future: <guide format="md" output="ru"> ... -->
  </version>
</phpdocumentor>
```

`${VERSION}` is injected at build time. Locally it defaults to
`0.1.0-dev`; in CI it is `dev` for the `master` workflow and `vX.Y.Z`
for the tag workflow.

`phpdocumentor/guides-markdown` is bundled inside the phar from
v3.10.0+; no extra composer requirement is needed.

### Content tree (Diataxis)

```
docs/guide/
  en/
    index.md                          # landing: 4 quadrant cards + version picker
    tutorial/
      index.md                        # "Getting started" landing
      01-installation.md              # composer + ext-sodium + PHP 8.5
      02-first-bot.md                 # /start echo, BOT_TOKEN, runPolling
      03-handlers-and-filters.md      # Command + F-DSL basics
      04-state.md                     # inline FSM (no scenes yet)
      05-deployment.md                # nginx + systemd from deploy/
    how-to/
      index.md                        # cookbook landing, grouped by intent
      deep-linking.md
      telegram-stars-payment.md
      multi-bot.md
      custom-filter.md
      custom-storage.md
      file-upload-download.md
      web-app-data.md
      scenes-wizard.md
      webhook-without-amphp-server.md
      background-tasks.md
      error-handling.md
      testing-bots.md
      redis-mongo-fsm.md
      rate-limiting.md
      i18n-payloads.md
    concepts/
      index.md
      bot-and-session.md
      dispatcher.md
      routers.md
      filters.md
      f-dsl.md
      callback-data.md
      middlewares.md
      flags.md
      fsm.md
      scenes.md
      webhook.md
      text-decoration.md
      keyboards.md
      error-model.md
      architecture-decisions.md       # documented divergences from aiogram
    reference/
      index.md                        # stub linking to ../api/
    changelog.md                      # includes ../../../../CHANGELOG.md
    contributing.md                   # includes ../../../../CONTRIBUTING.md
    migration/
      .gitkeep                        # reserved for future breaking changes
  shared/
    assets/
      diagrams/                       # SVG/PNG, language-agnostic
      code-snippets/                  # *.php fragments, included into .md
```

Counts: 5 tutorial + 15 how-to + 15 concepts + stubs ≈ **40 pages**.

### Cross-references (guide ↔ API)

phpDocumentor's `xref:` syntax resolves to the API page for the named
symbol inside the same build:

```markdown
The dispatcher delegates to [Router::propagateEvent](xref:Gruven\PhpBotGram\Dispatcher\Router::propagateEvent).
```

Broken `xref:` is reported as a warning. CI runs `phpdoc --fail-on=error`
plus an explicit `scripts/check-docs-xrefs.php` that greps every
`xref:` and verifies the target class exists under `src/`.

### Code examples (hybrid pattern)

Two presentation styles, used together:

1. **Inline snippet** — fenced ```php block inside the Markdown. Used
   for short conceptual illustrations (3–15 lines). Validated by
   `scripts/lint-docs.php` (extract block → `php -l`).
2. **Full runnable bot** — link to `examples/<name>.php`. Used when the
   point of the example is end-to-end behaviour:

   ```markdown
   See the [full example](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot.php).
   ```

   Validated by `scripts/check-docs-examples.php` (every linked example
   file exists on disk).

The split keeps reading flow uninterrupted (no context switch for short
ideas) without losing the single-source-of-truth guarantee for runnable
bots.

### Versioning + deploy

Two GitHub Actions workflows publish to a single `gh-pages` orphan
branch:

```
.github/workflows/docs.yml          # push to master → /en/dev/
.github/workflows/docs-release.yml  # tag push v*.*.* → /en/<tag>/ + /en/latest/
```

Each run:

1. Checkout the appropriate ref.
2. `composer install --no-interaction --no-progress`.
3. `VERSION=<dev|vX.Y.Z> composer docs-api`.
4. Checkout `gh-pages` into a worktree.
5. `rsync -a --delete build/docs/api/ <worktree>/en/<VERSION>/`.
6. Tag workflow only: update `/en/latest/` (full copy of new tag),
   append entry to `/versions.json`.
7. Run `scripts/inject-version-switcher.php` against the
   freshly-rsynced HTML — injects a navbar dropdown that reads
   `/versions.json` at page load.
8. Commit + push `gh-pages` via `peaceiris/actions-gh-pages@v4`
   (`keep_files: true` so other version dirs survive).

`gh-pages/index.html` is a static `<meta http-equiv="refresh">` redirect
to `/en/latest/`.

`versions.json`:

```json
{
  "languages": ["en"],
  "versions": [
    { "id": "dev",     "label": "dev (master)",     "url": "/en/dev/"    },
    { "id": "latest",  "label": "latest (vX.Y.Z)",  "url": "/en/latest/" },
    { "id": "v0.1.0",  "label": "v0.1.0",           "url": "/en/v0.1.0/" }
  ]
}
```

Old versions never auto-delete; manual cleanup via direct `gh-pages`
commit if it ever matters.

### Version + language switcher

Single shared component, injected post-build:

- Two `<select>` elements rendered into the navbar (language, version).
- `<script>` fetches `/versions.json` and `/languages.json` at page
  load, populates options, marks current selection.
- Unavailable cross-products (e.g. `ru/v0.1.0/` before RU is translated)
  render as `disabled <option>`.
- Switcher is injected by `scripts/inject-version-switcher.php` walking
  every `*.html` under the freshly built site and patching a known
  placeholder in the phpDocumentor template (or wrapping `<body>` if
  no placeholder).

### i18n-ready structure

English content lives at `docs/guide/en/`. A future `docs/guide/ru/`
mirrors the structure 1-to-1. The build config gains a second `<guide
output="ru">` block. The switcher already supports the language
dimension. No gettext / `.po` workflow: a translator authors complete
Markdown files in `ru/`.

Fallback behaviour when a `ru/` page is missing: the navbar's "ru" link
on that page is `disabled` (or it falls through to `/en/<same-path>/`
with an "untranslated" banner). Implementation detail of the switcher
script.

## Components

| Component | Purpose | Lives at |
| --- | --- | --- |
| `docs/guide/en/` Markdown tree | Narrative content source | repo |
| `docs/guide/shared/` | Language-agnostic SVG/PHP fragments | repo |
| `phpdoc.dist.xml.tpl` | Versionable template (envsubst → `phpdoc.dist.xml`) | repo |
| `scripts/lint-docs.php` | Extract ```php → `php -l` | repo |
| `scripts/check-docs-xrefs.php` | Verify every `xref:` target exists in `src/` | repo |
| `scripts/check-docs-examples.php` | Verify every `examples/*.php` link is real | repo |
| `scripts/inject-version-switcher.php` | Post-build HTML patcher | repo |
| `versions.json`, `languages.json` | Switcher inventory | `gh-pages` |
| `.github/workflows/docs.yml` | master → `/en/dev/` | repo |
| `.github/workflows/docs-release.yml` | tag → `/en/<tag>/` + `latest/` | repo |
| `composer docs-api` / `make docs-api` | Local build entry point | repo |

## Data flow

```
master push
    │
    ▼
docs.yml workflow
    ├─ composer install
    ├─ VERSION=dev envsubst phpdoc.dist.xml.tpl → phpdoc.dist.xml
    ├─ composer docs-api  →  build/docs/api/
    ├─ scripts/lint-docs.php           (gate)
    ├─ scripts/check-docs-xrefs.php    (gate)
    ├─ scripts/check-docs-examples.php (gate)
    ├─ markdownlint --strict           (gate)
    ├─ scripts/inject-version-switcher.php on rendered HTML
    └─ peaceiris/actions-gh-pages@v4 → gh-pages/en/dev/

tag push v0.1.0
    │
    ▼
docs-release.yml workflow
    ├─ (same build steps with VERSION=v0.1.0)
    ├─ rsync → gh-pages/en/v0.1.0/
    ├─ rsync → gh-pages/en/latest/   (copy of v0.1.0)
    ├─ append "v0.1.0" to gh-pages/versions.json
    └─ push gh-pages
```

## Error handling

| Failure | Detected by | Behaviour |
| --- | --- | --- |
| Markdown syntax error | `phpdoc --fail-on=error` | Build fails, workflow red |
| Broken `xref:` | `phpdoc --fail-on=error` + `scripts/check-docs-xrefs.php` | Build fails |
| `\`\`\`php` block fails `php -l` | `scripts/lint-docs.php` | Build fails |
| Linked `examples/X.php` missing | `scripts/check-docs-examples.php` | Build fails |
| Markdown style violation | `markdownlint-cli2` | Build fails |
| Workflow secret missing (gh-pages push) | `peaceiris/actions-gh-pages` | Workflow red |
| `gh-pages` push race | `peaceiris/actions-gh-pages` retries; concurrency group `pages` cancels older runs | Self-healing |
| Network error fetching phpdoc phar | `composer install` returns non-zero | Workflow red |

Author-facing errors are surfaced as PR-blocking CI failures. No
silent build-time degradation.

## Testing strategy

### Per-PR (gate)

1. `composer test` — existing unit test suite still passes.
2. `make coverage-gate` — per-module floors still met.
3. `composer docs-api` — full build with `--fail-on=error`.
4. `scripts/lint-docs.php` — all PHP snippets parse.
5. `scripts/check-docs-xrefs.php` — all `xref:` resolve.
6. `scripts/check-docs-examples.php` — all example links resolve.
7. `markdownlint-cli2` — style.

### Per-release

8. The published `/en/<tag>/index.html` returns HTTP 200.
9. Spot-check: navbar switcher includes the new version.
10. Spot-check: API reference within the new version still works.

### Manual content review checklist (per page)

- **tutorial/**: contains a runnable snippet or full-example link.
- **concepts/**: ≥ 1 `xref:` to a corresponding API class.
- **how-to/**: starts with "When to use this", ends with "Pitfalls".
- **index.md**: 4 Diataxis quadrants linked, each with a one-sentence
  hook.

No content-quality automation in scope; reviewer judgment covers
grammar, voice, and accuracy.

## Definition of done

- `docs/guide/en/` populated per the content tree (5 + 15 + 15 + stubs).
- `phpdoc.dist.xml.tpl` produces a single site combining narrative +
  API reference, with working `xref:` cross-links.
- `.github/workflows/docs.yml` + `.github/workflows/docs-release.yml`
  publish to `gh-pages/en/<version>/`.
- `https://gruven.github.io/phpbotgram/en/dev/index.html` renders with
  navbar showing 4 Diataxis sections + language + version switchers.
- All seven CI gates green on the Phase 10 merge commit.
- `CHANGELOG.md` Phase 10 entry summarises the new docs surface.

## Out-of-scope follow-ups (not Phase 10)

- Russian translation (Phase 11+).
- Sphinx-style search index / `searchindex.json` (Phase 11+).
- Mobile-optimised theme variant (Phase 11+).
- Doc-versions-as-PR-comments (preview deploys on PR open) — useful but
  deferred.
- Auto-generated changelog → release-notes page (Phase 11+).
- Live "try it" sandbox (out of scope indefinitely).

## Open questions

None at design time. Versioning, content tree, toolchain, deploy,
language strategy, and test strategy are all settled in the
brainstorming session that produced this document.

## References

- Diataxis methodology: <https://diataxis.fr>
- Aiogram docs (visual + structural reference): <https://docs.aiogram.dev/en/v3.28.2/>
- phpDocumentor Guides internals: <https://docs.phpdoc.org/guide/internals/guides/index.html>
- `phpdocumentor/guides` library: <https://github.com/phpDocumentor/guides>
- Phase 9 (API documentation pipeline this design extends):
  `docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md` §
  Phase 9.
