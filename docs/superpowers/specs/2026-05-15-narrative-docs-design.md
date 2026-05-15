# phpbotgram narrative documentation — design

> Status: draft, ready for review.
> Outcome: a docs site modelled after <https://docs.aiogram.dev/en/v3.28.2/>,
> served at `https://gruven.github.io/phpbotgram/en/<version>/`, integrated
> with the phpDocumentor v3 API reference shipped in Phase 9.

## Goals

1. **Narrative parity with aiogram 3.28** — every concept a Telegram-bot
   author needs to ship a production bot is explained in prose, with
   examples, and linked to the API reference.
2. **Diataxis structure** — clear separation of tutorials, how-to
   recipes, concept explanations, and reference (API). Newcomers,
   integrators, and maintainers each have a primary entry point.
3. **Single-tool pipeline** — one `composer docs-api` invocation builds
   both the narrative and the API reference into the same output tree.
4. **Per-version site** — `/en/dev/`, `/en/v0.1.0/`, `/en/latest/`, all
   live concurrently on `gh-pages` with a navbar switcher.
5. **i18n-foundation, not i18n-now** — the file layout slots a future
   `ru/` mirror in without restructuring; adding RU still requires the
   handful of explicit code changes documented in §i18n.
6. **CI-enforced quality (post-build, not phpdoc-native)** — broken
   cross-links, syntax-invalid PHP snippets, dead example links, and
   markdown-lint violations fail the build. Because phpDocumentor v3
   itself never sets a non-zero exit on doc-quality issues, Phase 10
   implements every doc-quality gate as a post-build script that
   inspects the build output stream and the rendered HTML.

## Non-goals (deliberate scope cuts)

- Russian (or any other non-English) translation in this phase. The
  source layout reserves the slot; content is English-only. See §i18n
  for what adding RU actually entails.
- Sphinx-style internationalisation tooling (`.po` files, gettext
  message extraction, translation memory).
- Migration guide (`migration_2_to_3.rst` equivalent). `v0.1.0` is the
  first release; nothing to migrate from. A `migration/` directory is
  reserved with a `.gitkeep` placeholder for future breaking changes.
- Read the Docs hosting. We control the deploy pipeline through
  GitHub Actions + `gh-pages`.
- Auto-generated API tutorials. Method/type pages remain in
  phpDocumentor's auto-generated output.
- Watch-mode local dev server. `composer docs-api && open
  build/docs/api/index.html` is the dev loop.
- Screenshots or video walkthroughs.
- Live Telegram API calls during doc build.
- Cookbook coverage of class-based handlers and `MagicData` (deferred
  to a Phase 11 cookbook expansion; not ship-blocking for v0.1).

## Architecture

### Phase 9 dependencies the design relies on

Phase 9 already shipped:

- `phpdocumentor/shim ^3` (composer dev-dep) drops the official
  phpDocumentor v3.10 phar into `vendor/bin/phpdoc`. Phase 10 pins the
  shim to `~3.10` (composer-level guard) so a future shim major that
  bumps the phar doesn't silently break `<source dsn=".">` resolution
  or the navbar template (both are observed-working against v3.10.0 by
  building locally).
- `phpdoc.dist.xml` rendering API docs from `src/` into
  `build/docs/api/`.
- `composer docs-api` script + `make docs-api` Makefile target.
- `.github/workflows/docs.yml` publishing `build/docs/api/` to GitHub
  Pages via `actions/upload-pages-artifact@v3` +
  `actions/deploy-pages@v4` (Pages "workflow mode").

Phase 10 explicitly **migrates** the Phase 9 workflow from Pages
"workflow mode" to **Pages branch mode** so multiple versions can
coexist on a single `gh-pages` branch (see §Versioning + deploy).

### Build pipeline (single tool, two content roots)

Phase 10 extends `phpdoc.dist.xml` with a `<guide>` block so the same
phpDocumentor phar renders narrative Markdown alongside the API
reference. Because the `<version number="…">` value must change
between dev and tag builds, the file becomes a **template**
(`phpdoc.dist.xml.tpl`, committed); the rendered `phpdoc.dist.xml` is
gitignored.

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
  <!-- Template overrides live at .phpdoc/template/ (next to this XML),
       auto-discovered by phpDocumentor's
       ProvideTemplateOverridePathMiddleware. <template> declarations
       belong at THIS top level per the v3 XSD (versionType is a
       sequence of folder?, api*, guide* — no template child). -->
