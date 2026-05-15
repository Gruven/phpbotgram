# phpbotgram narrative documentation — design

> Status: draft, ready for review.
> Outcome: a docs site modelled after <https://docs.aiogram.dev/en/v3.28.2/>,
> served at `https://gruven.github.io/phpbotgram/en/<version>/`, integrated
> with the phpDocumentor v3 API reference shipped in Phase 9.

## Goals

1. **Narrative parity with aiogram 3.28** — every concept a Telegram-bot
   author needs to ship a production bot is explained in prose, with
   examples, and linked to the API reference.
2. **Diataxis structure** — clear separation of tutorials, how-to recipes,
   concept explanations, and reference (API). Newcomers, integrators, and
   maintainers each have a primary entry point.
3. **Single-tool pipeline** — one `composer docs-api` invocation builds
   both the narrative and the API reference into the same output tree,
   with working hyperlinks between them.
4. **Per-version site** — `/en/dev/`, `/en/v0.1.0/`, `/en/latest/` (latest
   tag), all live concurrently on `gh-pages` with a navbar switcher.
5. **i18n-foundation, not i18n-now** — the file layout slots a future
   `ru/` mirror in without restructuring; adding RU still requires a
   handful of explicit code changes documented in §i18n in this spec.
6. **CI-enforced quality** — broken cross-links, syntax-invalid PHP
   snippets, dead example links, and markdown-lint violations fail the
   build. Because phpDocumentor v3 itself never sets a non-zero exit on
   doc-quality issues, Phase 10 implements the gate as post-build
   scripts that scan the build log and the rendered output.

## Non-goals (deliberate scope cuts)

- Russian (or any other non-English) translation in this phase. Layout
  reserves the slot; content is English-only. See §i18n for what
  adding RU actually entails.
- Sphinx-style internationalisation tooling (`.po` files, gettext
  message extraction, translation memory).
- Migration guide (`migration_2_to_3.rst` equivalent). `v0.1.0` is the
  first release; nothing to migrate from. A `migration/` directory is
  reserved with a `.gitkeep` placeholder for future breaking changes.
- Read the Docs hosting. We control the deploy pipeline through GitHub
  Actions + `gh-pages`.