</phpdocumentor>
```

Notes verified against the v3.10.0 phar:

- `<template>` is a sibling of `<version>`, not a child. The XSD at
  `phar://.../data/xsd/phpdoc.xsd` defines `versionType` as a sequence
  of `folder?`, `api*`, `guide*` only.
- phpDocumentor auto-loads template overrides from
  `.phpdoc/template/` resolved relative to the **config file's
  directory** (`ProvideTemplateOverridePathMiddleware::PATH_TO_TEMPLATE_OVERRIDES`).
  No XML element wires this up; the path is the only auto-discovered
  location. Phase 10 puts overrides at `.phpdoc/template/` next to
  `phpdoc.dist.xml.tpl` (project root).
- `<source dsn=".">` matches the Phase 9 working file exactly; verified
  buildable.

`${VERSION}` is substituted via `envsubst < phpdoc.dist.xml.tpl >
phpdoc.dist.xml` immediately before each phpdoc invocation:

- Locally: `0.1.0-dev` (Makefile / composer script default).
- CI (master push): `dev`.
- CI (tag push `vX.Y.Z`): `vX.Y.Z`.

`envsubst` is part of `gettext` (preinstalled on `ubuntu-latest`
runners and on most dev machines via `brew install gettext`). The
build entry point is a shell wrapper (`scripts/build-docs.sh`, see
below) — composer-script-inlined pipelines are avoided to keep shell
semantics consistent across systems.

### Output tree (after build)

```
build/docs/api/
  index.html             # phpDocumentor landing (covers API)
  classes/...            # API: per-class pages
  namespaces/...
  files/...
  guide/                 # narrative (from <guide output="guide">)
    index.html
    tutorial/...
    how-to/...
    concepts/...
    reference/...
    changelog.html
    contributing.html
```

`<base href="...">` injection: phpDocumentor v3 emits a `<base
href="../">` (or similar relative tag) in every rendered page. This
re-frames every relative URL inside the page. **Every relative
hyperlink in narrative Markdown must therefore be authored relative to
the `<base>` target, not the source page's directory.** Concretely,
from `docs/guide/en/concepts/dispatcher.md` (rendered to
`build/docs/api/guide/concepts/dispatcher.html` with `<base
href="../">`), the path to a class page is `classes/Foo-Bar.html`
— not `../classes/Foo-Bar.html` or `../../classes/Foo-Bar.html`. The
link checker (see §Components) is `<base href>`-aware to enforce this.

### Cross-references (guide → API)

**No symbolic `xref:` shortcut.** phpDocumentor v3.10's Markdown
plugin (`phpdocumentor/guides-markdown`) does not implement reference
text roles — only the RST plugin has `:php:class:` and friends.
Markdown emits raw `<a href="...">` from a CommonMark `Link` node, so
`[Router](xref:Gruven\PhpBotGram\Dispatcher\Router)` would render as
a broken anchor.

Phase 10 uses **plain relative hyperlinks** instead:

```markdown
The dispatcher delegates to
[`Router::propagateEvent`](classes/Gruven-PhpBotGram-Dispatcher-Router.html#method_propagateEvent).
```

Authoring convention (relative to the rendered page's `<base href>`):

- API class:
  `classes/Gruven-PhpBotGram-<Namespace>-<Class>.html`
- API method:
  `classes/...-<Class>.html#method_<name>`
- Another guide page:
  `guide/<diataxis>/<page>.html` (or just
  `<diataxis>/<page>.html` from inside `guide/`).

API page filename pattern is the phpDocumentor default template
contract (`<Namespace-with-dashes>-<ClassName>.html`). If a future
phpdoc bump changes that pattern, the link checker fails loudly across
every guide page — that's the migration trigger.

### CI gate strategy

phpDocumentor v3.10 returns exit code `0` even with unresolved
references or render warnings. The `--fail-on=*` CLI flag does not
exist (verified via `vendor/bin/phpdoc --help` and exhaustive grep of
the phar). The `--log=PATH` flag exists but **does not produce a file
when the run has nothing to log** — verified empirically: a successful
build leaves no log file at the requested path.

Phase 10 therefore drops `--log=` and instead captures stderr+stdout
into a build-output file, then post-processes:

```bash
# scripts/build-docs.sh  (bash, set -euo pipefail)
mkdir -p build/docs
: "${VERSION:?VERSION env var must be set}"
envsubst < phpdoc.dist.xml.tpl > phpdoc.dist.xml
php scripts/copy-root-docs.php
vendor/bin/phpdoc -c phpdoc.dist.xml 2>&1 \
  | tee build/docs/build.out
php scripts/check-docs-build-log.php build/docs/build.out
php scripts/lint-docs.php
php scripts/check-docs-links.php
php scripts/check-docs-examples.php
npx markdownlint-cli2 'docs/guide/en/**/*.md'
```

The `composer docs-api` script invokes `scripts/build-docs.sh`. `set
-euo pipefail` makes `tee`'s zero exit code not mask a phpdoc crash.

**Grep patterns** for `scripts/check-docs-build-log.php`:

phpDocumentor's default-verbosity unresolved-reference message is
literally `Reference <ref> could not be resolved in <doc>` — observed
in `vendor/phpdocumentor/guides/src/ReferenceResolvers/ReferenceResolverPreRender.php`.
The implementation plan's first step is a **pilot pass**: deliberately
introduce a broken cross-link, capture `build.out`, and pin the exact
substring set into `check-docs-build-log.php` constants. Initial
candidate patterns (to be validated by pilot, not blind-trusted):

- `could not be resolved`
- ` ERROR ` (Monolog's default formatter inserts spaces around the
  level keyword)
- ` CRITICAL `
- ` WARNING ` — pilot will decide whether to gate on warnings or only
  errors; default initial choice is to gate on warnings.

The script exits non-zero on any non-empty match.

### Code examples (hybrid pattern)

Two presentation styles used together:

1. **Inline snippet** — fenced ```` ```php ```` block. Used for short
   conceptual illustrations (3–15 lines). Validated by
   `scripts/lint-docs.php`:

   Algorithm:
   ```
   for each *.md file under docs/guide/en/:
     for each fenced block whose info string starts with "php":
       if info string is "php-fragment": skip (eye-review only)
       trim leading whitespace from the block content
       if not starts_with("<?php"): prepend "<?php\n"
       write to a temp file
       run `php -l <tempfile>`
       on parse error: record (file, line, error)
   exit 1 if any errors recorded; print summary
   ```

   The `php-fragment` tag is for class-body fragments (cannot be
   linted standalone). Note that GitHub web view does not syntax-
   highlight unknown fence tags — `php-fragment` blocks render as
   plain code on github.com but get full highlighting in the rendered
   docs site. Authors should prefer `php` whenever possible.

2. **Full runnable bot** — link to a real file in `examples/`:

   ```markdown
   See the [full example](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot.php).
   ```

   Validated by `scripts/check-docs-examples.php`: every link of the
   shape `examples/<name>.php` (relative or absolute github URL) must
   resolve to an existing file under `examples/` in the working tree.

### Versioning + deploy on `gh-pages` (branch mode)

Phase 9 currently uses Pages **workflow mode**
(`actions/upload-pages-artifact@v3` + `actions/deploy-pages@v4`),
which treats every workflow run as a full-site replacement. That
model is incompatible with multi-version directories. Phase 10
migrates to Pages **branch mode**.

**One-time setup tasks** (recorded explicitly in the Phase 10
implementation plan):

1. **Repo Pages settings:** GitHub → repo settings → Pages → "Source"
   changed from "GitHub Actions" to "Deploy from a branch", branch
   `gh-pages`, folder `/`. This is a UI-only step; document it in the
   plan.
2. **Bootstrap the orphan branch** before any workflow first runs:
   ```bash
   git checkout --orphan gh-pages
   git rm -rf .
   echo '<html><body>bootstrap</body></html>' > index.html
   git add index.html && git commit -m "Init gh-pages"
   git push origin gh-pages
   git checkout master
   ```
   `peaceiris/actions-gh-pages@v4` with `keep_files: true` requires
   the branch to exist; without bootstrap the first workflow run
   fails.
3. **Rename Phase 9's `concurrency.group`** from `pages` to
   `pages-write` (see below).

**Rewrite `docs.yml`** to push to a subdirectory of the orphan
`gh-pages` branch via `peaceiris/actions-gh-pages@v4`. Replace the
Phase 9 actions:

- Remove `actions/upload-pages-artifact@v3` and
  `actions/deploy-pages@v4`.
- Add `peaceiris/actions-gh-pages@v4` with:
  - `publish_branch: gh-pages`
  - `publish_dir: build/docs/api`
  - `destination_dir: en/dev`
  - `keep_files: true`
- Remove the `environment: github-pages` block (Pages branch mode
  doesn't use it).

**Add `.github/workflows/docs-release.yml`** triggered on `push: tags:
'v*.*.*'`, publishing into `/en/<tag>/` plus updating `/en/latest/`.

Branch layout on `gh-pages`:

```
gh-pages/
  index.html                 # JS fallback (see below)
  versions.json
  languages.json             # ["en"] for now
  en/
    dev/                     # built from master
      index.html
      classes/...
      guide/...
    latest/                  # full copy of newest tag; missing until v0.1.0
    v0.1.0/                  # built from tag (missing pre-release)
```

`gh-pages/index.html` is a small inline-JS page that:

1. Fetches `/versions.json`.
2. Picks the first version flagged `stable: true`, or falls back to
   `"dev"` if none exists yet.
3. `window.location.replace(...)` to that path.

This works before `v0.1.0`: visitors are redirected to `/en/dev/`
until the first tag lands.

`versions.json` schema:

```json
{
  "versions": [
    { "id": "v0.1.0",  "label": "v0.1.0 (latest)", "path": "en/v0.1.0/", "stable": true  },
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
3. `VERSION=<dev|vX.Y.Z> composer docs-api` (envsubst + phpdoc + all
   post-build gates via `scripts/build-docs.sh`).
4. `peaceiris/actions-gh-pages@v4` publishes `build/docs/api/` to
   `en/<VERSION>/`. The action's `keep_files: true` preserves other
   version directories that the current run did not touch.
5. **Tag workflow only:** a second `peaceiris/actions-gh-pages@v4`
   step publishes the same `build/docs/api/` to `en/latest/`; a third
   step runs `scripts/update-versions-json.php` on a `gh-pages`
   worktree to append the new tag.

**Concurrency strategy** (single shared group, queued not cancelled):

```yaml
# both docs.yml and docs-release.yml
concurrency:
  group: pages-write
  cancel-in-progress: false
```

`peaceiris/actions-gh-pages@v4` does not auto-rebase on push
conflict. With a single shared group and no cancellation, a tag push
during a master push queues behind the master push and runs after it
completes. The trade-off: a slow build delays releases. Acceptable
for v0.1 release cadence.

If a race still occurs (e.g. manual reruns), the failing workflow
must be re-run by hand — the spec does not promise auto-recovery.

Old versions never auto-delete; manual cleanup via direct `gh-pages`
commit if it ever matters.

### Version + language switcher: template override via `.phpdoc/template/`

Post-build HTML rewriting is fragile (phpDocumentor template HTML
shape can change between point releases). Phase 10 uses
phpDocumentor's **template-override** mechanism, which is auto-loaded
from `.phpdoc/template/` relative to the config file's directory
(verified in `ProvideTemplateOverridePathMiddleware::PATH_TO_TEMPLATE_OVERRIDES`).

Phase 10 ships `.phpdoc/template/` at the project root, containing
**only** the Twig files we need to override (typically
`base.html.twig` + a new `_includes/switcher.html.twig`). Files not
overridden compose from the upstream default template.

The switcher partial:

- Renders two `<select>` elements in the navbar.
- Inline-`<script>` fetches `/versions.json` and `/languages.json` at
  page load.
- Populates options, marks the current selection, disables
  unavailable cross-products (e.g. ru/v0.1.0/ before RU is
  translated).
- Resolves the runtime path correctly under `<base href>` by using
  absolute `/` URLs for the JSON fetches (the JSON files live at the
  `gh-pages` root, not inside any version directory).

**Maintenance burden:** on each phpDocumentor major bump, diff
`.phpdoc/template/` against the new upstream `data/templates/default/`
inside the new phar to catch structural drift. CI does not enforce
this automatically; it's a release-engineering checklist item flagged
when `phpdocumentor/shim` is bumped.

### i18n: foundation, not feature

The Markdown source lives under `docs/guide/en/`. The build config
has one `<guide>` block targeting `docs/guide/en`. The output lands at
`build/docs/api/guide/` and is rsynced to `gh-pages/en/<version>/`.

**Adding a Russian translation later requires (exhaustive list):**

1. Author `docs/guide/ru/` mirroring `en/`'s tree.
2. **Per-language phpdoc invocation strategy.** Building two `<guide>`
   blocks under one `<version>` would conflict on `output="guide"`.
   The chosen strategy: invoke phpdoc twice (once per language) into
   separate `paths.output` (e.g. `build/docs/en/` and
   `build/docs/ru/`). Each invocation uses a per-language
   `phpdoc.<lang>.dist.xml.tpl` rendered from the same source via
   envsubst with a `${LANG}` variable. Rsync each output to its own
   `/<lang>/<version>/` path on `gh-pages`.
3. **Per-language search index.** phpDocumentor's default template
   builds `js/searchIndex.js` per render. With two invocations there
   are two indexes; the navbar switcher must load the correct one for
   the current page (the switcher already knows the active language).
4. **Default-language redirect.** Today `gh-pages/index.html`
   redirects to a version. When RU lands, it must first pick a
   language (browser `navigator.language`-aware, falling back to
   `en`), then a version.
5. Update `languages.json` to add `ru`.
6. Update `versions.json` if some versions only exist in one language
   (status flag).
7. Update the switcher Twig override to gracefully handle "this page
   has no `ru` translation" — link disabled or fall through to `en`
   with a banner.
8. **Asset paths under `<base href>` from RU pages** mirror the EN
   conventions; `docs/guide/shared/` references remain identical.
9. Decide on translation-freshness policy (none in this spec).
10. If a sitemap is added later (currently out of scope), update it
    to enumerate both languages.

**i18n is "ready" only in the sense that the EN source layout does
not need to be moved when RU lands.** It is *not* a one-config-change
add. The §Non-goals section lists this honestly.

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
      dependency-injection.md
      callback-answer.md
      chat-action-typing.md
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
      architecture-decisions.md       # phpbotgram-specific; divergences from aiogram
    reference/
      index.md                        # stub linking to ../../classes/ (API)
    changelog.md                      # generated copy of CHANGELOG.md (gitignored)
    contributing.md                   # generated copy of CONTRIBUTING.md (gitignored)
    migration/
      .gitkeep                        # reserved for future breaking changes; empty by design
  shared/
    assets/
      diagrams/                       # SVG/PNG, language-agnostic
      code-snippets/                  # *.php fragments, included into .md
```

**Page count (honest):** 5 tutorial + 18 how-to + 15 concepts + 4
Diataxis index pages + top-level landing + changelog + contributing +
reference-stub = **44 pages**. Plus a `.gitkeep` placeholder in
`migration/` (phpDocumentor scans the directory but emits nothing
from a hidden file — harmless).

**`docs/guide/en/changelog.md` and `contributing.md`:** Markdown has
no `include::` directive. `scripts/copy-root-docs.php` runs early in
`scripts/build-docs.sh` (after envsubst, before phpdoc) and copies
the project-root `CHANGELOG.md` and `CONTRIBUTING.md` into
`docs/guide/en/`. Phase 10 scope includes:

- Adding two explicit `.gitignore` lines:
  ```
  /docs/guide/en/changelog.md
  /docs/guide/en/contributing.md
  ```
- Creating `CONTRIBUTING.md` at the project root with sections: how to
  open issues, PR workflow (branch from master, run `composer test`
  and `composer docs-api`), coding standards (PHPStan level 9 +
  php-cs-fixer + the project's existing tooling targets), commit
  message convention. The file is committed by Phase 10.
- Configuring `.markdownlint.jsonc` so the copied files pass — either
  by including a permissive line-length rule for long-line changelogs
  or by excluding the two file paths from lint scope. Phase 10
  commits the config alongside the first lint run.

**`docs/superpowers/` exclusion guard:** the template's
`<guide><source>` points at `docs/guide/en` exactly. `docs/superpowers/`
(specs + plans, internal-only) is never scanned. This is stated
explicitly here to prevent future "let's just point at `docs/`"
simplifications.

## Components

| Component | Purpose | Lives at |
| --- | --- | --- |
| `docs/guide/en/` Markdown tree | Narrative content source | repo |
| `docs/guide/shared/` | Language-agnostic SVG/PHP fragments | repo |
| `phpdoc.dist.xml.tpl` | Versionable template (envsubst → `phpdoc.dist.xml`) | repo |
| `phpdoc.dist.xml` | Rendered config (gitignored) | local + CI scratch |
| `.phpdoc/template/` | Twig overrides for navbar switcher (auto-loaded by phpdoc) | repo |
| `.markdownlint.jsonc` | Lint config compatible with the copied changelog/contributing | repo |
| `scripts/build-docs.sh` | Bash wrapper: `set -euo pipefail`, envsubst, copy-root-docs, phpdoc, gates | repo |
| `scripts/copy-root-docs.php` | Copy CHANGELOG/CONTRIBUTING to `docs/guide/en/` pre-build | repo |
| `scripts/lint-docs.php` | Extract `\`\`\`php` blocks (auto-prepend `<?php\n`), `php -l` | repo |
| `scripts/check-docs-links.php` | `<base href>`-aware: verify every internal href in `build/docs/api/guide/**/*.html` resolves to an existing file/anchor; external links and `examples/` links explicitly out of scope (handled by the other checkers) | repo |
| `scripts/check-docs-examples.php` | Verify every `examples/*.php` link is real | repo |
| `scripts/check-docs-build-log.php` | Grep `build.out` for `could not be resolved`, ` ERROR `, ` CRITICAL `, ` WARNING ` (pilot-pinned) | repo |
| `scripts/update-versions-json.php` | Atomic `versions.json` updater used by the release workflow | repo |
| `versions.json`, `languages.json` | Switcher inventory | `gh-pages` root |
| `.github/workflows/docs.yml` | (Migrated from Phase 9) master → `/en/dev/` | repo |
| `.github/workflows/docs-release.yml` | tag → `/en/<tag>/` + `latest/` | repo |
| `composer docs-api` / `make docs-api` | Local build entry point (delegates to `scripts/build-docs.sh`) | repo |
| `CONTRIBUTING.md` | Created by Phase 10 | repo root |

### `scripts/check-docs-links.php` contract (sub-bullets)

- Walks every `*.html` under `build/docs/api/guide/`.
- Extracts every `href` attribute.
- Skips `http://` / `https://` / `mailto:` / fragment-only (`#…`)
  links (out of scope; checked separately by
  `scripts/check-docs-examples.php` for the `examples/*.php` subset).
- For each remaining link: resolves it against the page's
  `<base href>` (parsed from the same HTML), then joins with the
  rendered output directory, and verifies the target file exists.
- For links with an anchor (`#method_foo`), opens the target HTML and
  verifies the anchor `id` exists. Anchor IDs are part of the
  phpDocumentor template contract; broken anchors are CI-blocking and
  signal a template-format change worth investigating.

## Data flow

```
master push
    │
    ▼
docs.yml workflow  [MIGRATED in Phase 10 from workflow mode → branch mode]
    ├─ composer install
    ├─ composer docs-api  → scripts/build-docs.sh:
    │   ├─ envsubst phpdoc.dist.xml.tpl → phpdoc.dist.xml  (VERSION=dev)
    │   ├─ scripts/copy-root-docs.php  (CHANGELOG/CONTRIBUTING → docs/guide/en/)
    │   ├─ vendor/bin/phpdoc -c phpdoc.dist.xml 2>&1 | tee build/docs/build.out
    │   ├─ scripts/check-docs-build-log.php build/docs/build.out  (gate)
    │   ├─ scripts/lint-docs.php                                 (gate)
    │   ├─ scripts/check-docs-links.php                          (gate)
    │   ├─ scripts/check-docs-examples.php                       (gate)
    │   └─ markdownlint-cli2 'docs/guide/en/**/*.md'             (gate)
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
    ├─ peaceiris publishes build/docs/api/ → gh-pages/en/v0.1.0/
    ├─ peaceiris publishes build/docs/api/ → gh-pages/en/latest/
    └─ scripts/update-versions-json.php on a gh-pages worktree; commit + push
```

## Error handling

| Failure | Detected by | Behaviour |
| --- | --- | --- |
| `${VERSION}` env var unset | `: "${VERSION:?...}"` in `scripts/build-docs.sh` | Build fails before phpdoc |
| `tee` masks phpdoc crash | `set -euo pipefail` in `build-docs.sh` | pipefail propagates real exit code |
| Markdown syntax error / unresolved reference | `phpdoc` stderr (pattern: `could not be resolved`, ` ERROR `, ` WARNING `, ` CRITICAL ` — pilot-pinned) → `scripts/check-docs-build-log.php` | Build fails |
| Broken hyperlink between narrative pages or guide → API | `scripts/check-docs-links.php` post-build walk with `<base href>` resolution | Build fails |
| `\`\`\`php` block fails `php -l` | `scripts/lint-docs.php` with `<?php\n` prepended | Build fails |
| Linked `examples/X.php` missing | `scripts/check-docs-examples.php` | Build fails |
| Markdown style violation | `markdownlint-cli2` with `.markdownlint.jsonc` config (excludes copied changelog/contributing) | Build fails |
| Pages source not switched to branch mode | First Phase 10 deploy: visitors see stale Phase 9 artefact | Implementation plan flags this as a one-time UI step |
| `gh-pages` branch doesn't exist | `peaceiris/actions-gh-pages@v4` errors on first push | Implementation plan includes the bootstrap commit |
| `gh-pages` push race between dev and release | Single shared `concurrency.group: pages-write` with `cancel-in-progress: false` | Queued, not cancelled |
| `versions.json` corrupted by overlapping releases | `scripts/update-versions-json.php` writes atomically (write-rename) | Self-healing |
| Future phpdoc bump changes API page filenames or `<base href>` shape | `check-docs-links.php` fails CI | Phase 10+1 migration trigger |
| `CONTRIBUTING.md` missing | `scripts/copy-root-docs.php` exits non-zero with a clear "create CONTRIBUTING.md at the project root" message | Phase 10 creates the file as part of scope |

The spec's intent of "no silent build-time degradation" is implemented
by post-build scripts rather than a phpdoc flag (because that flag
does not exist).

## Testing strategy

### Per-PR (gate)

1. `composer test` — existing unit test suite still passes.
2. `make coverage-gate` — per-module floors still met.
3. `composer docs-api` — runs the full pipeline (build + all gates).
4. The pipeline itself contains 5 doc-quality gates (build-log grep,
   php-l, link resolver, example resolver, markdownlint).

### Per-release

5. The published `/en/<tag>/index.html` returns HTTP 200.
6. Spot-check: navbar switcher template-override loaded the new
   version.
7. Spot-check: API reference within the new version still works.
8. `versions.json` lists the new entry.

### Manual content review checklist (per page)

- **tutorial/**: contains either a runnable snippet (linted) or a
  `[full example](examples/X.php)` link pointing to a real file under
  `examples/`.
- **concepts/**: ≥ 1 hyperlink into the API reference (i.e. an `href`
  to a `classes/Gruven-PhpBotGram-...html` path under the `<base
  href>`-resolved root).
- **how-to/**: starts with "When to use this", ends with "Pitfalls".
- **index.md**: 4 Diataxis quadrants linked, each with a one-sentence
  hook.
- Fenced blocks tagged `php-fragment` are eye-reviewed (not
  auto-linted).

No content-quality automation in scope; reviewer judgment covers
grammar, voice, and accuracy.

### Pilot pass (first step of the implementation plan)

Before pinning `scripts/check-docs-build-log.php`'s grep patterns,
the implementation plan runs an empirical pilot:

1. Author one deliberately-broken Markdown page (broken `<a>` href,
   broken `xref`-style ref, unknown directive).
2. Run `vendor/bin/phpdoc -c phpdoc.dist.xml 2>&1 | tee
   build/docs/build.out`.
3. Inspect `build.out` for the actual warning/error format phpdoc
   emits.
4. Pin the exact pattern constants in `check-docs-build-log.php`.
5. Remove the deliberately-broken page.

This avoids guessing at log formats that may have changed between
phpdoc point releases.

## Definition of done

- `docs/guide/en/` populated per the content tree (5 + 18 + 15 + 4
  index + landing + changelog + contributing + reference-stub = 44
  pages, plus a `migration/.gitkeep`).
- `phpdoc.dist.xml.tpl` produces a single site combining narrative +
  API reference, with working hyperlinks between them validated by
  the link checker.
- `.phpdoc/template/` ships the navbar switcher; phpdoc loads it
  successfully (the rendered HTML contains the two `<select>`
  elements).
- `.markdownlint.jsonc` ships at the repo root with rules the
  generated `changelog.md`/`contributing.md` pass.
- `CONTRIBUTING.md` created at the project root with the documented
  section list.
- `.github/workflows/docs.yml` migrated to branch-mode, publishes
  master push to `/en/dev/`.
- `.github/workflows/docs-release.yml` publishes tag push to
  `/en/<tag>/` + `/en/latest/`.
- Repo Pages settings switched to "Deploy from branch: gh-pages /"
  (one-time manual UI step, documented in the implementation plan).
- `gh-pages` orphan branch bootstrapped (one-time manual commit,
  documented in the implementation plan).
- Phase 9's `concurrency.group: pages` renamed to `pages-write`.
- `https://gruven.github.io/phpbotgram/en/dev/index.html` renders
  with navbar showing language + version switchers (currently `[en]`
  and `[dev]` entries only).
- All five pipeline gates green on the Phase 10 merge commit.
- `CHANGELOG.md` Phase 10 entry summarises the new docs surface.

**Decoupled from `v0.1.0` tagging:** if Phase 9's task 9.5 has not
fired by Phase 10 merge time, `/en/latest/` and `/en/v0.1.0/` will
not exist on `gh-pages`. The JS-based `gh-pages/index.html` falls
through to `/en/dev/` cleanly. The release workflow stays dormant
until the first `v*.*.*` tag push.

## Out-of-scope follow-ups (not Phase 10)

- Russian translation (Phase 11+) — see §i18n for the full step list.
- Sphinx-style search index / `searchindex.json` (Phase 11+).
- Mobile-optimised theme variant (Phase 11+).
- Per-PR preview deploys (e.g. PR-specific subdirectory on
  `gh-pages`).
- Auto-generated changelog → release-notes page.
- Live "try it" sandbox.
- Cleanup automation for old version directories (manual `git rm` is
  fine until the gh-pages branch grows past a few dozen versions; at
  that point a `make prune-old-docs` placeholder lands).
- Class-based handler cookbook recipes and a `MagicData` concept page
  (deferred; see §Non-goals).

## Open questions / unverified assumptions

The toolchain claims that **are** mechanically verified against the
v3.10.0 phar bundled by `phpdocumentor/shim ^3`:

- `<guide format="md">` block exists and renders Markdown via the
  bundled `phpdocumentor/guides-markdown` package.
- `<template>` is a sibling of `<version>` per the XSD; template
  overrides are auto-loaded from `.phpdoc/template/` next to the
  config file.
- `vendor/bin/phpdoc --help` confirms the absence of `--fail-on=*` and
  the presence of `--log=` (which does not produce a file when the
  run has no log content).
- `<source dsn=".">` is observed-working in the Phase 9 build.

Items **not yet verified** and resolved by the implementation plan's
first step (the "pilot pass" in §Testing):

- The exact stderr/stdout pattern phpdoc emits for unresolved
  references vs. plain warnings vs. critical errors.
- Whether `<base href>`'s value is always `../` for guide pages, or
  varies by Diataxis depth (the link checker handles both).
- Whether the inline-`<script>` in `.phpdoc/template/`'s
  `_includes/switcher.html.twig` partial composes cleanly with the
  upstream `base.html.twig` (test-render before committing).

## References

- Diataxis methodology: <https://diataxis.fr>
- Aiogram docs (visual + structural reference):
  <https://docs.aiogram.dev/en/v3.28.2/>
- phpDocumentor v3 Guides component:
  <https://github.com/phpDocumentor/guides>
- `phpdocumentor/guides-markdown`:
  <https://github.com/phpDocumentor/guides-markdown>
- phpDocumentor v3 XSD (in the bundled phar):
  `phar://vendor/bin/phpdoc/data/xsd/phpdoc.xsd`
- `ProvideTemplateOverridePathMiddleware`:
  `phar://vendor/bin/phpdoc/src/phpDocumentor/Configuration/ProvideTemplateOverridePathMiddleware.php`
- `peaceiris/actions-gh-pages@v4`:
  <https://github.com/peaceiris/actions-gh-pages>
- Phase 9 (API documentation pipeline this design extends):
  `docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md` §
  Phase 9; `.github/workflows/docs.yml`; `phpdoc.dist.xml`.