- Auto-generated API tutorials (e.g. "for each method, render a tutorial
  page"). Method/type pages remain in phpDocumentor's auto-generated
  output.
- Watch-mode local dev server. `composer docs-api && open
  build/docs/api/index.html` is the dev loop.
- Screenshots or video walkthroughs.
- Live Telegram API calls during doc build.

## Architecture

### Phase 9 dependencies the design relies on

Phase 9 already shipped:

- `phpdocumentor/shim ^3` (composer dev-dep) drops the official
  phpDocumentor v3.10 phar into `vendor/bin/phpdoc`. The phar bundles
  `phpdocumentor/guides`, `guides-markdown`, `guides-restructured-text`,
  and `guides-graphs` (verified by walking the phar archive on
  2026-05-15).
- `phpdoc.dist.xml` rendering API docs from `src/` into
  `build/docs/api/`.
- `composer docs-api` script + `make docs-api` Makefile target.
- `.github/workflows/docs.yml` publishing `build/docs/api/` to GitHub
  Pages via `actions/upload-pages-artifact@v3` +
  `actions/deploy-pages@v4` (Pages "workflow mode").

Phase 10 explicitly migrates the Phase 9 workflow from Pages
"workflow mode" to **Pages branch mode** so multiple versions can
coexist on a single `gh-pages` branch (see §Versioning + deploy).

### Build pipeline (single tool, two content roots)

Phase 10 extends `phpdoc.dist.xml` with a `<guide>` block so the same
phpDocumentor phar renders narrative Markdown alongside the API
reference. Because the `<version number="…">` must change between dev
and tag builds, the file becomes a **template** (`phpdoc.dist.xml.tpl`,
committed), and the rendered `phpdoc.dist.xml` is gitignored.

`phpdoc.dist.xml.tpl`:

```xml
<phpdocumentor configVersion="3" xmlns="https://www.phpdoc.org">
  <title>phpbotgram</title>
  <paths>
    <output>build/docs/api</output>
    <cache>build/docs/.cache</cache>
  </paths>
  <version number="${VERSION}">
    <api format="php">
      <source dsn="."><path>src</path></source>
    </api>
    <guide format="md" output="guide">
      <source dsn="."><path>docs/guide/en</path></source>
    </guide>
  </version>
</phpdocumentor>
```

`${VERSION}` is substituted via `envsubst < phpdoc.dist.xml.tpl >
phpdoc.dist.xml` immediately before each phpdoc invocation:

- Locally: `0.1.0-dev` (set by the Makefile / composer script default).
- CI (master push): `dev`.
- CI (tag push `vX.Y.Z`): `vX.Y.Z`.

`envsubst` is part of `gettext` (preinstalled on `ubuntu-latest`
runners and on most dev machines via `brew install gettext`). The
Makefile/composer-script entry point is responsible for both rendering
the template and invoking phpdoc, so contributors never see the
two-step flow.

`<source dsn=".">` matches the Phase 9 working file syntax exactly. The
`<guide output="guide">` value is the **output subdirectory inside
`paths.output`**, not a URL or language label — `output="guide"` means
narrative HTML lands at `build/docs/api/guide/`. The API reference
stays at `build/docs/api/classes/`, `build/docs/api/namespaces/`, etc.

### Output tree (after build)

```
build/docs/api/
  index.html             # phpDocumentor landing (covers API)
  classes/...            # API: per-class pages
  namespaces/...         # API: per-namespace pages
  files/...              # API: per-file pages
  guide/                 # narrative (from <guide output="guide">)
    index.html
    tutorial/...
    how-to/...
    concepts/...
    reference/...
    changelog.html
    contributing.html
```

### Cross-references (guide → API)

**No symbolic `xref:` shortcut.** phpDocumentor v3.10's Markdown plugin
(`phpdocumentor/guides-markdown`) does not implement reference text
roles — the RST plugin has `:php:class:` etc., but Markdown only
emits raw `<a href="...">` from a CommonMark `Link` node. A
`[Router](xref:Gruven\PhpBotGram\Dispatcher\Router)` would render as a
broken link.

Phase 10 uses **relative paths** instead:

```markdown
The dispatcher delegates to
[`Router::propagateEvent`](../../classes/Gruven-PhpBotGram-Dispatcher-Router.html#method_propagateEvent).
```

The `../../` count depends on the page depth under `guide/`. To keep
this manageable:

- A markdown helper, `docs/guide/shared/api-link.md` (or a Twig partial
  picked up by the markdown extension if available), defines link
  conventions and constants.
- `scripts/check-docs-links.php` (described in §Components) walks every
  rendered HTML page in `build/docs/api/guide/`, extracts every `href`
  pointing back into the build, and verifies the target file exists.
  Broken relative links are CI-blocking.
- phpDocumentor template stability: API page filenames follow the
  pattern `classes/<Namespace-With-Dashes>-<ClassName>.html`. This
  pattern is locked by the default template; a future phpDocumentor
  template change would force a path-rewrite migration, which we
  accept as a known release-engineering tax.

### CI gate strategy (the spec's hard claim about "build fails on warning" relies on this)

`vendor/bin/phpdoc` returns exit code `0` even with hundreds of
unresolved references or render warnings. The `--fail-on=*` CLI flag
does not exist. The Phase 10 build pipeline therefore wraps phpdoc:

```bash
# composer script "docs-api"
set -e
envsubst < phpdoc.dist.xml.tpl > phpdoc.dist.xml
vendor/bin/phpdoc -c phpdoc.dist.xml --log=build/docs/build.log 2>&1 | tee build/docs/build.out
php scripts/check-docs-build-log.php build/docs/build.out build/docs/build.log
php scripts/lint-docs.php
php scripts/check-docs-links.php
php scripts/check-docs-examples.php
```

`scripts/check-docs-build-log.php` greps `build.out` (and `build.log`
if phpdoc wrote one — empirically the file is created only when there
is content to write) for `WARN`, `ERROR`, `NOTICE: Reference`, and the
specific "Could not resolve" patterns the guides resolver emits via
`logger->warning(...)`. Non-empty match → exit non-zero.

### Code examples (hybrid pattern)

Two presentation styles used together:

1. **Inline snippet** — fenced ```` ```php ```` block. Used for short
   conceptual illustrations (3–15 lines). Validated by
   `scripts/lint-docs.php`:
   - Extract each fenced `php` block.
   - **Prepend `<?php\n`** if not already present (a fenced
     `php` block is usually a fragment, not a complete file).
   - Write to a temp file. `php -l` it. Aggregate errors.
   - A snippet that is intentionally a class-body fragment (cannot be
     linted standalone) uses the fence tag `php-fragment` instead of
     `php` — `lint-docs.php` skips those, and a checklist item in the
     manual review covers them.
2. **Full runnable bot** — link to a real file in `examples/`:

   ```markdown
   See the [full example](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot.php).
   ```

   Validated by `scripts/check-docs-examples.php`: every link of the
   shape `examples/<name>.php` (relative or absolute github URL) must
   resolve to an existing file under `examples/` in the working tree.

### Versioning + deploy on `gh-pages` (branch mode)

Phase 9 currently uses Pages **workflow mode**
(`actions/upload-pages-artifact@v3` + `actions/deploy-pages@v4`), which
treats every workflow run as a full-site replacement. That model is
incompatible with multi-version directories. Phase 10 migrates to
Pages **branch mode**:

1. **Manual config change** (recorded in the Phase 10 implementation
   plan): in repo settings → Pages, switch "Source" from "GitHub
   Actions" to "Deploy from a branch", branch `gh-pages`, folder `/`.
2. **Rewrite Phase 9's `docs.yml`** to push the rendered site to a
   subdirectory of an orphan `gh-pages` branch via
   `peaceiris/actions-gh-pages@v4` (current major). `peaceiris`'s
   `keep_files: true` preserves directories that the current run did
   not touch, so a `master` push leaves tag releases intact and vice
   versa.
3. **Add `.github/workflows/docs-release.yml`** triggered on
   `push: tags: 'v*.*.*'`, publishing into `/en/<tag>/` and updating
   `/en/latest/`.

Branch layout on `gh-pages`:

```
gh-pages/
  index.html                 # JS-based fallback (see below)
  versions.json              # ordered version inventory
  languages.json             # ["en"] for now
  en/
    dev/                     # built from master
      index.html
      classes/...
      guide/...
    latest/                  # full copy of newest tag; missing until v0.1.0
    v0.1.0/                  # built from tag (first one missing pre-release)
```

`gh-pages/index.html` is **not** a fixed `<meta http-equiv="refresh">`
redirect. It is a tiny inline-JS page that:

1. Fetches `/versions.json`.
2. Picks the first stable version, or falls back to `"dev"` if none.
3. `window.location.replace(...)` to that path.

This works before `v0.1.0` exists: visitors are redirected to
`/en/dev/` until the first tag lands.

`versions.json` schema:

```json
{
  "versions": [
    { "id": "v0.1.0",  "label": "v0.1.0 (latest)", "path": "en/v0.1.0/", "stable": true },
    { "id": "dev",     "label": "dev (master)",    "path": "en/dev/",    "stable": false }
  ]
}
```

`languages.json` schema:

```json
{ "languages": [ { "id": "en", "label": "English" } ] }
```

Each workflow:

1. Checkout repo on the appropriate ref.
2. `composer install --no-interaction --no-progress --prefer-dist`.
3. `VERSION=<dev|vX.Y.Z> composer docs-api` (runs envsubst + phpdoc +
   all post-build gates).
4. Checkout `gh-pages` into a worktree.
5. `rsync -a --delete build/docs/api/ <worktree>/en/<VERSION>/`.
6. **Tag workflow only:** `rsync -a --delete build/docs/api/
   <worktree>/en/latest/`; update `versions.json` (atomic write).
7. `peaceiris/actions-gh-pages@v4` with `keep_files: true`,
   `publish_branch: gh-pages`, `publish_dir: <worktree>`.

**Concurrency:** distinct groups per workflow.

```yaml
# docs.yml (master push)
concurrency:
  group: pages-dev
  cancel-in-progress: true

# docs-release.yml (tag push)
concurrency:
  group: pages-release
  cancel-in-progress: false   # do not cancel a mid-rsync release
```

A `master` push can still race against a `tag` push of an older
ref since they use different groups. Race tolerance:
`peaceiris/actions-gh-pages@v4` does a `git pull --rebase` before
push and retries on non-fast-forward, so the two workflows can
serialise at the branch level even without group coordination.

### Version + language switcher: template override (not HTML rewriting)

Post-build HTML rewriting is fragile (phpDocumentor template HTML
shape can change between point releases) and full of edge cases
(asset paths, base hrefs, idempotence). Instead, Phase 10 uses
phpDocumentor's first-class **template override** mechanism:

`phpdoc.dist.xml.tpl` gains a `<template>` entry pointing at
`docs/template-override/`. The override directory mirrors the
phpDocumentor default template structure for **only** the files we
modify (typically `base.html.twig` + a new `_includes/switcher.html.twig`).

```xml
<version number="${VERSION}">
  <api format="php">…</api>
  <guide format="md" output="guide">…</guide>
  <template name="default" location="./docs/template-override/" />
</version>
```

The Twig override loads `/versions.json` and `/languages.json` at
runtime via vanilla JS (the same fetch-and-populate logic that would
have lived in the rewrite script), and renders two `<select>`
elements in the existing navbar. No HTML rewriting.

**Maintenance burden** of template override is acknowledged: on each
phpDocumentor major bump, the override file must be diff'd against the
new upstream default to catch structural drift. CI runs `phpdoc
--version` against the pinned shim and a developer-readable diff check
is documented in §Components.

### i18n: foundation, not feature

The Markdown source lives under `docs/guide/en/`. The build config
has one `<guide>` block targeting `docs/guide/en`. The output lands at
`build/docs/api/guide/` and is rsynced to `gh-pages/en/<version>/`.

**Adding a Russian translation later requires:**

1. Author `docs/guide/ru/` mirroring `en/`'s tree.
2. Decide on a per-language output strategy:
   - Option A: **two phpdoc invocations**, one per language, each
     emitting to a separate `paths.output` (e.g.
     `build/docs/en/` and `build/docs/ru/`); rsync each to its own
     `/<lang>/<version>/` path.
   - Option B: keep one phpdoc invocation but switch to phpDocumentor's
     multi-`<version>` support, with each `<version>` carrying a
     different language label (verify behaviour first).
3. Update `languages.json` to add `ru`.
4. Update `versions.json` if some versions only exist in one language
   (status flag).
5. Update the switcher Twig override to gracefully handle "this page
   has no `ru` translation" — link disabled or fall through to `en`
   with a banner.
6. Decide on translation freshness policy (none in this spec).

**i18n is therefore "ready" in the sense that the source layout will
not need to be moved.** It is *not* ready in the sense that adding
RU is a one-line config change. The Non-goals section lists this
honestly.

Shared, language-agnostic assets live at `docs/guide/shared/`
(diagrams, SVG, language-neutral PHP fragments). They are referenced
from `en/` (and later `ru/`) via relative paths.

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
      dependency-injection.md         # extra (cookbook gap fill)
      callback-answer.md              # extra (cookbook gap fill)
      chat-action-typing.md           # extra (cookbook gap fill)
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
      architecture-decisions.md       # phpbotgram-specific; documents divergences from aiogram
    reference/
      index.md                        # stub linking to ../classes/ (API)
    changelog.md                      # copy of CHANGELOG.md (see below)
    contributing.md                   # copy of CONTRIBUTING.md (see below)
    migration/
      .gitkeep                        # reserved for future breaking changes
  shared/
    assets/
      diagrams/                       # SVG/PNG, language-agnostic
      code-snippets/                  # *.php fragments, included into .md
```

**Page count (honest):** 5 tutorial + 18 how-to + 15 concepts + 4 Diataxis
index pages + landing + changelog + contributing + reference-stub +
migration-stub = **46 pages**, fitting "~40–50".

**`docs/guide/en/changelog.md` and `contributing.md`:** Markdown has
no `include::` directive. Phase 10 introduces a pre-build step in
`composer docs-api` that copies the project-root `CHANGELOG.md` and
`CONTRIBUTING.md` into `docs/guide/en/changelog.md` and
`contributing.md` respectively, prepending a short front-matter (Twig
template header used by the override). The copied files are
`.gitignore`d to preserve the single-source. The pre-build copy
happens after `envsubst` and before `phpdoc`. (`CONTRIBUTING.md` does
not yet exist at the project root and is created as part of Phase 10
scope.)

**`docs/superpowers/` exclusion guard:** `phpdoc.dist.xml.tpl`'s
`<guide><source>` points at `docs/guide/en` exactly. `docs/superpowers/`
(specs + plans, internal-only) is never scanned. State this explicitly
to prevent future "let's just point at docs/" simplifications.

## Components

| Component | Purpose | Lives at |
| --- | --- | --- |
| `docs/guide/en/` Markdown tree | Narrative content source | repo |
| `docs/guide/shared/` | Language-agnostic SVG/PHP fragments | repo |
| `phpdoc.dist.xml.tpl` | Versionable template (envsubst → `phpdoc.dist.xml`) | repo |
| `phpdoc.dist.xml` | Rendered config (gitignored) | local + CI scratch |
| `docs/template-override/` | Twig overrides for navbar switcher | repo |
| `scripts/lint-docs.php` | Extract `\`\`\`php` blocks (auto-prepend `<?php\n`) → `php -l` | repo |
| `scripts/check-docs-links.php` | Verify every relative href in `build/docs/api/guide/*.html` resolves | repo |
| `scripts/check-docs-examples.php` | Verify every `examples/*.php` link is real | repo |
| `scripts/check-docs-build-log.php` | Grep `build.out`/`build.log` for WARN/ERROR/NOTICE | repo |
| `scripts/copy-root-docs.php` | Copy CHANGELOG/CONTRIBUTING to `docs/guide/en/` pre-build | repo |
| `versions.json`, `languages.json` | Switcher inventory | `gh-pages` |
| `.github/workflows/docs.yml` | (Migrated from Phase 9) master → `/en/dev/` | repo |
| `.github/workflows/docs-release.yml` | tag → `/en/<tag>/` + `latest/` | repo |
| `composer docs-api` / `make docs-api` | Local build entry point | repo |
| `CONTRIBUTING.md` | Created in Phase 10 if not present | repo root |

## Data flow

```
master push
    │
    ▼
docs.yml workflow  [MIGRATED in Phase 10 from workflow mode → branch mode]
    ├─ composer install
    ├─ composer docs-api:
    │   ├─ VERSION=dev envsubst phpdoc.dist.xml.tpl → phpdoc.dist.xml
    │   ├─ scripts/copy-root-docs.php  (CHANGELOG/CONTRIBUTING → docs/guide/en/)
    │   ├─ vendor/bin/phpdoc -c phpdoc.dist.xml --log=build/docs/build.log 2>&1 \
    │   │     | tee build/docs/build.out
    │   ├─ scripts/check-docs-build-log.php   (gate)
    │   ├─ scripts/lint-docs.php              (gate)
    │   ├─ scripts/check-docs-links.php       (gate)
    │   ├─ scripts/check-docs-examples.php    (gate)
    │   └─ markdownlint-cli2 docs/guide/en    (gate)
    └─ peaceiris/actions-gh-pages@v4
        publish_branch=gh-pages
        publish_dir=build/docs/api
        destination_dir=en/dev
        keep_files=true

tag push v0.1.0
    │
    ▼
docs-release.yml workflow
    ├─ (same build steps with VERSION=v0.1.0)
    ├─ rsync build/docs/api/ → gh-pages/en/v0.1.0/ via peaceiris
    ├─ rsync build/docs/api/ → gh-pages/en/latest/  via peaceiris (separate run)
    ├─ scripts/update-versions-json.php inside the gh-pages worktree
    └─ peaceiris commit + push
```

## Error handling

| Failure | Detected by | Behaviour |
| --- | --- | --- |
| envsubst produces literal `${VERSION}` because var unset | `set -u` in composer script | Build fails before phpdoc |
| Markdown syntax error | `phpdoc` stderr (writes WARN/ERROR) → `scripts/check-docs-build-log.php` | Build fails |
| Broken cross-link / unresolved reference | `phpdoc` stderr (logger->warning) → `scripts/check-docs-build-log.php`; also `scripts/check-docs-links.php` post-build walk | Build fails |
| `\`\`\`php` block fails `php -l` | `scripts/lint-docs.php` (with `<?php\n` prepended) | Build fails |
| Linked `examples/X.php` missing | `scripts/check-docs-examples.php` | Build fails |
| Markdown style violation | `markdownlint-cli2` | Build fails |
| GitHub Pages source not in branch mode | First Phase 10 deploy run: site does not update | Implementation plan flags this as a one-time manual config |
| `gh-pages` push race | `peaceiris/actions-gh-pages@v4` rebases and retries; release workflow uses `cancel-in-progress: false` | Self-healing for typical races |
| `versions.json` corrupted by overlapping releases | `scripts/update-versions-json.php` uses atomic write + lock-file | Self-healing |
| phpdoc CLI flag removed in a future version | `make docs-api` exits with the upstream error | Implementer addresses on next phpdoc bump |

Author-facing errors are surfaced as PR-blocking CI failures. The
spec's intent of "no silent build-time degradation" is implemented by
post-build scripts rather than a phpdoc flag (because that flag does
not exist).

## Testing strategy

### Per-PR (gate)

1. `composer test` — existing unit test suite still passes.
2. `make coverage-gate` — per-module floors still met.
3. `composer docs-api` — runs the full pipeline (build + all gates).
4. The pipeline itself contains 6 gates (build log, php-l, link
   resolver, example resolver, markdownlint, plus the build-step
   error code).

### Per-release

5. The published `/en/<tag>/index.html` returns HTTP 200.
6. Spot-check: navbar switcher template-override loaded the new
   version.
7. Spot-check: API reference within the new version still works.
8. `versions.json` lists the new entry.

### Manual content review checklist (per page)

- **tutorial/**: contains either a runnable snippet (linted) or a
  `[full example](examples/X.php)` link that points to a real file
  under `examples/`.
- **concepts/**: ≥ 1 hyperlink into the API reference (i.e. an `href`
  to a `../../classes/Gruven-PhpBotGram-...html` path).
- **how-to/**: starts with "When to use this", ends with "Pitfalls".
- **index.md**: 4 Diataxis quadrants linked, each with a one-sentence
  hook.
- Fenced blocks tagged `php-fragment` are eye-reviewed (not auto-linted).

No content-quality automation in scope; reviewer judgment covers
grammar, voice, and accuracy.

## Definition of done

- `docs/guide/en/` populated per the content tree (5 + 18 + 15 + stubs
  = 46 pages).
- `phpdoc.dist.xml.tpl` produces a single site combining narrative +
  API reference, with working hyperlinks between them.
- `docs/template-override/` ships the navbar switcher; phpdoc loads it
  successfully.
- `.github/workflows/docs.yml` migrated to branch-mode and publishes
  master push to `/en/dev/`.
- `.github/workflows/docs-release.yml` publishes tag push to
  `/en/<tag>/` + `/en/latest/`.
- Repo Pages settings switched to "Deploy from branch: gh-pages /"
  (one-time manual step, documented in the implementation plan).
- `https://gruven.github.io/phpbotgram/en/dev/index.html` renders
  with navbar showing language + version switchers (currently `[en]`
  and `[dev]` entries only).
- All six pipeline gates green on the Phase 10 merge commit.
- `CHANGELOG.md` Phase 10 entry summarises the new docs surface.
- `CONTRIBUTING.md` created (if not already present) so the copy step
  succeeds.

**Decoupled from `v0.1.0` tagging:** if Phase 9's task 9.5 has not
fired by Phase 10 merge time, `/en/latest/` and `/en/v0.1.0/` will not
yet exist on `gh-pages`. The JS-based `gh-pages/index.html` falls
through to `/en/dev/` cleanly. The release workflow stays dormant
until the first `v*.*.*` tag push.

## Out-of-scope follow-ups (not Phase 10)

- Russian translation (Phase 11+) — see §i18n for what adding RU
  entails.
- Sphinx-style search index / `searchindex.json` (Phase 11+).
- Mobile-optimised theme variant (Phase 11+).
- Per-PR preview deploys (e.g. PR-specific subdirectory on gh-pages).
- Auto-generated changelog → release-notes page.
- Live "try it" sandbox.
- Cleanup automation for old version directories (manual `git rm` is
  fine until the gh-pages branch grows past a few dozen versions; at
  that point a `make prune-old-docs` placeholder lands).

## Open questions

None at design time. All toolchain claims are mechanically verified
against the v3.10.0 phar bundled by `phpdocumentor/shim ^3` and
against the existing Phase 9 workflow.

## References

- Diataxis methodology: <https://diataxis.fr>
- Aiogram docs (visual + structural reference):
  <https://docs.aiogram.dev/en/v3.28.2/>
- phpDocumentor v3 Guides component:
  <https://github.com/phpDocumentor/guides>
- `phpdocumentor/guides-markdown`:
  <https://github.com/phpDocumentor/guides-markdown>
- `peaceiris/actions-gh-pages@v4`:
  <https://github.com/peaceiris/actions-gh-pages>
- Phase 9 (API documentation pipeline this design extends):
  `docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md` §
  Phase 9; `.github/workflows/docs.yml`; `phpdoc.dist.xml`.
