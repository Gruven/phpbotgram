# Phase 10 — Narrative documentation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Diataxis-structured narrative docs site (46 committed pages + 2 build-time copies of CHANGELOG / CONTRIBUTING → 48 rendered pages) into the same phpDocumentor v3 build as the Phase 9 API reference, published per-version to a `gh-pages` branch via GitHub Actions.

**Architecture:** Single `composer docs-api` invocation runs phpDocumentor over `src/` + `docs/guide/en/`, then a chain of PHP scripts validates and rewrites the output. CI publishes to `gh-pages` via `peaceiris/actions-gh-pages@v4` in branch mode (replacing Phase 9's workflow-mode Pages). A JS redirect at the gh-pages root + a Twig template override provides language + version switching.

**Tech Stack:** PHP 8.5, phpDocumentor v3.10 (via `phpdocumentor/shim ^3`), bash, GNU envsubst, GitHub Actions (`actions/checkout@v5`, `actions/cache@v4`, `shivammathur/setup-php@v2`, `actions/setup-node@v4`, `peaceiris/actions-gh-pages@v4`), `markdownlint-cli2@0.22.1` via npx.

**Spec:** `docs/superpowers/specs/2026-05-15-narrative-docs-design.md` (approved 2026-05-15 after 10 review cycles).

---

## File structure (locked in before tasks)

**New files (committed):**

| Path | Responsibility |
| --- | --- |
| `phpdoc.dist.xml.tpl` | Template for the rendered phpdoc config; envsubst expands `${VERSION}`. Replaces concrete `phpdoc.dist.xml`. |
| `.phpdoc/template/components/header.html.twig` | Copy of phpdoc's upstream `default/components/header.html.twig` with one extra `{{ include('_includes/switcher.html.twig') }}` line inside the navbar bar (so the switcher renders alongside the search input). phpdoc's Twig `ChainLoader` consults `.phpdoc/template/` *before* the bundled defaults, so overriding just this one file routes the existing upstream `{% include 'components/header.html.twig' %}` call into our copy while the rest of the layout still loads from the phar. |
| `.phpdoc/template/_includes/switcher.html.twig` | Twig partial: two `<select>` elements + inline JS that fetches `versions.json`/`languages.json` and populates them. |
| `.markdownlint.jsonc` | markdownlint-cli2 rules permissive enough for `docs/guide/en/changelog.md` and `contributing.md` (or excluding those two paths). |
| `scripts/build-docs.sh` | Bash wrapper: `set -euo pipefail`, envsubst, copy-root-docs, phpdoc, all gates. Invoked by `composer docs-api` and `make docs-api`. |
| `scripts/copy-root-docs.php` | Copy CHANGELOG.md + CONTRIBUTING.md into `docs/guide/en/`. Banner-prepend + mtime preserve. |
| `scripts/lint-docs.php` | TWO algorithms: (a) extract ```` ```php ```` blocks, auto-prepend `<?php\n`, run `php -l`; (b) positive regex scan for inline `<a>`/`<div>`/etc. in narrative Markdown. |
| `scripts/check-docs-build-log.php` | Grep `build/docs/build.out` for the four pilot-pinned warning substrings. |
| `scripts/check-docs-links.php` | Walks rendered `build/docs/api/guide/**/*.html`; for every sentinel-URL `<a href="https://api.phpbotgram.local/X">`, verifies `build/docs/api/classes/X` exists + anchor ID present. |
| `scripts/rewrite-api-links.php` | HTML-aware DOM rewrite: `<a href="https://api.phpbotgram.local/X">` → `<a href="classes/X">`. Post-rewrite asserts no sentinel substring remains, with documented exclusions. |
| `scripts/check-internal-links.php` | Walks rendered `build/docs/api/guide/**/*.html`; verifies every non-sentinel internal href and `#fragment` resolves under `<base href>`. |
| `scripts/check-docs-examples.php` | Verifies every `examples/<name>.php` link in narrative Markdown points at an existing file under `examples/`. |
| `scripts/update-versions-json.php` | Atomic CLI: load → dedup-by-id → `stable=auto`/`true`/`false` handling with semver-aware backport semantics → insert-newest-first → atomic write. |
| `tests/Scripts/CopyRootDocsTest.php` | PHPUnit test for `copy-root-docs.php`. |
| `tests/Scripts/LintDocsTest.php` | PHPUnit test for `lint-docs.php` (both `php -l` and inline-HTML scan paths). |
| `tests/Scripts/CheckDocsBuildLogTest.php` | PHPUnit test for `check-docs-build-log.php`. |
| `tests/Scripts/CheckDocsLinksTest.php` | PHPUnit test for `check-docs-links.php`. |
| `tests/Scripts/RewriteApiLinksTest.php` | PHPUnit test for `rewrite-api-links.php` (HTML-aware DOM, exclusion rules, completeness assertion). |
| `tests/Scripts/CheckInternalLinksTest.php` | PHPUnit test for `check-internal-links.php` (base-href resolution, fragment anchors). |
| `tests/Scripts/CheckDocsExamplesTest.php` | PHPUnit test for `check-docs-examples.php`. |
| `tests/Scripts/UpdateVersionsJsonTest.php` | PHPUnit test for `update-versions-json.php` (all stable= modes, dedup, semver backport). |
| `.github/workflows/docs-release.yml` | New tag-triggered workflow. |
| `CONTRIBUTING.md` | Project-root contributing guide (sections: issues, PR workflow, coding standards, commit conventions). |
| `docs/guide/en/index.md` | Top-level landing: 4 Diataxis quadrant cards. |
| `docs/guide/en/tutorial/{index,01-installation,02-first-bot,03-handlers-and-filters,04-state,05-deployment}.md` | 6 tutorial pages. |
| `docs/guide/en/how-to/{index,...20 recipes}.md` | 21 how-to pages (index + 20 recipes). |
| `docs/guide/en/concepts/{index,...16 concepts}.md` | 17 concept pages (index + 16 topics). |
| `docs/guide/en/reference/index.md` | Reference stub pointing at the rendered API. |
| `docs/guide/en/migration/.gitkeep` | Placeholder for future breaking-change migration. |
| `docs/guide/shared/assets/diagrams/.gitkeep` | Reserved for language-agnostic SVG. |
| `docs/guide/shared/assets/code-snippets/.gitkeep` | Reserved for language-agnostic PHP fragments. |

**Files modified:**

| Path | Change |
| --- | --- |
| `phpdoc.dist.xml` | **Deleted** (replaced by `phpdoc.dist.xml.tpl`). |
| `.github/workflows/docs.yml` | Migrated from workflow mode → branch mode per spec table. |
| `composer.json` | `docs-api` script rewritten to `bash scripts/build-docs.sh`. `phpdocumentor/shim` constraint tightened to `~3.10.0`. |
| `Makefile` | `docs-api` target rewritten to `bash scripts/build-docs.sh`. |
| `.gitignore` | Add `/phpdoc.dist.xml`, `/docs/guide/en/changelog.md`, `/docs/guide/en/contributing.md`. |
| `CHANGELOG.md` | Append `## [Unreleased]` section above `## [0.1.0]` with Phase 10 surface summary. |
| `README.md` | Add link to new `https://gruven.github.io/phpbotgram/en/dev/guide/` URL. |

**Files generated by build (gitignored):**

- `phpdoc.dist.xml` (envsubst output)
- `docs/guide/en/changelog.md`, `docs/guide/en/contributing.md` (copy-root-docs output)
- `build/docs/api/` (phpdoc render)
- `build/docs/build.out` (phpdoc stderr+stdout tee)
- `build/docs/root-publish/versions.json` (scratch for the second peaceiris invocation)

---

## Task ordering rationale

1. **Pilot pass (Task 1)** runs first to re-confirm spec's empirical claims against the locally-installed phpdoc v3.10. Pins grep patterns into `check-docs-build-log.php` constants.
2. **Toolchain skeleton (Tasks 2–4)** — template, build wrapper, copy-root-docs. Establishes the loop locally.
3. **Validation scripts (Tasks 5–11)** — one task per script, TDD-driven. Each script has its own test under `tests/Scripts/`.
4. **Template override (Task 12)** — Twig partials + JS switcher.
5. **`.gitignore` + `CONTRIBUTING.md` + `.markdownlint.jsonc` + composer/Makefile (Task 13)** — manifest changes.
6. **Workflows (Tasks 14–15)** — `docs.yml` migration + new `docs-release.yml`.
7. **Content authoring (Tasks 16–20)** — landing, tutorial, how-to, concepts, reference stub. One task per Diataxis section. Numbering reflects Diataxis order, but the physical execution order swaps Tasks 17 and 18: author concepts first so cross-links from recipes find existing targets (see the note at the top of each task).
8. **One-time manual setup (Task 21)** — gh-pages bootstrap + Pages UI flip, documented but not executed by the automation.
9. **CHANGELOG + README polish (Task 22)** — final commit polish.
10. **Phase 10 acceptance + tag (Task 23)** — verify all gates green, smoke-test, tag `phase-10-complete`.

---

## Task 1: Pilot pass — verify spec's empirical claims and pin grep patterns

**Files:**
- Create (temporary): `/tmp/phpdoc-pilot/{src,docs/guide,phpdoc.xml}`
- Create: `docs/superpowers/notes/2026-05-15-phase-10-pilot.md` (checked in).
- Will inform (later, in Task 5 Step 5): `scripts/check-docs-build-log.php` — patterns pinned from the notes written here.

This task does not change runtime behavior — it produces a research notes commit only.

- [ ] **Step 0: Create the Phase 10 feature branch off `master`**

```bash
cd /Users/gruven/repository/github/phpbotgram
git fetch origin master
git checkout -b feat/phase-10-narrative-docs origin/master
```

Expected: branch `feat/phase-10-narrative-docs` exists and is checked out, tracking nothing yet (Task 23 pushes with `-u`). All subsequent commits in this plan land on this branch. If the branch already exists from a previous run, switch to it (`git checkout feat/phase-10-narrative-docs`); do not re-create it.

- [ ] **Step 1: Build the pilot fixture**

```bash
rm -rf /tmp/phpdoc-pilot && mkdir -p /tmp/phpdoc-pilot/{src,docs/guide,docs/guide/shared}
cat > /tmp/phpdoc-pilot/src/Foo.php <<'PHP'
<?php
namespace TestNS;
/** Foo summary. */
class Foo {
    /** bar method. */
    public function bar(): void {}
}
PHP

cat > /tmp/phpdoc-pilot/docs/guide/index.md <<'MD'
# Pilot

[A](other.md)
[A2](other.md#section)
[B](classes/TestNS-Foo.html)
[C](https://example.com/x)
<a href="classes/TestNS-Foo.html">D</a>
[E](#anchor)
[Sentinel](https://api.phpbotgram.local/TestNS-Foo.html#method_bar)

![](../shared/_pilot.svg)
MD

cat > /tmp/phpdoc-pilot/docs/guide/other.md <<'MD'
# Other

## Section

body
MD

touch /tmp/phpdoc-pilot/docs/guide/shared/_pilot.svg

cat > /tmp/phpdoc-pilot/phpdoc.xml <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpdocumentor configVersion="3" xmlns="https://www.phpdoc.org">
  <paths><output>build</output></paths>
  <version number="0.0">
    <api format="php"><source dsn="."><path>src</path></source></api>
    <guide format="md" output="guide"><source dsn="."><path>docs/guide</path></source></guide>
  </version>
</phpdocumentor>
XML
```

- [ ] **Step 2: Build the pilot and capture stderr+stdout**

Run:
```bash
cd /tmp/phpdoc-pilot && /Users/gruven/repository/github/phpbotgram/vendor/bin/phpdoc -c phpdoc.xml --no-ansi --no-progress 2>&1 | tee build.out
echo '---'
ls build/
echo '---'
cat build/guide/index.html | grep -E '<(a|img)' | head -10
```

Expected: phpdoc exits with code 0. `build/guide/index.html` is produced. `build.out` contains at least these substrings:
- `Reference other.md#section could not be resolved`
- `Reference classes/TestNS-Foo.html could not be resolved`

The rendered `index.html` should contain:
- `<a href="https://example.com/x">C</a>` (kept)
- `<a href="https://api.phpbotgram.local/TestNS-Foo.html#method_bar">Sentinel</a>` (kept verbatim — confirms sentinel passthrough)
- `<a href="guide/other.html#other">A</a>` (whole-page `.md` link rewritten by phpdoc)
- Plain `A2`, `B`, `D` text (anchors dropped)

- [ ] **Step 3: Probe `<base href>` depth-adaptivity**

Run:
```bash
for f in /tmp/phpdoc-pilot/build/guide/index.html /tmp/phpdoc-pilot/build/classes/TestNS-Foo.html; do
  echo "=== $f ==="
  grep -o '<base href="[^"]*"' "$f"
done
```

Expected: each page has a `<base href>` matching its depth (`./` at root, `../` one level deep, `../../` two levels deep).

- [ ] **Step 4: Probe `Image reference not found` warning**

Search `build.out` for the literal string `Image reference not found`. Record whether it appears for the `![](../shared/_pilot.svg)` reference. Two outcomes are possible:
- Warning **does** appear → spec's allow-list assumption is correct; `scripts/check-docs-build-log.php` must skip this substring.
- Warning **does not** appear → spec's gate set is already complete without an allow-list.

- [ ] **Step 4a: Confirm sentinel-URL form matches phpdoc's real class-page filenames**

The plan's sentinel URLs use dash-separated namespace forms like
`Gruven-PhpBotGram-Filters-Filter.html`. The fixture pilot above only
tests `TestNS-Foo.html` (single-namespace toy). Before authoring any
narrative page, confirm the dash form matches what phpdoc actually
emits for the real codebase:

```bash
cd /Users/gruven/repository/github/phpbotgram
# If a Phase 9 build is not on disk, run one (it's idempotent).
[ -d build/docs/api/classes ] || VERSION=0.1.0-dev vendor/bin/phpdoc -c phpdoc.dist.xml >/dev/null 2>&1 || true

ls build/docs/api/classes/ | grep -E '^Gruven-PhpBotGram-' | head -10
```

Expected: at least 10 filenames of the form
`Gruven-PhpBotGram-<subpath>-<Class>.html`. Record one example each
of:
- A top-level class: `Gruven-PhpBotGram-Bot.html`.
- A nested-namespace class:
  `Gruven-PhpBotGram-Client-Session-BaseSession.html`.
- A `Types` namespace class:
  `Gruven-PhpBotGram-Types-Message.html`.
- A `Types` class introduced in Phase 8/9:
  `Gruven-PhpBotGram-Types-ErrorEvent.html`.

If any of the four are MISSING (e.g. nested namespaces use underscores
or PascalCase joins), STOP and update the sentinel-URL convention in
the spec + all the worked examples in Tasks 17/18 BEFORE proceeding.
The convention is load-bearing for every narrative page that links
into the API ref; getting it wrong here cascades into every
`check-docs-links.php` failure later.

Record the observed convention in the pilot notes:

```markdown
## Class-page filename convention

`Gruven-PhpBotGram-Foo-Bar.html` form: <yes|no>
Examples observed:
- ...
- ...
- ...
- ...
```

- [ ] **Step 4b: Probe `php-fragment` fence rendering**

Extend the pilot fixture with a fenced block tagged `php-fragment`, rebuild, and inspect the rendered HTML:

```bash
cat >> /tmp/phpdoc-pilot/docs/guide/index.md <<'MD'

## Fragment fence

```php-fragment
public function send(): Message {
    return $this->bot->sendMessage(text: 'hi');
}
```

## Plain php fence

```php
$x = 1;
```
MD

cd /tmp/phpdoc-pilot && /Users/gruven/repository/github/phpbotgram/vendor/bin/phpdoc -c phpdoc.xml --no-ansi --no-progress 2>&1 | tee build.out
grep -A2 'fragment-fence\|Fragment fence' build/guide/index.html | head -20
```

Record:
- Does `php-fragment` render as `<pre><code>...` or as something different (raw block, plain `<p>`)?
- Does plain `php` render as a highlighted block (look for class attributes like `language-php` or syntax-highlighted spans)?
- Is the `php-fragment` block visually distinguishable from a `php` block? Note any divergence so authors know which fence to choose in Tasks 16–18.

This decision rolls into the "Fenced-block conventions" section of `CONTRIBUTING.md` (Task 13).

- [ ] **Step 5: Write the pilot notes file**

```bash
mkdir -p /Users/gruven/repository/github/phpbotgram/docs/superpowers/notes
```

Create `/Users/gruven/repository/github/phpbotgram/docs/superpowers/notes/2026-05-15-phase-10-pilot.md` with sections (filled from the previous steps):

```markdown
# Phase 10 pilot pass — empirical findings (YYYY-MM-DD)

phpDocumentor version (from `vendor/bin/phpdoc --version`):
<version-string>

## Warning substrings observed on broken fixtures

- `could not be resolved`: <yes|no>, examples: ...
- `Document with name`: <yes|no>, examples: ...
- `No parent found for file`: <yes|no>, examples: ...
- `Document has no title`: <yes|no>, examples: ...
- `Image reference not found`: <yes|no>, decision: <gate|allow-list>

## <base href> depth table

| Rendered path | <base href> |
| ... | ... |

## Sentinel-URL passthrough

`https://api.phpbotgram.local/X.html#anchor` rendered as: ...

## Shared-asset image resolution

`![](../shared/_pilot.svg)` rendered as: ...
Cp-after-phpdoc warning observed: <yes|no>

## `php-fragment` fence rendering

`php-fragment` rendered as: ...
`php` rendered as: ...
Visually distinguishable: <yes|no>
Authoring guidance: <prefer `php` for full files, `php-fragment` for snippets | both render identically, prefer `php`>

## Decision rolls into

- `scripts/check-docs-build-log.php` patterns array.
- §"Build pipeline" cp ordering (before vs after phpdoc).
- `CONTRIBUTING.md` "Fenced-block conventions" section (Task 13).
```

- [ ] **Step 6: Commit the pilot notes**

```bash
cd /Users/gruven/repository/github/phpbotgram
git add docs/superpowers/notes/2026-05-15-phase-10-pilot.md
git commit -m "phase-10: pilot pass — empirical phpdoc 3.10 findings"
```

---

## Task 2: phpdoc config template + `.gitignore` entries

**Files:**
- Create: `phpdoc.dist.xml.tpl`
- Modify: `.gitignore` (add three entries)
- Delete: `phpdoc.dist.xml` (Phase 9 concrete file)

> **Intentional deviation from spec §"Build pipeline" example:** the
> approved spec shows `<ignore-tags>` as a direct child of `<version>`,
> sibling to `<api>` and `<guide>`. That placement is invalid against
> phpDocumentor's v3 XSD (`versionType`'s sequence is
> `folder?, api*, guide*` — no `ignore-tags` slot). The Phase 9
> working file (`phpdoc.dist.xml` on `master`) correctly nests
> `<ignore-tags>` inside `<api>`, which is where the v3 XSD allows it.
> This plan follows the working file's shape, not the spec's. The
> spec deviation is intentional; do not "correct" it back to the
> spec form during execution.

- [ ] **Step 1: Write the template**

Create `/Users/gruven/repository/github/phpbotgram/phpdoc.dist.xml.tpl`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpdocumentor configVersion="3" xmlns="https://www.phpdoc.org">
  <title>phpbotgram</title>
  <paths>
    <output>build/docs/api</output>
    <cache>build/docs/.cache</cache>
  </paths>
  <version number="${VERSION}">
    <api format="php">
      <source dsn="."><path>src</path></source>
      <ignore-tags>
        <!-- Preserved from Phase 9: codegen-output classes carry
             @generated; suppress to keep API docs readable. Per the
             v3 XSD, <ignore-tags> is a child of <api>, not <version>. -->
        <ignore-tag>generated</ignore-tag>
      </ignore-tags>
    </api>
    <guide format="md" output="guide">
      <source dsn="."><path>docs/guide/en</path></source>
    </guide>
  </version>
  <!-- Template overrides live at .phpdoc/template/ (next to this XML),
       auto-discovered by phpDocumentor's
       ProvideTemplateOverridePathMiddleware. -->
</phpdocumentor>
```

- [ ] **Step 2: Delete the Phase 9 `phpdoc.dist.xml`**

```bash
git rm phpdoc.dist.xml
```

- [ ] **Step 3: Append `.gitignore` lines**

Edit `/Users/gruven/repository/github/phpbotgram/.gitignore`, append at the end:

```
# Phase 10 — generated by scripts/build-docs.sh
/phpdoc.dist.xml
/docs/guide/en/changelog.md
/docs/guide/en/contributing.md
```

- [ ] **Step 4: Verify envsubst works locally**

Run:
```bash
cd /Users/gruven/repository/github/phpbotgram
VERSION=0.1.0-dev envsubst '${VERSION}' < phpdoc.dist.xml.tpl > /tmp/phpdoc-rendered.xml
grep -F '<version number="0.1.0-dev">' /tmp/phpdoc-rendered.xml
```

Expected: exact match (envsubst expanded `${VERSION}`).

- [ ] **Step 5: Commit**

```bash
git add phpdoc.dist.xml.tpl .gitignore
git rm phpdoc.dist.xml 2>/dev/null || true
git commit -m "phase-10: template phpdoc config + gitignore entries"
```

---

## Task 3: `scripts/build-docs.sh` skeleton (delegate target for composer + make)

**Files:**
- Create: `scripts/build-docs.sh`
- Modify: `composer.json` (rewrite `docs-api` script)
- Modify: `Makefile` (rewrite `docs-api` target)

The script's gate-script invocations are stubs at this stage (the gate scripts arrive in Tasks 5–11). Each missing script will fail with "command not found", which is the intended state until those tasks land.

- [ ] **Step 1: Write the bash wrapper**

Create `/Users/gruven/repository/github/phpbotgram/scripts/build-docs.sh`:

```bash
#!/usr/bin/env bash
# scripts/build-docs.sh — Phase 10 docs build wrapper.
# Invoked by `composer docs-api` and `make docs-api`. Never call `vendor/bin/phpdoc`
# directly; this wrapper enforces the gate chain.
set -euo pipefail

: "${VERSION:?VERSION env var must be set (use 0.1.0-dev locally)}"

# Anchor cwd at the repo root so .phpdoc/template/ auto-discovery resolves
# relative to phpdoc.dist.xml (which lives at the repo root).
cd "$(dirname "$0")/.."

# build/docs is created AFTER the cd so it lands at repo/build/docs/.
mkdir -p build/docs build/docs/root-publish

# Resolve envsubst. macOS gettext is keg-only; fall back to common paths.
ENVSUBST_BIN="$(command -v envsubst 2>/dev/null || true)"
[ -z "$ENVSUBST_BIN" ] && [ -x /usr/local/opt/gettext/bin/envsubst ] && ENVSUBST_BIN=/usr/local/opt/gettext/bin/envsubst
[ -z "$ENVSUBST_BIN" ] && [ -x /opt/homebrew/opt/gettext/bin/envsubst ] && ENVSUBST_BIN=/opt/homebrew/opt/gettext/bin/envsubst
[ -z "$ENVSUBST_BIN" ] && { echo "envsubst not found (install with: brew install gettext)" >&2; exit 1; }

# Expand ${VERSION} only; the allow-list quote keeps $HOME/$PATH/etc. untouched.
"$ENVSUBST_BIN" '${VERSION}' < phpdoc.dist.xml.tpl > phpdoc.dist.xml

# Copy project-root CHANGELOG/CONTRIBUTING into the narrative tree.
php scripts/copy-root-docs.php

# Run phpdoc; capture stderr+stdout text-clean (no ANSI, no progress bar).
vendor/bin/phpdoc -c phpdoc.dist.xml --no-ansi --no-progress 2>&1 \
  | tee build/docs/build.out

# Doc-quality gates (each script exits non-zero on failure → pipefail kills us).
php scripts/check-docs-build-log.php build/docs/build.out

# Copy language-agnostic shared assets into the rendered output so guide
# pages can reference them via paths relative to <base href>.
mkdir -p build/docs/api/guide/shared
cp -r docs/guide/shared/. build/docs/api/guide/shared/

php scripts/check-docs-links.php
php scripts/rewrite-api-links.php
php scripts/check-internal-links.php
php scripts/lint-docs.php
php scripts/check-docs-examples.php

# Markdown style. Pinned version so a future cli2 breaking release doesn't
# silently break CI.
npx markdownlint-cli2@0.22.1 'docs/guide/en/**/*.md'
```

- [ ] **Step 2: Make the wrapper executable**

```bash
chmod +x scripts/build-docs.sh
```

- [ ] **Step 3: Rewrite `composer.json`'s `docs-api` script**

Open `/Users/gruven/repository/github/phpbotgram/composer.json` and find the `scripts` section. Replace the `docs-api` line. Current Phase 9 form:

```json
"docs-api": "phpdoc -c phpdoc.dist.xml"
```

New Phase 10 form (plain delegation; inherits `VERSION` from caller env):

```json
"docs-api": "bash scripts/build-docs.sh"
```

CI workflows export `VERSION` via the step-level `env:` block (Tasks 14–15). Local contributors set it inline: `VERSION=0.1.0-dev composer docs-api`. Document this in `CONTRIBUTING.md` (Task 13).

Also tighten the `phpdocumentor/shim` constraint in `require-dev` from `"^3"` to `"~3.10.0"`. Re-resolve the lock by running `composer update phpdocumentor/shim` afterwards (Step 5). Note: composer's `--lock` flag does **not** accept a package argument — `composer update --lock <pkg>` silently ignores the package and only refreshes the lock hash, leaving the resolved version untouched. To actually pull the new 3.10.x patch we omit `--lock` and pass the package name; composer re-resolves the named package **and its transitive requirements** (the lock hash will also bump). Inspect `git diff composer.lock` after the update to confirm only `phpdocumentor/shim` plus expected transitive deps moved.

- [ ] **Step 4: Rewrite `Makefile`'s `docs-api` target**

Open `/Users/gruven/repository/github/phpbotgram/Makefile` and replace the `docs-api` rule. Current Phase 9 form:

```makefile
docs-api:
	vendor/bin/phpdoc -c phpdoc.dist.xml
```

New Phase 10 form:

```makefile
docs-api:
	VERSION=0.1.0-dev bash scripts/build-docs.sh
```

- [ ] **Step 5: Refresh `composer.lock` and verify the pin**

```bash
NO_PROXY='*' no_proxy='*' composer update phpdocumentor/shim --no-interaction --ignore-platform-req=ext-mongodb
composer show phpdocumentor/shim | grep -E '^versions\s*:.*3\.10\.' \
  || { echo "ERROR: phpdocumentor/shim resolved outside 3.10.x — check composer.json constraint"; exit 1; }
```

Expected: lock entry for `phpdocumentor/shim` pins to a 3.10.x version AND the grep verifies the resolved version is 3.10.x. Composer only touches the named package's lock entry (no global re-resolution). If composer reports network errors, see Phase 9 §"NO_PROXY workaround" for the proxy bypass.

- [ ] **Step 6: Verify the wrapper exits with a clear error before any script exists**

```bash
VERSION=0.1.0-dev bash scripts/build-docs.sh 2>&1 | tail -3
```

Expected: phpdoc runs (envsubst + the existing API rendering still work), then the gate chain fails on the first missing `scripts/check-docs-build-log.php`. The exact wording of the "PHP could not open input file" message varies across platforms (`php: ... No such file or directory` on macOS, `Could not open input file: scripts/...` on Linux); the only invariant is that the wrapper exits non-zero with a message that contains `check-docs-build-log.php`. This confirms the wrapper integrates correctly with the to-be-built scripts.

- [ ] **Step 7: Commit**

```bash
git add scripts/build-docs.sh composer.json composer.lock Makefile
git commit -m "phase-10: build-docs.sh wrapper + composer/make delegation"
```

---

## Task 4: `scripts/copy-root-docs.php` — copy CHANGELOG/CONTRIBUTING into the narrative tree

**Files:**
- Create: `scripts/copy-root-docs.php`
- Create: `tests/Scripts/CopyRootDocsTest.php`

The script is invoked by `build-docs.sh` before phpdoc, after envsubst. It copies the project-root files into `docs/guide/en/{changelog,contributing}.md`, prepending an "AUTO-GENERATED" banner and preserving the source's mtime so phpdoc's mtime-based cache stays warm. `CONTRIBUTING.md` doesn't exist at project root yet — Task 13 creates it; this task makes the script tolerate that case with a clear error.

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/CopyRootDocsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CopyRootDocsTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/copyrootdocs-' . uniqid();
    mkdir($this->tmp . '/source', recursive: true);
    mkdir($this->tmp . '/target', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testCopiesSourceFileWithBanner(): void
  {
    file_put_contents($this->tmp . '/source/CHANGELOG.md', "# CL\nbody\n");
    touch($this->tmp . '/source/CHANGELOG.md', 1_700_000_000);

    $this->runScript([$this->tmp . '/source/CHANGELOG.md', $this->tmp . '/target/changelog.md']);

    $copy = file_get_contents($this->tmp . '/target/changelog.md');
    self::assertStringContainsString('AUTO-GENERATED', $copy);
    self::assertStringContainsString('source: ' . $this->tmp . '/source/CHANGELOG.md', $copy);
    self::assertStringContainsString("# CL\nbody\n", $copy);
    self::assertSame(1_700_000_000, filemtime($this->tmp . '/target/changelog.md'));
  }

  public function testExitsNonZeroWhenSourceMissing(): void
  {
    $rc = $this->runScriptExpectingFailure([$this->tmp . '/source/missing.md', $this->tmp . '/target/x.md']);
    self::assertSame(1, $rc);
  }

  public function testExitsNonZeroWhenTargetIsDirectory(): void
  {
    file_put_contents($this->tmp . '/source/CHANGELOG.md', "body\n");
    mkdir($this->tmp . '/target/changelog.md');

    $rc = $this->runScriptExpectingFailure([$this->tmp . '/source/CHANGELOG.md', $this->tmp . '/target/changelog.md']);
    self::assertSame(1, $rc);
  }

  /** @param list<string> $args */
  private function runScript(array $args): void
  {
    $script = dirname(__DIR__, 2) . '/scripts/copy-root-docs.php';
    $cmd = sprintf('php %s %s 2>&1', escapeshellarg($script), implode(' ', array_map(escapeshellarg(...), $args)));
    exec($cmd, $output, $rc);
    self::assertSame(0, $rc, 'Script failed: ' . implode("\n", $output));
  }

  /** @param list<string> $args */
  private function runScriptExpectingFailure(array $args): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/copy-root-docs.php';
    $cmd = sprintf('php %s %s 2>&1', escapeshellarg($script), implode(' ', array_map(escapeshellarg(...), $args)));
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) {
      return;
    }
    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/CopyRootDocsTest.php`
Expected: FAIL — `scripts/copy-root-docs.php` doesn't exist yet.

- [ ] **Step 3: Implement `scripts/copy-root-docs.php`**

Create `/Users/gruven/repository/github/phpbotgram/scripts/copy-root-docs.php`:

```php
<?php

declare(strict_types=1);

/**
 * Copy project-root CHANGELOG.md and CONTRIBUTING.md into docs/guide/en/.
 *
 * Two modes:
 *   - No args: copy /CHANGELOG.md → /docs/guide/en/changelog.md and
 *     /CONTRIBUTING.md → /docs/guide/en/contributing.md.
 *   - Two args: copy <source> → <target>. Used by tests.
 *
 * Banner prepend: "<!-- AUTO-GENERATED — do not edit; source: <abs path> -->"
 * Mtime preserve: touch(target, sourceMtime).
 *
 * Exit codes:
 *   0 — success.
 *   1 — source missing OR target path exists and is a directory OR write failed.
 */

function copy_one(string $source, string $target): void
{
  if (!is_file($source)) {
    fwrite(STDERR, "copy-root-docs: source not found: {$source}\n");
    fwrite(STDERR, "  (Phase 10 scope: CONTRIBUTING.md is created in Task 13 — see plan.)\n");
    exit(1);
  }
  if (is_dir($target)) {
    fwrite(STDERR, "copy-root-docs: target is a directory: {$target}\n");
    exit(1);
  }

  $body = file_get_contents($source);
  if ($body === false) {
    fwrite(STDERR, "copy-root-docs: read failed: {$source}\n");
    exit(1);
  }

  $banner = "<!-- AUTO-GENERATED — do not edit; source: {$source} -->\n\n";
  $payload = $banner . $body;

  if (file_put_contents($target, $payload) === false) {
    fwrite(STDERR, "copy-root-docs: write failed: {$target}\n");
    exit(1);
  }

  $mtime = filemtime($source);
  if ($mtime !== false) {
    touch($target, $mtime);
  }

  echo "copy-root-docs: {$source} → {$target}\n";
}

$args = array_slice($argv, 1);

if (count($args) === 2) {
  copy_one($args[0], $args[1]);
  exit(0);
}

if (count($args) !== 0) {
  fwrite(STDERR, "Usage: copy-root-docs.php [source target]\n");
  exit(2);
}

$repo = dirname(__DIR__);
copy_one("{$repo}/CHANGELOG.md", "{$repo}/docs/guide/en/changelog.md");
copy_one("{$repo}/CONTRIBUTING.md", "{$repo}/docs/guide/en/contributing.md");
```

- [ ] **Step 4: Create the `docs/guide/en/` directory if missing**

The script writes into `docs/guide/en/`, so the directory must exist:

```bash
mkdir -p /Users/gruven/repository/github/phpbotgram/docs/guide/en
```

- [ ] **Step 5: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/CopyRootDocsTest.php`
Expected: PASS (3 tests, 7 assertions).

- [ ] **Step 6: Commit**

```bash
git add scripts/copy-root-docs.php tests/Scripts/CopyRootDocsTest.php docs/guide/en/
git commit -m "phase-10: copy-root-docs.php + test"
```

---

## Task 5: `scripts/check-docs-build-log.php` — gate phpdoc warnings

**Files:**
- Create: `scripts/check-docs-build-log.php`
- Create: `tests/Scripts/CheckDocsBuildLogTest.php`

Patterns are pinned from the pilot pass notes (Task 1). Spec defaults: `could not be resolved`, `Document with name`, `No parent found for file`, `Document has no title`. Adjust per pilot findings.

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/CheckDocsBuildLogTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CheckDocsBuildLogTest extends TestCase
{
  public function testPassesOnCleanLog(): void
  {
    $log = $this->makeTempLog("0/23 [===]   0%\nAll done in 12 seconds!\n");
    self::assertSame(0, $this->runScript($log));
  }

  public function testFailsOnUnresolvedReference(): void
  {
    $log = $this->makeTempLog("0/23 [===]   0%\n Reference other.md#section could not be resolved in index\nAll done.\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnNoParent(): void
  {
    $log = $this->makeTempLog("No parent found for file \"orphan/page\" attaching it to the document root instead.\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnMissingTitle(): void
  {
    $log = $this->makeTempLog("Document has no title for orphan/page\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnMissingDocument(): void
  {
    $log = $this->makeTempLog("Document with name 'foo' not found\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnMissingIndexFile(): void
  {
    // Belt-and-braces: ParseDirectoryHandler usually throws hard, but the
    // pattern is gated in case a future phpdoc switches to warning surface.
    $log = $this->makeTempLog("Could not find an index file 'docs/guide/en/orphan/'\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testIgnoresAllowedSubstrings(): void
  {
    // Image reference not found is on the allow-list (cp runs after phpdoc).
    $log = $this->makeTempLog("Image reference not found '../shared/foo.svg'\nAll done.\n");
    self::assertSame(0, $this->runScript($log));
  }

  public function testFailsWhenLogFileMissing(): void
  {
    self::assertSame(2, $this->runScript('/tmp/nonexistent-log-' . uniqid()));
  }

  private function makeTempLog(string $content): string
  {
    $path = tempnam(sys_get_temp_dir(), 'buildlog');
    file_put_contents($path, $content);

    return $path;
  }

  private function runScript(string $logPath): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-docs-build-log.php';
    $cmd = sprintf('php %s %s 2>&1', escapeshellarg($script), escapeshellarg($logPath));
    exec($cmd, $output, $rc);

    return $rc;
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/CheckDocsBuildLogTest.php`
Expected: FAIL — script doesn't exist.

- [ ] **Step 3: Implement the script**

Create `/Users/gruven/repository/github/phpbotgram/scripts/check-docs-build-log.php`:

```php
<?php

declare(strict_types=1);

/**
 * Inspect build/docs/build.out (or any phpdoc stderr+stdout capture file) for
 * doc-quality warning substrings that phpdoc emits at exit-0. Exit non-zero on
 * any match.
 *
 * Patterns pinned by the pilot pass (Task 1 of the Phase 10 plan); update the
 * list when the pilot notes change.
 *
 * Allow-listed substrings are matched FIRST and skipped — patterns the spec
 * deliberately doesn't gate on (e.g. `Image reference not found` when the
 * shared-asset cp runs after phpdoc).
 *
 * Exit codes:
 *   0 — no gate pattern matched.
 *   1 — at least one gate pattern matched.
 *   2 — argv usage error (missing path, unreadable file).
 */

const GATE_PATTERNS = [
  'could not be resolved',
  'Document with name',
  'No parent found for file',
  'Document has no title',
  'Could not find an index file', // belt-and-braces; usually phpdoc exits non-zero
];

const ALLOW_PATTERNS = [
  'Image reference not found',
];

if ($argc !== 2) {
  fwrite(STDERR, "Usage: check-docs-build-log.php <path-to-build.out>\n");
  exit(2);
}

$path = $argv[1];
if (!is_file($path)) {
  fwrite(STDERR, "check-docs-build-log: log file not found: {$path}\n");
  exit(2);
}

$body = file_get_contents($path);
if ($body === false) {
  fwrite(STDERR, "check-docs-build-log: read failed: {$path}\n");
  exit(2);
}

$lines = preg_split('/\R/', $body);
$failures = [];

foreach ($lines as $i => $line) {
  $allowed = false;
  foreach (ALLOW_PATTERNS as $allow) {
    if (str_contains($line, $allow)) {
      $allowed = true;
      break;
    }
  }
  if ($allowed) {
    continue;
  }
  foreach (GATE_PATTERNS as $pattern) {
    if (str_contains($line, $pattern)) {
      $failures[] = ['line' => $i + 1, 'pattern' => $pattern, 'text' => $line];
      break;
    }
  }
}

if ($failures === []) {
  echo "check-docs-build-log: clean\n";
  exit(0);
}

fwrite(STDERR, "check-docs-build-log: FAIL — " . count($failures) . " gate pattern matches in {$path}\n");
foreach ($failures as $f) {
  fwrite(STDERR, sprintf("  line %d (matched '%s'): %s\n", $f['line'], $f['pattern'], $f['text']));
}
exit(1);
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/CheckDocsBuildLogTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Reconcile patterns with pilot pass notes (Task 1)**

Re-read `docs/superpowers/notes/2026-05-15-phase-10-pilot.md` (produced
in Task 1). For each warning substring the pilot observed:

- If it is in the spec's gate list (`could not be resolved`,
  `Document with name`, `No parent found for file`,
  `Document has no title`, `Could not find an index file`): leave
  it in `GATE_PATTERNS`.
- If it is in the spec's allow-list (`Image reference not found`):
  leave it in `ALLOW_PATTERNS`.
- If the pilot observed a **new** doc-quality substring not on either
  list, decide explicitly (add to gate or allow) and edit the script.
- If the pilot showed a listed pattern is **never** emitted by the
  installed phpdoc, remove it from the array.

If the pilot's `Image reference not found` decision was "reorder cp
before phpdoc", drop the `'Image reference not found'` entry from
`ALLOW_PATTERNS` AND swap the cp ordering in `scripts/build-docs.sh`
(Task 3). Document the chosen branch in a comment at the top of the
patterns constants.

**Whenever the patterns arrays change, update
`tests/Scripts/CheckDocsBuildLogTest.php` in lockstep.** The test is
fixture-driven, so a removed allow-entry or an added gate-entry leaves
the corresponding test case asserting against the wrong body. Specifically:

- If you remove an entry from `ALLOW_PATTERNS`, also remove or rewrite
  the test case that asserted "ignored when allowed" for that substring.
- If you add an entry to `GATE_PATTERNS`, add a parallel test case that
  feeds the substring through the script and asserts exit code 1.
- If you remove an entry from `GATE_PATTERNS`, remove the corresponding
  failing test or replace its body with a different gate-pattern.

Re-run `vendor/bin/phpunit tests/Scripts/CheckDocsBuildLogTest.php` after
the edits and confirm the count of tests still matches the count of
fixtures.

- [ ] **Step 6: Commit**

```bash
git add scripts/check-docs-build-log.php tests/Scripts/CheckDocsBuildLogTest.php
git commit -m "phase-10: check-docs-build-log.php + test (pilot-reconciled)"
```

---

## Task 6: `scripts/lint-docs.php` — `php -l` on fenced blocks + inline-HTML regex

**Files:**
- Create: `scripts/lint-docs.php`
- Create: `tests/Scripts/LintDocsTest.php`

Two algorithms in one script (the spec ties them together because both walk `docs/guide/en/**/*.md`).

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/LintDocsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class LintDocsTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/lintdocs-' . uniqid();
    mkdir($this->tmp . '/docs/guide/en', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesOnValidPhpBlock(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\n\$a = 1;\necho \$a;\n```\n");
    self::assertSame(0, $this->run());
  }

  public function testFailsOnInvalidPhpBlock(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\nfunction broken( {\n```\n");
    self::assertSame(1, $this->run());
  }

  public function testValidBlockWithExplicitOpener(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\n<?php\n\$a = 1;\n```\n");
    self::assertSame(0, $this->run());
  }

  public function testValidBlockStartingWithDeclare(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\ndeclare(strict_types=1);\n\$a = 1;\n```\n");
    self::assertSame(0, $this->run());
  }

  public function testPhpFragmentBlockSkipped(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php-fragment\npublic function foo(): void {}\n```\n");
    self::assertSame(0, $this->run());
  }

  public function testFailsOnInlineAnchorTag(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\nUse <a href=\"foo\">link</a>\n");
    self::assertSame(1, $this->run());
  }

  public function testPassesWithInlineAnchorInBacktickSpan(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\nThe `<a>` element is stripped.\n");
    self::assertSame(0, $this->run());
  }

  public function testFencedBlockHidesInlineHtml(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```html\n<a href=\"foo\">in fence</a>\n```\n");
    self::assertSame(0, $this->run());
  }

  private function run(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/lint-docs.php';
    $cmd = sprintf(
      'PHPBOTGRAM_DOCS_ROOT=%s php %s 2>&1',
      escapeshellarg($this->tmp . '/docs/guide/en'),
      escapeshellarg($script),
    );
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/LintDocsTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the script**

Create `/Users/gruven/repository/github/phpbotgram/scripts/lint-docs.php`:

```php
<?php

declare(strict_types=1);

/**
 * Two-in-one linter for docs/guide/en/**\/*.md:
 *
 * 1. Fenced ```php blocks: extract body, auto-prepend `<?php\n` if missing,
 *    write to a temp file, run `php -l`. Aggregate parse errors.
 * 2. Inline-HTML check: for every line outside fenced code blocks, strip
 *    inline backtick spans, then reject lines containing
 *    `</?(?:a|div|span|table|tr|td|th|img|iframe|script|style)\b`.
 *
 * Honour the env var PHPBOTGRAM_DOCS_ROOT for the source directory (defaults
 * to docs/guide/en relative to the script's repo root). Tests set this.
 *
 * Exit codes:
 *   0 — clean.
 *   1 — at least one violation recorded.
 */

const FORBIDDEN_TAGS = '(?:a|div|span|table|tr|td|th|img|iframe|script|style)';

$root = getenv('PHPBOTGRAM_DOCS_ROOT') ?: (dirname(__DIR__) . '/docs/guide/en');

if (!is_dir($root)) {
  fwrite(STDERR, "lint-docs: source directory not found: {$root}\n");
  exit(1);
}

$errors = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'md') continue;
  lint_file((string)$file, $errors);
}

if ($errors === []) {
  echo "lint-docs: clean (root={$root})\n";
  exit(0);
}

fwrite(STDERR, "lint-docs: FAIL — " . count($errors) . " issue(s)\n");
foreach ($errors as $e) {
  fwrite(STDERR, "  {$e}\n");
}
exit(1);

/** @param list<string> $errors */
function lint_file(string $path, array &$errors): void
{
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) {
    $errors[] = "{$path}: read failed";
    return;
  }

  $inside_fence = false;
  $fence_info = null;
  $fence_buffer = [];
  $fence_start_line = 0;

  foreach ($lines as $idx => $line) {
    $lineno = $idx + 1;

    if (preg_match('/^```\s*(\S*)/', $line, $m)) {
      if ($inside_fence) {
        // Closing fence: process buffer if php/php-fragment.
        process_fence($path, $fence_info, $fence_buffer, $fence_start_line, $errors);
        $inside_fence = false;
        $fence_info = null;
        $fence_buffer = [];
      } else {
        $inside_fence = true;
        $fence_info = $m[1];
        $fence_buffer = [];
        $fence_start_line = $lineno;
      }
      continue;
    }

    if ($inside_fence) {
      $fence_buffer[] = $line;
      continue;
    }

    // Inline-HTML check on non-fenced lines.
    $stripped = preg_replace('/`+[^`]*`+/', '', $line);
    if (preg_match('#</?' . FORBIDDEN_TAGS . '\b#', $stripped)) {
      $errors[] = "{$path}:{$lineno}: inline raw HTML tag is silently stripped by phpDocumentor; use Markdown syntax or the sentinel HTTPS URL (`https://api.phpbotgram.local/...`).";
    }
  }
}

/** @param list<string> $buffer @param list<string> $errors */
function process_fence(string $path, ?string $info, array $buffer, int $startLine, array &$errors): void
{
  if ($info === 'php-fragment') {
    return; // eye-review only
  }
  if ($info !== 'php') {
    return;
  }

  $body = implode("\n", $buffer);
  // Strip only leading newlines, NOT leading indentation. A fenced block
  // inside a list item legitimately starts with spaces; `php -l` doesn't
  // care, but preserving the author's indentation makes error messages
  // (which include the file body) easier to map back to the source.
  $body = ltrim($body, "\r\n");
  if (!str_starts_with($body, '<?php')) {
    $body = "<?php\n" . $body;
  }

  $tmp = tempnam(sys_get_temp_dir(), 'lintdocs-php-');
  file_put_contents($tmp, $body);
  $cmd = sprintf('php -l %s 2>&1', escapeshellarg($tmp));
  exec($cmd, $output, $rc);
  unlink($tmp);

  if ($rc !== 0) {
    $errors[] = "{$path}:{$startLine}: ```php block fails `php -l`: " . trim(implode(' ', $output));
  }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/LintDocsTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add scripts/lint-docs.php tests/Scripts/LintDocsTest.php
git commit -m "phase-10: lint-docs.php + test (php -l + inline-HTML scan)"
```

---

## Task 7: `scripts/check-docs-links.php` — verify sentinel URL targets exist

**Files:**
- Create: `scripts/check-docs-links.php`
- Create: `tests/Scripts/CheckDocsLinksTest.php`

Walks `build/docs/api/guide/**/*.html`, extracts every `<a href="https://api.phpbotgram.local/X">`, and verifies `build/docs/api/classes/X` exists. If the href has `#fragment`, also greps the target HTML for `id="fragment"`.

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/CheckDocsLinksTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CheckDocsLinksTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/checkdocslinks-' . uniqid();
    mkdir($this->tmp . '/build/docs/api/guide/concepts', recursive: true);
    mkdir($this->tmp . '/build/docs/api/classes', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesWhenSentinelTargetExists(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/classes/Foo-Bar.html',
      '<html><body><h2 id="method_baz">baz</h2></body></html>',
    );
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Foo-Bar.html#method_baz">x</a></body></html>',
    );

    self::assertSame(0, $this->run());
  }

  public function testFailsWhenSentinelTargetFileMissing(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Missing.html">x</a></body></html>',
    );

    self::assertSame(1, $this->run());
  }

  public function testFailsWhenAnchorMissing(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/classes/Foo-Bar.html',
      '<html><body><h2 id="method_other">other</h2></body></html>',
    );
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Foo-Bar.html#method_baz">x</a></body></html>',
    );

    self::assertSame(1, $this->run());
  }

  public function testIgnoresNonSentinelLinks(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://example.com/x">external</a><a href="#anchor">frag</a></body></html>',
    );

    self::assertSame(0, $this->run());
  }

  private function run(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-docs-links.php';
    $cmd = sprintf(
      'PHPBOTGRAM_BUILD_ROOT=%s php %s 2>&1',
      escapeshellarg($this->tmp . '/build/docs/api'),
      escapeshellarg($script),
    );
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/CheckDocsLinksTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the script**

Create `/Users/gruven/repository/github/phpbotgram/scripts/check-docs-links.php`:

```php
<?php

declare(strict_types=1);

/**
 * Walks every *.html under <build>/guide/, extracts <a href="https://api.phpbotgram.local/X">
 * sentinel URLs, and verifies:
 *   - <build>/classes/X exists on disk.
 *   - If href has #fragment, the target HTML contains id="fragment".
 *
 * Exit codes:
 *   0 — every sentinel URL resolves.
 *   1 — at least one sentinel URL points at a missing class file or anchor.
 */

const SENTINEL_PREFIX = 'https://api.phpbotgram.local/';

$buildRoot = getenv('PHPBOTGRAM_BUILD_ROOT') ?: (dirname(__DIR__) . '/build/docs/api');
$guideRoot = $buildRoot . '/guide';
$classesRoot = $buildRoot . '/classes';

if (!is_dir($guideRoot)) {
  fwrite(STDERR, "check-docs-links: guide root not found: {$guideRoot}\n");
  exit(1);
}

$errors = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($guideRoot, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'html') continue;
  $body = file_get_contents((string)$file);
  if ($body === false) continue;

  if (preg_match_all('#href="' . preg_quote(SENTINEL_PREFIX, '#') . '([^"#]+)(#[^"]+)?"#', $body, $m, PREG_SET_ORDER)) {
    foreach ($m as $hit) {
      $target = $hit[1];
      $fragment = isset($hit[2]) ? ltrim($hit[2], '#') : null;
      $targetPath = $classesRoot . '/' . $target;

      if (!is_file($targetPath)) {
        $errors[] = sprintf('%s: sentinel target file not found: classes/%s', (string)$file, $target);
        continue;
      }
      if ($fragment !== null) {
        $targetBody = file_get_contents($targetPath);
        if ($targetBody === false || !preg_match('#\bid=["\']' . preg_quote($fragment, '#') . '["\']#', $targetBody)) {
          $errors[] = sprintf('%s: sentinel anchor not found: classes/%s#%s', (string)$file, $target, $fragment);
        }
      }
    }
  }
}

if ($errors === []) {
  echo "check-docs-links: clean\n";
  exit(0);
}

fwrite(STDERR, "check-docs-links: FAIL — " . count($errors) . " broken sentinel URL(s)\n");
foreach ($errors as $e) {
  fwrite(STDERR, "  {$e}\n");
}
exit(1);
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/CheckDocsLinksTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add scripts/check-docs-links.php tests/Scripts/CheckDocsLinksTest.php
git commit -m "phase-10: check-docs-links.php + test"
```

---

## Task 8: `scripts/rewrite-api-links.php` — HTML-aware sentinel rewrite

**Files:**
- Create: `scripts/rewrite-api-links.php`
- Create: `tests/Scripts/RewriteApiLinksTest.php`

DOM-walks every `.html` under `build/docs/api/guide/`, rewrites every `<a href="https://api.phpbotgram.local/X">` to `<a href="classes/X">`. Text content under `<pre>`/`<code>`/`<kbd>`/`<samp>` and non-`<a>@href` attribute values are preserved. Post-rewrite assertion: no leftover sentinel substring outside the exclusions.

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/RewriteApiLinksTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class RewriteApiLinksTest extends TestCase
{
  private string $tmp;
  private string $guideRoot;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/rewriteapi-' . uniqid();
    $this->guideRoot = $this->tmp . '/build/docs/api/guide';
    mkdir($this->guideRoot, recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testRewritesSentinelHref(): void
  {
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Foo-Bar.html#method_baz">label</a></body></html>',
    );
    self::assertSame(0, $this->run());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringContainsString('href="classes/Foo-Bar.html#method_baz"', $after);
    self::assertStringNotContainsString('https://api.phpbotgram.local/', $after);
  }

  public function testPreservesSentinelInCodeBlock(): void
  {
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><pre><code>[X](https://api.phpbotgram.local/Foo.html)</code></pre></body></html>',
    );
    self::assertSame(0, $this->run());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringContainsString('[X](https://api.phpbotgram.local/Foo.html)', $after);
  }

  public function testPreservesSentinelInImgAlt(): void
  {
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><img alt="see https://api.phpbotgram.local/Foo.html" src="diagram.svg"></body></html>',
    );
    self::assertSame(0, $this->run());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringContainsString('alt="see https://api.phpbotgram.local/Foo.html"', $after);
  }

  public function testFailsOnLeftoverSentinelInPageBody(): void
  {
    // A `<p>` containing a bare sentinel URL outside an <a> — should fail the
    // post-rewrite assertion because the sentinel is in normal text (not in
    // a code/pre/kbd/samp element, not in an allowed attribute, not in <a>@href).
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><p>Visit https://api.phpbotgram.local/Foo.html</p></body></html>',
    );
    self::assertSame(1, $this->run());
  }

  public function testPreservesHtml5Doctype(): void
  {
    // Regression: DOMDocument::loadHTML defaults to substituting an HTML 4.01
    // PUBLIC doctype when LIBXML_HTML_NODEFDTD is missing. Real phpdoc output
    // uses `<!DOCTYPE html>` (HTML5); the rewrite must round-trip it intact.
    $original = "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"UTF-8\"><title>x</title></head><body><a href=\"https://api.phpbotgram.local/Foo.html\">x</a></body></html>";
    file_put_contents($this->guideRoot . '/x.html', $original);
    self::assertSame(0, $this->run());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringStartsWith('<!DOCTYPE html>', ltrim($after));
    self::assertStringNotContainsString('-//W3C//DTD HTML 4.01//EN', $after);
    self::assertStringContainsString('href="classes/Foo.html"', $after);
  }

  private function run(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/rewrite-api-links.php';
    $cmd = sprintf(
      'PHPBOTGRAM_BUILD_ROOT=%s php %s 2>&1',
      escapeshellarg($this->tmp . '/build/docs/api'),
      escapeshellarg($script),
    );
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/RewriteApiLinksTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the script**

Create `/Users/gruven/repository/github/phpbotgram/scripts/rewrite-api-links.php`:

```php
<?php

declare(strict_types=1);

/**
 * HTML-aware sentinel URL rewrite for every *.html under <build>/guide/.
 *
 * For each <a href="https://api.phpbotgram.local/X..."> element:
 *   - Rewrite the href to "classes/X..." (preserving anchor).
 *   - Leave text content, all other attributes, and other elements untouched.
 *
 * Post-rewrite assertion: no leftover "https://api.phpbotgram.local/" substring
 * in any element OUTSIDE the documented exclusions:
 *   - Text content under <pre>, <code>, <kbd>, <samp>.
 *   - Attribute values other than <a>@href (e.g. <img alt>, <a title>).
 *
 * Exit codes:
 *   0 — rewrite succeeded, assertion passed.
 *   1 — assertion failed (leftover sentinel) or write failure.
 */

const SENTINEL_PREFIX = 'https://api.phpbotgram.local/';
const REPLACE_PREFIX = 'classes/';

$buildRoot = getenv('PHPBOTGRAM_BUILD_ROOT') ?: (dirname(__DIR__) . '/build/docs/api');
$guideRoot = $buildRoot . '/guide';

if (!is_dir($guideRoot)) {
  fwrite(STDERR, "rewrite-api-links: guide root not found: {$guideRoot}\n");
  exit(1);
}

$failures = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($guideRoot, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'html') continue;

  $path = (string)$file;
  rewrite_page($path, $failures);
}

if ($failures !== []) {
  fwrite(STDERR, "rewrite-api-links: FAIL — " . count($failures) . " leftover sentinel(s)\n");
  foreach ($failures as $f) {
    fwrite(STDERR, "  {$f}\n");
  }
  exit(1);
}

echo "rewrite-api-links: clean\n";
exit(0);

/** @param list<string> $failures */
function rewrite_page(string $path, array &$failures): void
{
  $body = file_get_contents($path);
  if ($body === false) {
    $failures[] = "{$path}: read failed";
    return;
  }

  // Preserve the original doctype literal. libxml's HTML parser, even with
  // LIBXML_HTML_NODEFDTD, can collapse `<!DOCTYPE html>` (HTML5) to its
  // canonical form on serialization. Capturing and re-injecting the original
  // bytes keeps the rewrite a true no-op for the doctype line.
  $originalDoctype = null;
  if (preg_match('#^\s*(<!DOCTYPE[^>]*>)#i', $body, $m)) {
    $originalDoctype = $m[1];
  }

  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  // LIBXML_HTML_NODEFDTD prevents libxml from substituting an HTML 4.01
  // PUBLIC doctype when the input already declares `<!DOCTYPE html>`.
  $loaded = $dom->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
  libxml_clear_errors();
  if (!$loaded) {
    $failures[] = "{$path}: HTML parse failed";
    return;
  }

  $xpath = new DOMXPath($dom);
  foreach ($xpath->query('//a[@href]') as $a) {
    $href = $a->getAttribute('href');
    if (str_starts_with($href, SENTINEL_PREFIX)) {
      $a->setAttribute('href', REPLACE_PREFIX . substr($href, strlen(SENTINEL_PREFIX)));
    }
  }

  $rewritten = $dom->saveHTML();
  if ($rewritten === false) {
    $failures[] = "{$path}: write failed";
    return;
  }

  // Sanity guard: if libxml choked on weird input it can return a tree
  // missing most of the body. Refuse to write back anything <50% of the
  // original byte count — that almost certainly means we'd zero-out the
  // page silently. The threshold is conservative; legitimate HTML rewrites
  // change a handful of href attributes and stay close to the original size.
  if (strlen($rewritten) < (int)(strlen($body) * 0.5)) {
    $failures[] = sprintf(
      '%s: rewrite shrank output (%d → %d bytes); refusing to write',
      $path,
      strlen($body),
      strlen($rewritten),
    );
    return;
  }

  // Re-inject the original doctype literal if libxml mangled it.
  if ($originalDoctype !== null) {
    $rewritten = preg_replace(
      '#^\s*<!DOCTYPE[^>]*>#i',
      $originalDoctype,
      $rewritten,
      1,
    );
  }

  if (file_put_contents($path, $rewritten) === false) {
    $failures[] = "{$path}: write failed";
    return;
  }

  // Post-rewrite assertion: walk text nodes outside <pre>/<code>/<kbd>/<samp>;
  // bare sentinel substring there is a violation.
  $reloaded = new DOMDocument();
  libxml_use_internal_errors(true);
  $reloaded->loadHTML($rewritten, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
  libxml_clear_errors();
  $xpath = new DOMXPath($reloaded);
  $textNodes = $xpath->query('//text()[not(ancestor::pre or ancestor::code or ancestor::kbd or ancestor::samp)]');
  foreach ($textNodes as $node) {
    if (str_contains($node->textContent, SENTINEL_PREFIX)) {
      $failures[] = "{$path}: leftover sentinel in text content: " . trim(substr($node->textContent, 0, 80));
    }
  }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/RewriteApiLinksTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add scripts/rewrite-api-links.php tests/Scripts/RewriteApiLinksTest.php
git commit -m "phase-10: rewrite-api-links.php + test (HTML-aware DOM rewrite)"
```

---

## Task 9: `scripts/check-internal-links.php` — base-href-aware internal link resolver

**Files:**
- Create: `scripts/check-internal-links.php`
- Create: `tests/Scripts/CheckInternalLinksTest.php`

Walks `build/docs/api/guide/**/*.html`. Extracts every `href`. Skips external (`http://`/`https://`), `mailto:`, and the rewritten `classes/` paths (those are checked by Task 7 before the rewrite). Validates fragment-only (`#…`) links against the same page's `id`s. Resolves remaining relative paths against the page's `<base href>`.

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/CheckInternalLinksTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CheckInternalLinksTest extends TestCase
{
  private string $tmp;
  private string $apiRoot;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/checkinternal-' . uniqid();
    $this->apiRoot = $this->tmp . '/build/docs/api';
    mkdir($this->apiRoot . '/guide/concepts', recursive: true);
    mkdir($this->apiRoot . '/guide/how-to', recursive: true);
    mkdir($this->apiRoot . '/classes', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesOnValidLinks(): void
  {
    file_put_contents($this->apiRoot . '/classes/Foo.html', '<html><body><span id="method_bar"></span></body></html>');
    file_put_contents($this->apiRoot . '/guide/how-to/other.html', '<html></html>');
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><head><base href="../../"></head><body>'
      . '<a href="guide/how-to/other.html">cross</a>'
      . '<a href="classes/Foo.html#method_bar">api</a>'
      . '<a href="#section1">frag</a>'
      . '<span id="section1"></span>'
      . '</body></html>',
    );

    self::assertSame(0, $this->run());
  }

  public function testFailsOnMissingFile(): void
  {
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><head><base href="../../"></head><body><a href="guide/missing.html">x</a></body></html>',
    );

    self::assertSame(1, $this->run());
  }

  public function testFailsOnMissingFragmentAnchor(): void
  {
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><body><a href="#nowhere">x</a></body></html>',
    );

    self::assertSame(1, $this->run());
  }

  public function testIgnoresExternalAndMailto(): void
  {
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><body>'
      . '<a href="https://example.com/x">ext</a>'
      . '<a href="mailto:foo@bar">mail</a>'
      . '</body></html>',
    );

    self::assertSame(0, $this->run());
  }

  public function testResolvesShallowBaseHref(): void
  {
    // Regression for a depth-1 page that uses `<base href="../">`
    // (one level up from the page's directory). The previous depth-2
    // test (`<base href="../../">`) didn't exercise this shape.
    file_put_contents($this->apiRoot . '/classes/Foo.html', '<html><body></body></html>');
    file_put_contents(
      $this->apiRoot . '/guide/index.html',
      '<html><head><base href="../"></head><body>'
      . '<a href="classes/Foo.html">api</a>'
      . '</body></html>',
    );

    self::assertSame(0, $this->run());
  }

  private function run(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-internal-links.php';
    $cmd = sprintf(
      'PHPBOTGRAM_BUILD_ROOT=%s php %s 2>&1',
      escapeshellarg($this->apiRoot),
      escapeshellarg($script),
    );
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/CheckInternalLinksTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the script**

Create `/Users/gruven/repository/github/phpbotgram/scripts/check-internal-links.php`:

```php
<?php

declare(strict_types=1);

/**
 * Walks every *.html under <build>/guide/, validates every <a href> internal link:
 *
 *   - http://, https://, mailto: → skipped (out of scope).
 *   - Fragment-only (#…) → checked against the same page's id="…" attributes.
 *   - Other paths → resolved against the page's <base href>, then joined with
 *     <build>/, then file-existence check. If the link has #fragment, the
 *     target HTML's id="…" set is also checked.
 *
 * Exit codes:
 *   0 — every link resolves.
 *   1 — at least one broken link.
 */

$buildRootInput = getenv('PHPBOTGRAM_BUILD_ROOT') ?: (dirname(__DIR__) . '/build/docs/api');
$buildRoot = realpath($buildRootInput);
if ($buildRoot === false) {
  fwrite(STDERR, "check-internal-links: build root not found: {$buildRootInput}\n");
  exit(1);
}
$guideRoot = $buildRoot . '/guide';

if (!is_dir($guideRoot)) {
  fwrite(STDERR, "check-internal-links: guide root not found: {$guideRoot}\n");
  exit(1);
}

$errors = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($guideRoot, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'html') continue;
  check_page((string)$file, $buildRoot, $errors);
}

if ($errors === []) {
  echo "check-internal-links: clean\n";
  exit(0);
}

fwrite(STDERR, "check-internal-links: FAIL — " . count($errors) . " broken link(s)\n");
foreach ($errors as $e) {
  fwrite(STDERR, "  {$e}\n");
}
exit(1);

/** @param list<string> $errors */
function check_page(string $path, string $buildRoot, array &$errors): void
{
  $body = file_get_contents($path);
  if ($body === false) {
    $errors[] = "{$path}: read failed";
    return;
  }

  $base = preg_match('#<base href="([^"]+)"#', $body, $m) ? $m[1] : './';
  $ownIds = id_set($body);

  preg_match_all('#<a[^>]+href="([^"]+)"#i', $body, $matches);
  foreach ($matches[1] as $href) {
    if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) continue;
    if (str_starts_with($href, 'mailto:')) continue;

    if (str_starts_with($href, '#')) {
      $frag = substr($href, 1);
      if (!isset($ownIds[$frag])) {
        $errors[] = "{$path}: in-page anchor not found: #{$frag}";
      }
      continue;
    }

    [$pathPart, $fragPart] = array_pad(explode('#', $href, 2), 2, null);
    // Resolve against <base href>. Since <base href> is depth-adaptive
    // (../, ../../, …), joining with the rendered page's directory and
    // normalizing is equivalent to resolving against the build root.
    //
    // Canonicalise $pageDir via realpath() so symlink-traversal mismatches
    // (notably macOS, where /var → /private/var and the tmp-dir iterator
    // yields /var/folders/… while realpath($buildRoot) yields
    // /private/var/folders/…) don't make the str_starts_with check
    // unconditionally fail.
    $pageDirReal = realpath(dirname($path));
    if ($pageDirReal === false) {
      $errors[] = "{$path}: realpath of page dir failed";
      continue;
    }
    $resolved = realpath_logical($pageDirReal . '/' . $base . $pathPart);

    if ($resolved === null || !str_starts_with($resolved, $buildRoot)) {
      $errors[] = "{$path}: link target escapes build root: {$href}";
      continue;
    }
    if (!is_file($resolved)) {
      $errors[] = "{$path}: link target file missing: {$href}";
      continue;
    }

    if ($fragPart !== null) {
      $targetBody = file_get_contents($resolved);
      if ($targetBody === false || !isset(id_set($targetBody)[$fragPart])) {
        $errors[] = "{$path}: link target anchor missing: {$href}";
      }
    }
  }
}

/** @return array<string, true> */
function id_set(string $html): array
{
  preg_match_all('#\sid=["\']([^"\']+)["\']#i', $html, $m);

  return array_fill_keys($m[1] ?? [], true);
}

function realpath_logical(string $path): ?string
{
  // Lexical path normalisation without resolving symlinks (we may target
  // files that the build hasn't created yet during validation).
  $parts = [];
  foreach (explode('/', $path) as $part) {
    if ($part === '' || $part === '.') continue;
    if ($part === '..') {
      array_pop($parts);
      continue;
    }
    $parts[] = $part;
  }

  return '/' . implode('/', $parts);
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/CheckInternalLinksTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add scripts/check-internal-links.php tests/Scripts/CheckInternalLinksTest.php
git commit -m "phase-10: check-internal-links.php + test (base-href-aware)"
```

---

## Task 10: `scripts/check-docs-examples.php` — verify `examples/X.php` links

**Files:**
- Create: `scripts/check-docs-examples.php`
- Create: `tests/Scripts/CheckDocsExamplesTest.php`

Walks `docs/guide/en/**/*.md`. Extracts every Markdown link of the shape `examples/<name>.php` (also accepts the absolute github-blob URL form). Verifies each name corresponds to a file under `examples/` in the repo.

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/CheckDocsExamplesTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CheckDocsExamplesTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/checkexamples-' . uniqid();
    mkdir($this->tmp . '/docs/guide/en/tutorial', recursive: true);
    mkdir($this->tmp . '/examples', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesWhenAllExampleFilesExist(): void
  {
    touch($this->tmp . '/examples/echo_bot.php');
    touch($this->tmp . '/examples/webhook_bot.php');
    file_put_contents(
      $this->tmp . '/docs/guide/en/tutorial/02-first-bot.md',
      "See [echo](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot.php).\n"
      . "Or [webhook](examples/webhook_bot.php).\n",
    );

    self::assertSame(0, $this->run());
  }

  public function testFailsWhenExampleMissing(): void
  {
    file_put_contents(
      $this->tmp . '/docs/guide/en/tutorial/02-first-bot.md',
      "See [echo](examples/missing.php).\n",
    );

    self::assertSame(1, $this->run());
  }

  private function run(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-docs-examples.php';
    $cmd = sprintf(
      'PHPBOTGRAM_DOCS_ROOT=%s PHPBOTGRAM_EXAMPLES_ROOT=%s php %s 2>&1',
      escapeshellarg($this->tmp . '/docs/guide/en'),
      escapeshellarg($this->tmp . '/examples'),
      escapeshellarg($script),
    );
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/CheckDocsExamplesTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the script**

Create `/Users/gruven/repository/github/phpbotgram/scripts/check-docs-examples.php`:

```php
<?php

declare(strict_types=1);

/**
 * Walks docs/guide/en/**\/*.md, extracts every Markdown link whose URL ends
 * with `examples/<name>.php` (relative path or full github-blob URL), and
 * verifies each name corresponds to a file under examples/.
 *
 * Exit codes:
 *   0 — every linked example exists on disk.
 *   1 — at least one broken example link.
 */

$docsRoot = getenv('PHPBOTGRAM_DOCS_ROOT') ?: (dirname(__DIR__) . '/docs/guide/en');
$examplesRoot = getenv('PHPBOTGRAM_EXAMPLES_ROOT') ?: (dirname(__DIR__) . '/examples');

if (!is_dir($docsRoot)) {
  fwrite(STDERR, "check-docs-examples: docs root not found: {$docsRoot}\n");
  exit(1);
}

$errors = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docsRoot, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'md') continue;
  $body = file_get_contents((string)$file);
  if ($body === false) continue;

  // Match Markdown links whose URL ends with examples/<name>.php
  preg_match_all('#\]\(([^)]*examples/([A-Za-z0-9_./-]+\.php))\)#', $body, $matches, PREG_SET_ORDER);
  foreach ($matches as $hit) {
    $name = $hit[2];
    if (!is_file($examplesRoot . '/' . $name)) {
      $errors[] = "{$file}: examples/{$name} does not exist";
    }
  }
}

if ($errors === []) {
  echo "check-docs-examples: clean\n";
  exit(0);
}

fwrite(STDERR, "check-docs-examples: FAIL — " . count($errors) . " missing example(s)\n");
foreach ($errors as $e) {
  fwrite(STDERR, "  {$e}\n");
}
exit(1);
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/CheckDocsExamplesTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add scripts/check-docs-examples.php tests/Scripts/CheckDocsExamplesTest.php
git commit -m "phase-10: check-docs-examples.php + test"
```

---

## Task 11: `scripts/update-versions-json.php` — atomic versions.json updater

**Files:**
- Create: `scripts/update-versions-json.php`
- Create: `tests/Scripts/UpdateVersionsJsonTest.php`

CLI: `update-versions-json.php <path> --upsert id=<id> path=<path> label=<label> stable=<true|false|auto>`.

- [ ] **Step 1: Write the failing test**

Create `/Users/gruven/repository/github/phpbotgram/tests/Scripts/UpdateVersionsJsonTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class UpdateVersionsJsonTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/uvj-' . uniqid();
    mkdir($this->tmp, recursive: true);
  }

  protected function tearDown(): void
  {
    foreach (glob($this->tmp . '/*') as $f) unlink($f);
    rmdir($this->tmp);
  }

  public function testFirstDevPush(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions": []}');

    $this->runScript($path, ['id=dev', 'path=en/dev/', 'label=dev (master)', 'stable=false']);

    $data = json_decode(file_get_contents($path), true);
    self::assertCount(1, $data['versions']);
    self::assertSame('dev', $data['versions'][0]['id']);
    self::assertFalse($data['versions'][0]['stable']);
  }

  public function testFirstTagPushFlagsStable(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions": [{"id":"dev","path":"en/dev/","label":"dev","stable":false}]}');

    $this->runScript($path, ['id=v0.1.0', 'path=en/v0.1.0/', 'label=v0.1.0', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    self::assertCount(2, $data['versions']);
    // newest first
    self::assertSame('v0.1.0', $data['versions'][0]['id']);
    self::assertTrue($data['versions'][0]['stable']);
    self::assertSame('dev', $data['versions'][1]['id']);
    self::assertFalse($data['versions'][1]['stable']);
  }

  public function testSecondTagFlipsPriorStable(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":['
      . '{"id":"v0.1.0","path":"en/v0.1.0/","label":"v0.1.0","stable":true},'
      . '{"id":"dev","path":"en/dev/","label":"dev","stable":false}'
      . ']}');

    $this->runScript($path, ['id=v0.2.0', 'path=en/v0.2.0/', 'label=v0.2.0', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    self::assertSame('v0.2.0', $data['versions'][0]['id']);
    self::assertTrue($data['versions'][0]['stable']);
    self::assertSame('v0.1.0', $data['versions'][1]['id']);
    self::assertFalse($data['versions'][1]['stable']);
  }

  public function testBackportPublishLeavesNewerStable(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":['
      . '{"id":"v0.2.0","path":"en/v0.2.0/","label":"v0.2.0","stable":true},'
      . '{"id":"dev","path":"en/dev/","label":"dev","stable":false}'
      . ']}');

    $this->runScript($path, ['id=v0.1.1', 'path=en/v0.1.1/', 'label=v0.1.1', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    $ids = array_column($data['versions'], 'id');
    $byId = array_combine($ids, $data['versions']);
    self::assertTrue($byId['v0.2.0']['stable']);
    self::assertFalse($byId['v0.1.1']['stable']);
    self::assertFalse($byId['dev']['stable']);
  }

  public function testForcePushDeduplicates(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":[{"id":"v0.1.0","path":"en/v0.1.0/","label":"v0.1.0","stable":true}]}');

    $this->runScript($path, ['id=v0.1.0', 'path=en/v0.1.0/', 'label=v0.1.0', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    self::assertCount(1, $data['versions']);
  }

  public function testForcePushOfOlderTagDoesNotReclaimStable(): void
  {
    // Edge case: a maintainer force-pushes the v0.1.0 tag to fix a
    // typo, but v0.2.0 has since been released and is the stable tag.
    // The dedup pass drops the old v0.1.0; then isNewestTag must
    // observe the surviving v0.2.0 entry so the re-pushed v0.1.0 does
    // NOT steal the stable flag.
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":['
      . '{"id":"v0.2.0","path":"en/v0.2.0/","label":"v0.2.0","stable":true},'
      . '{"id":"v0.1.0","path":"en/v0.1.0/","label":"v0.1.0","stable":false}'
      . ']}');

    $this->runScript($path, ['id=v0.1.0', 'path=en/v0.1.0/', 'label=v0.1.0 (re-tagged)', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    $byId = array_column($data['versions'], null, 'id');
    self::assertCount(2, $data['versions']);
    self::assertTrue($byId['v0.2.0']['stable'], 'v0.2.0 must retain stable flag');
    self::assertFalse($byId['v0.1.0']['stable'], 'force-pushed older tag must not reclaim stable');
    self::assertSame('v0.1.0 (re-tagged)', $byId['v0.1.0']['label']);
  }

  public function testLabelContainingSpaces(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions": []}');

    $this->runScript($path, ['id=dev', 'path=en/dev/', 'label=dev (master)', 'stable=false']);

    $data = json_decode(file_get_contents($path), true);
    self::assertSame('dev (master)', $data['versions'][0]['label']);
  }

  /** @param list<string> $args */
  private function runScript(string $path, array $args): void
  {
    $script = dirname(__DIR__, 2) . '/scripts/update-versions-json.php';
    $cmd = sprintf(
      'php %s %s --upsert %s 2>&1',
      escapeshellarg($script),
      escapeshellarg($path),
      implode(' ', array_map(escapeshellarg(...), $args)),
    );
    exec($cmd, $output, $rc);
    self::assertSame(0, $rc, "Script failed: " . implode("\n", $output));
  }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/Scripts/UpdateVersionsJsonTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the script**

Create `/Users/gruven/repository/github/phpbotgram/scripts/update-versions-json.php`:

```php
<?php

declare(strict_types=1);

/**
 * Atomic versions.json updater used by both Phase 10 workflows.
 *
 * CLI:
 *   update-versions-json.php <path> --upsert id=<id> path=<path> label=<label> stable=<true|false|auto>
 *
 * Each key=value arg is parsed with explode('=', $arg, 2) so label values
 * containing `=` survive intact.
 *
 * Algorithm:
 *   1. Load existing JSON (or initialise to {"versions": []}).
 *   2. Dedup: drop any existing entry whose id == new id.
 *   3. Stable flag handling:
 *      - stable=auto: if new id strictly greater than every tag-shaped
 *        ("v\d+\.\d+\.\d+") existing id, set new entry stable=true and flip
 *        every other entry's stable to false. Otherwise (backport / out-of-
 *        order) set new entry stable=false; leave others alone.
 *      - stable=true: flip all others to false; set this entry stable=true.
 *      - stable=false: leave others alone.
 *   4. Insert at array head (newest first).
 *   5. Atomic write: write to .tmp, rename.
 *
 * Exit codes:
 *   0 — written successfully.
 *   1 — usage / parse / write failure.
 */

if ($argc < 4 || $argv[2] !== '--upsert') {
  fwrite(STDERR, "Usage: update-versions-json.php <path> --upsert id=<id> path=<path> label=<label> stable=<true|false|auto>\n");
  exit(1);
}

$path = $argv[1];
$argsRaw = array_slice($argv, 3);

$entry = [];
foreach ($argsRaw as $raw) {
  $parts = explode('=', $raw, 2);
  if (count($parts) !== 2) {
    fwrite(STDERR, "update-versions-json: malformed arg '{$raw}' (expected key=value)\n");
    exit(1);
  }
  $entry[$parts[0]] = $parts[1];
}

$required = ['id', 'path', 'label', 'stable'];
foreach ($required as $k) {
  if (!isset($entry[$k])) {
    fwrite(STDERR, "update-versions-json: missing required key '{$k}'\n");
    exit(1);
  }
}

$existingJson = file_exists($path) ? file_get_contents($path) : null;
$data = ($existingJson === null || trim($existingJson) === '')
  ? ['versions' => []]
  : json_decode($existingJson, true);
if (!is_array($data) || !isset($data['versions']) || !is_array($data['versions'])) {
  $data = ['versions' => []];
}

// 1. Dedup
$data['versions'] = array_values(array_filter(
  $data['versions'],
  static fn(array $v): bool => ($v['id'] ?? null) !== $entry['id'],
));

// 2. Resolve stable flag
$stableInput = $entry['stable'];
$entry['stable'] = $stableInput === 'true';

$tagShaped = static fn(string $id): bool => preg_match('/^v\d+\.\d+\.\d+/', $id) === 1;
$isNewestTag = static function (string $newId, array $versions) use ($tagShaped): bool {
  foreach ($versions as $v) {
    if (($v['id'] ?? null) !== null && $tagShaped($v['id']) && version_compare($v['id'], $newId, '>=')) {
      return false;
    }
  }
  return true;
};

if ($stableInput === 'auto') {
  $entry['stable'] = $tagShaped($entry['id']) && $isNewestTag($entry['id'], $data['versions']);
  if ($entry['stable']) {
    foreach ($data['versions'] as &$v) {
      $v['stable'] = false;
    }
    unset($v);
  }
} elseif ($stableInput === 'true') {
  foreach ($data['versions'] as &$v) {
    $v['stable'] = false;
  }
  unset($v);
}

// 3. Insert newest-first
array_unshift($data['versions'], $entry);

// 4. Atomic write
$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$tmp = $path . '.tmp';
if (file_put_contents($tmp, $encoded . "\n") === false) {
  fwrite(STDERR, "update-versions-json: write failed: {$tmp}\n");
  exit(1);
}
if (!rename($tmp, $path)) {
  @unlink($tmp);
  fwrite(STDERR, "update-versions-json: rename failed: {$tmp} -> {$path}\n");
  exit(1);
}

echo "update-versions-json: {$path} updated (id={$entry['id']}, stable=" . ($entry['stable'] ? 'true' : 'false') . ")\n";
exit(0);
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/Scripts/UpdateVersionsJsonTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add scripts/update-versions-json.php tests/Scripts/UpdateVersionsJsonTest.php
git commit -m "phase-10: update-versions-json.php + test (semver-aware)"
```

---

## Task 12: `.phpdoc/template/` Twig override — navbar switcher

**Files:**
- Create: `.phpdoc/template/components/header.html.twig`
- Create: `.phpdoc/template/_includes/switcher.html.twig`

phpDocumentor's `ProvideTemplateOverridePathMiddleware` registers `.phpdoc/template/` (relative to the config file) as a higher-priority Twig `FilesystemLoader` in front of the bundled template. The Twig `ChainLoader` therefore consults `.phpdoc/template/` first for any template name, then falls back to the upstream `default` template files.

Implication: we only need to ship the ONE file whose content we want to change — `components/header.html.twig`. When upstream `layout.html.twig` (still loaded from the phar) does `{% include 'components/header.html.twig' %}`, Twig finds our override first. We do **not** need to ship a verbatim copy of `layout.html.twig`; doing so would freeze the upstream layout against a specific phpdoc release and force a manual re-sync on every patch bump.

The switcher renders inside the visible navbar bar (rather than as a stripe between `<header>` and `<main>`), which is what the spec calls for in §"Version + language switcher".

- [ ] **Step 1: Extract the upstream header template for reference**

```bash
mkdir -p /tmp/phpdoc-templates

# Sanity probe: confirm vendor/bin/phpdoc is loadable as a phar before
# extracting. phpdocumentor/shim ^3 installs the real phar here; if a
# future shim release switches to a wrapper script, this command will
# error out with "is not a Phar archive" and the extraction needs to
# target the real phar path inside vendor/phpdocumentor/shim/.
PHAR_PATH="$(php -r 'echo realpath("vendor/bin/phpdoc");')"
php -r "Phar::loadPhar('$PHAR_PATH'); echo 'phar OK: $PHAR_PATH', PHP_EOL;"

php -r "Phar::loadPhar('$PHAR_PATH'); copy('phar://$PHAR_PATH/data/templates/default/components/header.html.twig', '/tmp/phpdoc-templates/header.html.twig');"
cat /tmp/phpdoc-templates/header.html.twig
```

Expected: the first `php -r` prints `phar OK: <abs path>`. If it raises `UnexpectedValueException: ... is not a Phar archive`, stop and re-discover the phar location via `find vendor/phpdocumentor -name '*.phar'`; the shim's filename convention has changed.

In `header.html.twig` locate the navbar/`<header>` element — that's where the switcher's host `<div>` is rendered so it visually sits inside the navbar bar (typically right-aligned, alongside the search input). The exact insertion site depends on the upstream markup; usually the safe spot is right before the closing tag of whichever navbar container holds the search input.

We do NOT extract `layout.html.twig` because we do NOT ship a layout override — phpdoc's Twig `ChainLoader` will fall back to the bundled `layout.html.twig`, which already does `{% include 'components/header.html.twig' %}`. Our `.phpdoc/template/components/header.html.twig` intercepts that include via the higher-priority override path.

- [ ] **Step 2: Write the switcher partial**

Create `/Users/gruven/repository/github/phpbotgram/.phpdoc/template/_includes/switcher.html.twig`:

```twig
{# Phase 10 — version + language switcher.
   Populates two <select> elements from /versions.json and /languages.json,
   served at the gh-pages branch root. Path resolution is base-href-derived
   (no leading-slash absolute URLs — those would 404 on user-pages
   deployments).

   Styling notes:
   - margin-left:auto pushes the switcher to the right end of the navbar's
     flex container (alongside the search input).
   - inline style block intentional — phpdoc's CSS pipeline doesn't pick up
     ad-hoc files we drop into .phpdoc/template/, so colocating layout here
     keeps the override one Twig file and one inline <style>. #}
<div class="phpbotgram-switcher" style="display:flex; gap:0.5rem; padding:0.5rem; margin-left:auto; align-items:center;">
  <select id="phpbotgram-lang-select" aria-label="Language" disabled style="font-size:0.85rem; padding:0.2rem 0.4rem;"></select>
  <select id="phpbotgram-version-select" aria-label="Version" disabled style="font-size:0.85rem; padding:0.2rem 0.4rem;"></select>
</div>
<script>
(function () {
  const base = new URL(document.baseURI);
  const langRoot = base.pathname.replace(/\/[^/]+\/$/, '/');
  const repoRoot = langRoot.replace(/\/[^/]+\/$/, '/');
  const repoRootUrl = base.origin + repoRoot;

  const versionSelect = document.getElementById('phpbotgram-version-select');
  const langSelect = document.getElementById('phpbotgram-lang-select');

  function populate(el, items, currentId, formatter) {
    el.innerHTML = '';
    for (const it of items) {
      const opt = document.createElement('option');
      opt.value = it.path;
      opt.textContent = formatter(it);
      if (it.id === currentId) opt.selected = true;
      el.appendChild(opt);
    }
    el.disabled = false;
    el.addEventListener('change', function () {
      window.location.href = base.origin + repoRoot + el.value;
    });
  }

  const pathParts = base.pathname.split('/').filter(Boolean);
  const currentLang = pathParts.length >= 2 ? pathParts[pathParts.length - 2] : null;
  const currentVersion = pathParts.length >= 1 ? pathParts[pathParts.length - 1] : null;

  Promise.all([
    fetch(repoRootUrl + 'versions.json').then(r => r.json()),
    fetch(repoRootUrl + 'languages.json').then(r => r.json()),
  ]).then(function ([versions, languages]) {
    populate(versionSelect, versions.versions || [], currentVersion,
      v => v.label + (v.stable ? ' (latest)' : ''));
    populate(langSelect, (languages.languages || []).map(l => ({
      id: l.id,
      label: l.label,
      path: l.id + '/' + currentVersion + '/',
    })), currentLang, l => l.label);
  }).catch(function (err) {
    console.warn('phpbotgram switcher: failed to load inventory', err);
  });
})();
</script>
```

- [ ] **Step 3: Write the header override**

Create `/Users/gruven/repository/github/phpbotgram/.phpdoc/template/components/header.html.twig` by **copying** the upstream `data/templates/default/components/header.html.twig` extracted in Step 1 and adding ONE include of the switcher partial inside the navbar container (typically alongside the search-input form).

Pseudo-edit (apply to the real extracted file — the exact insertion site depends on the upstream markup; locate the navbar `<form>` or search-related container):

```twig
{# ...existing upstream header markup... #}
<header class="phpdocumentor-on-this-page__header">
  {# ...existing logo / nav / search form... #}
  {{ include('_includes/switcher.html.twig') }}    {# ← Phase 10 addition: navbar switcher #}
</header>
{# ...existing upstream content... #}
```

The full file is a verbatim copy of the phar's `components/header.html.twig` plus this one extra include placed inside the visible navbar bar so the switcher is right-aligned alongside the existing search input (and inherits the navbar's flex layout). Do not invent any other changes. If the upstream version's header markup looks different on a future phpdoc bump, adjust the insertion site and bump the phpdoc-version pin checklist note in §"Version + language switcher" of the spec.

- [ ] **Step 4: Verify the override compiles AND renders inside the navbar**

Build the API docs (with the new config + no narrative content yet — phpdoc tolerates an empty `docs/guide/en/`):

```bash
mkdir -p docs/guide/en
touch docs/guide/en/index.md && echo "# phpbotgram" > docs/guide/en/index.md
VERSION=0.1.0-dev bash scripts/build-docs.sh 2>&1 | tail -10 || true
```

The build will fail at one of the gates (no real content yet) — that's expected. What matters is that the phpdoc step itself completed and rendered the template. Inspect `build/docs/api/index.html`:

```bash
grep -c 'phpbotgram-switcher' build/docs/api/index.html
# Expected: 1 — the switcher partial rendered.

# Sanity check it actually landed INSIDE the visible navbar — not just
# anywhere within <header>, but in the same horizontal flex container
# that holds the search input.
#
# Heuristic: identify the search-input element (phpdoc uses a `<form>`
# with class containing `search` or an `<input>` with name="search").
# Assert the switcher class appears AFTER an opening tag that is the
# closest containing flex/nav element, AND the search input appears in
# the SAME containment block.
php -r '
$h = file_get_contents("build/docs/api/index.html");
if (!preg_match("#<header[^>]*>(.*?)</header>#is", $h, $m)) { echo "FAIL: no <header>\n"; exit(1); }
$header = $m[1];
if (!str_contains($header, "phpbotgram-switcher")) { echo "FAIL: switcher missing from <header>\n"; exit(1); }
// Must coexist with the search input inside <header>. phpdoc renders
// either a <form>/<input> containing "search" or a `js-search`/`phpdocumentor-search`
// class. Match any of those tokens.
if (!preg_match("#(class=[\"\'][^\"\']*search|name=[\"\']search[\"\']|js-search|phpdocumentor-search)#i", $header)) {
  echo "WARN: could not locate search input inside <header> — placement check is heuristic only\n";
}
echo "OK: switcher is inside <header>\n";
'
```

Expected: `OK: switcher is inside <header>`. If you see `FAIL: switcher missing from <header>`, the insertion site in `header.html.twig` was outside the `<header>` element — re-locate it inside.

Visual check: open `build/docs/api/index.html` in a browser and confirm the two `<select>` boxes render in the top navbar bar alongside any existing search input, not as a separate stripe below the navbar. If they render below, adjust the insertion site in `header.html.twig` to be inside the flex container that holds the search input (commonly something like `<div class="phpdocumentor-search">…</div>` — sibling, not parent).

- [ ] **Step 5: Commit**

```bash
git add .phpdoc/
git commit -m "phase-10: .phpdoc/template/ override — version + language switcher in navbar"
```

---

## Task 13: `CONTRIBUTING.md` + `.markdownlint.jsonc`

**Files:**
- Create: `CONTRIBUTING.md`
- Create: `.markdownlint.jsonc`

`scripts/copy-root-docs.php` (Task 4) requires both files to exist. Without them the build fails. Their content is part of Phase 10 scope.

- [ ] **Step 1: Write `CONTRIBUTING.md`**

Create `/Users/gruven/repository/github/phpbotgram/CONTRIBUTING.md`:

```markdown
# Contributing to phpbotgram

Thank you for your interest in contributing. This project is a PHP 8.5
port of the aiogram Telegram bot framework; the design specs and
implementation plans live under [`docs/superpowers/`](docs/superpowers/).

## Opening an issue

- Search existing issues first; duplicates get closed quickly.
- Include the framework version (`composer info gruven/phpbotgram | head -3`),
  PHP version (`php -v`), and a minimal reproducer.
- For Telegram-API integration bugs, attach the raw request/response if you
  can. Strip bot tokens.

## Pull request workflow

1. Branch from `master`: `git checkout -b feature/short-name master`.
2. Make changes; keep each commit focused. Conventional Commits-style prefix
   (`feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`) appreciated.
3. Run the full local check:
   ```bash
   composer test               # PHPUnit
   composer coverage-gate      # per-module coverage floors
   composer stan               # PHPStan level 9
   composer lint               # php-cs-fixer dry-run
   composer docs-api           # narrative + API build + gates
   ```
   All five must pass before review.
4. Push the branch and open a PR. CI replicates the local checks plus
   `markdownlint-cli2` on narrative Markdown.

## Coding standards

- **PHP 8.5** features encouraged: readonly classes, asymmetric visibility,
  property hooks, enums, attributes.
- **PHPStan level 9** — no `@phpstan-ignore`, no `assert()`, no inline `@var`
  to silence type errors. Fix the underlying issue.
- **php-cs-fixer** rules in `.php-cs-fixer.dist.php` are enforced.
- **No commented-out code**, no debug-print statements in committed code.
- **Tests required** for new behaviour. Aim for the existing per-module
  coverage floors (`scripts/coverage-gate.php`).

## Documentation contributions

Narrative docs live under `docs/guide/en/`. The build:

- `composer docs-api` renders narrative + API into `build/docs/api/`.
- `npx markdownlint-cli2@0.22.1 'docs/guide/en/**/*.md'` enforces style.
- `scripts/lint-docs.php` validates `\`\`\`php` fenced blocks (auto-prepends
  `<?php\n`) and forbids inline raw HTML in narrative pages.
- Guide → API cross-links use the sentinel host
  `https://api.phpbotgram.local/<Namespace-with-dashes>-<Class>.html`,
  rewritten post-build to `classes/...`.

## Commit message convention

```
<type>: <imperative summary, lowercase, <72 chars>

Optional body paragraph(s). Wrap at ~72 cols. Explain *why*, not *what*
(the diff already shows what).

Co-Authored-By: <co-author> <email>  # if applicable
```

Common types: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`, `ci`,
`perf`, `style`.

## Reporting security issues

Email <security@example.invalid> (replace before merge). Do not file a
public issue for security-sensitive bugs.

## License

By contributing you agree your work is licensed under the project's MIT
license (see [LICENSE](LICENSE)).
```

- [ ] **Step 2: Write `.markdownlint.jsonc`**

Create `/Users/gruven/repository/github/phpbotgram/.markdownlint.jsonc`:

```jsonc
{
  // phpbotgram narrative-docs markdownlint config.
  // Pinned against markdownlint-cli2@0.22.1.
  //
  // Permissive rules to accommodate copies of CHANGELOG.md and CONTRIBUTING.md
  // which `scripts/copy-root-docs.php` materialises into docs/guide/en/.
  "default": true,

  // CHANGELOG entries can exceed 80 chars; disable line-length.
  "MD013": false,

  // Allow inline HTML in fenced code blocks (the spec forbids it in narrative
  // prose; lint-docs.php's positive regex enforces that separately).
  "MD033": false,

  // CHANGELOG.md uses `### Added` / `### Fixed` / `### Changed` under every
  // version section, which legitimately repeats those headings. siblings_only
  // permits duplicates as long as they don't share a parent heading — exactly
  // the Keep-a-Changelog shape.
  "MD024": { "siblings_only": true },

  // Allow duplicate H1 across pages (one per file is fine).
  "MD025": { "front_matter_title": "" },

  // The autoload banner script writes <!-- AUTO-GENERATED -- > comments;
  // those are fine.
  "MD041": false,

  // Lists in cookbook recipes often nest 4 spaces deep.
  "MD007": { "indent": 2 },

  // Allow empty links in stub pages during development.
  "MD042": false
}
```

- [ ] **Step 3: Verify markdownlint accepts the config**

```bash
npx markdownlint-cli2@0.22.1 --config .markdownlint.jsonc docs/guide/en/index.md 2>&1 | tail -5
```

Expected: no errors (the `index.md` from Task 12 is trivial).

- [ ] **Step 4: Re-run `copy-root-docs.php` end-to-end**

```bash
php scripts/copy-root-docs.php
ls -la docs/guide/en/changelog.md docs/guide/en/contributing.md
```

Expected: both files exist, start with the AUTO-GENERATED banner, and have mtimes matching the source files.

- [ ] **Step 5: Commit**

```bash
git add CONTRIBUTING.md .markdownlint.jsonc
git commit -m "phase-10: CONTRIBUTING.md + .markdownlint.jsonc"
```

---

## Task 14: Migrate `.github/workflows/docs.yml` to branch mode

**Files:**
- Modify: `.github/workflows/docs.yml`

Apply the field-by-field migration from the spec. Single job (`deploy` job collapses into `build`), peaceiris replaces the upload+deploy actions, permissions narrow to `contents: write`, environment block removed, concurrency renamed.

- [ ] **Step 1: Replace the workflow file**

Overwrite `/Users/gruven/repository/github/phpbotgram/.github/workflows/docs.yml`:

```yaml
name: API documentation

on:
  push:
    branches:
      - master
  workflow_dispatch:

permissions:
  contents: write

# Single shared group with both docs-release.yml; cancel-in-progress: false
# queues a tag publish behind a master push instead of cancelling it.
concurrency:
  group: pages-write
  cancel-in-progress: false

jobs:
  build:
    name: Build and publish docs
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v5

      - name: Setup PHP 8.5
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          extensions: mbstring, sodium, json
          tools: composer:v2
          coverage: none

      - name: Setup Node 20
        uses: actions/setup-node@v4
        with:
          node-version: "20"

      - name: Cache composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ runner.os }}-${{ hashFiles('composer.lock') }}

      - name: Install dependencies
        run: composer install --no-interaction --no-progress --prefer-dist

      - name: Build docs
        env:
          VERSION: dev
        run: composer docs-api

      - name: Checkout gh-pages worktree
        uses: actions/checkout@v5
        with:
          ref: gh-pages
          path: gh-pages-worktree

      - name: Update versions.json with dev entry
        run: |
          php scripts/update-versions-json.php \
            gh-pages-worktree/versions.json \
            --upsert id=dev path=en/dev/ "label=dev (master)" stable=false
          mkdir -p build/docs/root-publish
          cp gh-pages-worktree/versions.json build/docs/root-publish/versions.json

      - name: Publish en/dev
        uses: peaceiris/actions-gh-pages@v4
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_branch: gh-pages
          publish_dir: build/docs/api
          destination_dir: en/dev
          keep_files: false
          commit_message: "publish ${{ github.sha }} -> en/dev"

      - name: Publish versions.json
        uses: peaceiris/actions-gh-pages@v4
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_branch: gh-pages
          publish_dir: build/docs/root-publish
          destination_dir: ""
          keep_files: true
          commit_message: "update versions.json (dev=${{ github.sha }})"
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/docs.yml
git commit -m "phase-10: migrate docs.yml from workflow mode to branch mode"
```

---

## Task 15: Add `.github/workflows/docs-release.yml`

**Files:**
- Create: `.github/workflows/docs-release.yml`

Tag-triggered: builds the docs at the tag ref, publishes to `/en/<tag>/`, `/en/latest/`, and updates `versions.json` with `stable=auto`. Same shared concurrency group as `docs.yml`.

- [ ] **Step 1: Write the workflow**

Create `/Users/gruven/repository/github/phpbotgram/.github/workflows/docs-release.yml`:

```yaml
name: Release documentation

on:
  push:
    tags:
      - 'v*.*.*'

permissions:
  contents: write

concurrency:
  group: pages-write
  cancel-in-progress: false

jobs:
  build:
    name: Build and publish release docs
    runs-on: ubuntu-latest
    steps:
      - name: Checkout @ tag
        uses: actions/checkout@v5

      - name: Setup PHP 8.5
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.5"
          extensions: mbstring, sodium, json
          tools: composer:v2
          coverage: none

      - name: Setup Node 20
        uses: actions/setup-node@v4
        with:
          node-version: "20"

      - name: Cache composer dependencies
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ runner.os }}-${{ hashFiles('composer.lock') }}

      - name: Install dependencies
        run: composer install --no-interaction --no-progress --prefer-dist

      - name: Build docs
        env:
          VERSION: ${{ github.ref_name }}
        run: composer docs-api

      - name: Checkout gh-pages worktree
        uses: actions/checkout@v5
        with:
          ref: gh-pages
          path: gh-pages-worktree

      # Note: peaceiris/actions-gh-pages inputs below (destination_dir,
      # commit_message) still interpolate `${{ github.ref_name }}` directly.
      # That is acceptable because the workflow trigger filter restricts
      # this job to tags matching `v*.*.*` — GitHub validates the tag name
      # at push time, so the interpolated string is constrained to that
      # shape. The `run:` block below is more permissive (any shell), hence
      # the env-var indirection there.
      - name: Update versions.json
        env:
          # Pass the tag through an env-var instead of interpolating
          # `${{ github.ref_name }}` into the shell command.
          REF_NAME: ${{ github.ref_name }}
        run: |
          php scripts/update-versions-json.php \
            gh-pages-worktree/versions.json \
            --upsert "id=${REF_NAME}" \
                     "path=en/${REF_NAME}/" \
                     "label=${REF_NAME}" \
                     stable=auto
          mkdir -p build/docs/root-publish
          cp gh-pages-worktree/versions.json build/docs/root-publish/versions.json

      - name: Publish en/${{ github.ref_name }}
        uses: peaceiris/actions-gh-pages@v4
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_branch: gh-pages
          publish_dir: build/docs/api
          destination_dir: en/${{ github.ref_name }}
          keep_files: false
          commit_message: "release ${{ github.ref_name }}: en/${{ github.ref_name }}"

      - name: Publish en/latest
        uses: peaceiris/actions-gh-pages@v4
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_branch: gh-pages
          publish_dir: build/docs/api
          destination_dir: en/latest
          keep_files: false
          commit_message: "release ${{ github.ref_name }}: en/latest"

      - name: Publish versions.json
        uses: peaceiris/actions-gh-pages@v4
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          publish_branch: gh-pages
          publish_dir: build/docs/root-publish
          destination_dir: ""
          keep_files: true
          commit_message: "release ${{ github.ref_name }}: update versions.json"
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/docs-release.yml
git commit -m "phase-10: docs-release.yml — tag-driven publish"
```

---

## Task 16: Author top-level landing + tutorial section (6 pages)

**Files:**
- Create: `docs/guide/en/index.md`
- Create: `docs/guide/en/tutorial/index.md`
- Create: `docs/guide/en/tutorial/01-installation.md`
- Create: `docs/guide/en/tutorial/02-first-bot.md`
- Create: `docs/guide/en/tutorial/03-handlers-and-filters.md`
- Create: `docs/guide/en/tutorial/04-state.md`
- Create: `docs/guide/en/tutorial/05-deployment.md`

Manual-content checklist (per spec): tutorial pages contain either a runnable snippet (linted) or a `[full example](examples/X.php)` link to an existing file. Concept-page conventions apply to a tutorial only if it references API classes.

- [ ] **Step 1: Top-level landing**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/index.md`:

```markdown
# phpbotgram documentation

Welcome to the phpbotgram narrative documentation. Pick the entry that
matches what you're trying to do.

## Tutorials

Hands-on, step-by-step lessons. Start here if you've never used
phpbotgram before. Each step builds on the previous one; by the end of
the tutorial track you have a polling bot, a webhook bot, an FSM,
and a deployment recipe.

[Start the tutorial →](tutorial/index.md)

## How-to recipes

Task-oriented cookbook. Each recipe answers a single question
("How do I send a media group?", "How do I add a custom filter?")
and assumes you already know the basics.

[Browse recipes →](how-to/index.md)

## Concepts

Understanding-oriented prose. Each concept page explains *why* a
phpbotgram subsystem works the way it does — the Bot/Session split,
the dispatcher cascade, the FSM strategy, etc.

[Read the concepts →](concepts/index.md)

## API reference

Auto-generated from the source: every class, method, type, enum.

[Open the API reference →](reference/index.md)
```

- [ ] **Step 2: Tutorial landing**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/tutorial/index.md`:

```markdown
# Getting started

Five short lessons. By the end you will have a running bot, you'll
understand handlers and filters, you'll have built a stateful
conversation, and you'll have a working production deployment recipe.

1. [Installation](01-installation.md) — composer require, PHP 8.5,
   ext-sodium.
2. [Your first bot](02-first-bot.md) — `BOT_TOKEN`, `runPolling`,
   echo handler.
3. [Handlers and filters](03-handlers-and-filters.md) — `Command`,
   `F`-DSL, returning kwargs.
4. [State](04-state.md) — inline FSM (`FsmContext`) without scenes.
5. [Deployment](05-deployment.md) — nginx + systemd from `deploy/`.

Time budget: ~30 minutes end-to-end. Each lesson is self-contained
and ends with a runnable example in `examples/`.
```

- [ ] **Step 3: 01-installation.md**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/tutorial/01-installation.md`:

```markdown
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
```

- [ ] **Step 4: 02-first-bot.md**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/tutorial/02-first-bot.md`:

```markdown
# Your first bot

Build a polling echo bot in 20 lines. Replies to every message with the
same text.

## Get a token

Talk to [@BotFather](https://t.me/BotFather) on Telegram, create a new
bot, and copy the token (looks like `123456:ABCdefGHIjklMNOpqrSTUvwxYZ`).

## Write the bot

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

$token = getenv('BOT_TOKEN') ?: throw new RuntimeException('BOT_TOKEN missing');
$bot = new Bot($token);
$dispatcher = new Dispatcher();

$dispatcher->message->register(static function (Message $event): void {
    $text = $event->text ?? '';
    if ($text === '') return;
    $event->answer($text)->emit();
});

$dispatcher->runPolling(new PollingOptions(), $bot);
```

## Run it

```bash
BOT_TOKEN=123456:ABCdef… php echo_bot.php
```

Send any text to your bot in Telegram; it echoes back.

See the [full example](examples/echo_bot.php) for the version with
graceful-shutdown handling.

## What just happened

- `new Bot($token)` constructs a [`Bot`](https://api.phpbotgram.local/Gruven-PhpBotGram-Bot.html)
  with the default `AmphpSession` HTTP transport.
- `Dispatcher::runPolling()` loops `getUpdates` against Telegram, feeding
  every update through the registered handlers.
- `$event->answer(...)` is a codegen-produced shortcut that builds a
  `SendMessage` already bound to the right chat.

## Next step

[Add filters and dispatch on commands →](03-handlers-and-filters.md)
```

- [ ] **Step 5: 03-handlers-and-filters.md**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/tutorial/03-handlers-and-filters.md`:

```markdown
# Handlers and filters

A handler is a closure registered on an event observer. A filter is a
callable that votes on whether a handler should run. This lesson adds a
`/start` welcome and a `/help` listing.

## Add a Command filter

```php
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Types\Message;

$dispatcher->message->register(
    static function (Message $event): void {
        $event->answer('Welcome! Send any message and I will echo it.')->emit();
    },
    filters: [new Command('start')],
);
```

The `filters: [...]` array list controls when the handler fires.
phpbotgram accepts any [callable](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-Filter.html)
here — bare `Filter` instances (like `Command`) are wrapped in a closure
automatically.

## The F-DSL

For filters on a field of the event, the `F` constant lets you write
chain expressions:

```php
use Gruven\PhpBotGram\Filters\Filter;
use const Gruven\PhpBotGram\F;

$dispatcher->message->register(
    static function (Message $event): void {
        $event->answer('You sent a private message.')->emit();
    },
    filters: [
        Filter::all(
            new Command('start'),
            F->chat->type->equals('private')->asFilter(),
        ),
    ],
);
```

`Filter::all(...)` builds a logical AND across filters. `Filter::any(...)`
builds OR; `Filter::invertOf($f)` negates.

## Filters that inject kwargs

A filter can return an associative array; entries get merged into the
handler's named arguments. This is how `CallbackData` and `Command` pass
parsed data through.

```php
$dispatcher->message->register(
    static function (Message $event, string $user_id): void {
        $event->answer("user_id=$user_id")->emit();
    },
    filters: [
        static fn (Message $e): array => ['user_id' => (string)$e->chat->id],
    ],
);
```

The handler closure must declare a parameter with the literal name
`$user_id` (no snake↔camel translation; the framework uses strict
`array_intersect_key`).

## Next step

[Add multi-step state →](04-state.md)
```

- [ ] **Step 6: 04-state.md**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/tutorial/04-state.md`:

```markdown
# Adding state

Some flows need to remember where the user is. phpbotgram's
[`FsmContext`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContext.html)
provides per-user/per-chat key-value storage; the
[`StateFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-StateFilter.html)
gates handlers on the current state name.

This lesson builds a two-step "register" flow without using scenes
(those land in the [scene how-to](../how-to/scenes-wizard.md)).

## Inline FSM

```php
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Types\Message;

$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $state->setState('register:name');
        $event->answer('What is your name?')->emit();
    },
    filters: [new Command('register'), new StateFilter(null)],
);

$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $state->updateData(['name' => $event->text ?? '']);
        $state->setState('register:age');
        $event->answer('How old are you?')->emit();
    },
    filters: [new StateFilter('register:name')],
);

$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $data = $state->getData();
        $data['age'] = $event->text ?? '';
        $event->answer("Registered: {$data['name']}, age {$data['age']}.")->emit();
        $state->clear();
    },
    filters: [new StateFilter('register:age')],
);
```

The framework injects `FsmContext $state` automatically — the parameter
name `state` is the kwarg key the dispatcher binds. `setState(null)`
matches handlers gated on `StateFilter(null)` (i.e. "no active state").

## Next step

[Deploy to production →](05-deployment.md)
```

- [ ] **Step 7: 05-deployment.md**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/tutorial/05-deployment.md`:

```markdown
# Deployment

phpbotgram ships ready-made deployment templates under
[`deploy/`](https://github.com/Gruven/phpbotgram/tree/master/deploy):
nginx + systemd + Docker compose. Pick the shape you want.

## Long-polling bot (systemd)

Best for low-traffic bots running on a single VM.

1. Install `deploy/systemd/phpbotgram-polling.service` to
   `/etc/systemd/system/`.
2. Edit the `User`, `WorkingDirectory`, `EnvironmentFile`, `ExecStart`
   paths.
3. `systemctl daemon-reload && systemctl enable --now phpbotgram-polling`.

The unit drops capabilities, applies `SystemCallFilter`, and uses
`ProtectSystem=strict`. Read [`deploy/README.md`](https://github.com/Gruven/phpbotgram/blob/master/deploy/README.md)
for hardening notes (e.g. the `MemoryDenyWriteExecute` + PHP JIT
interaction).

## Webhook bot (nginx + amphp/http-server)

Best for higher traffic or when you want subsecond latency.

1. Run [`examples/echo_bot_webhook.php`](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot_webhook.php)
   bound to `127.0.0.1:8080` (the example default).
2. Install `deploy/nginx/phpbotgram-webhook.conf` to
   `/etc/nginx/sites-available/` and link from `sites-enabled/`.
3. Provision a TLS cert (Let's Encrypt). The provided config terminates
   TLS and proxies to the loopback.
4. Register the webhook with Telegram via `setWebhook` (the framework's
   [`Setup::register()`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-Setup.html#method_register)
   helper handles this).

The nginx config restricts inbound to Telegram's CIDR ranges. The
framework's [`IpFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Webhook-IpFilter.html)
middleware can repeat the check in defense-in-depth; wire it via
`AmphpServer::run(..., ipFilter: IpFilter::default())`.

## Docker

`deploy/docker/Dockerfile` + `deploy/docker/compose.yaml` ship a
multi-stage build producing a `php:8.5-cli-alpine` runtime with
`vendor/` + `src/` + `examples/`.

```bash
BOT_TOKEN=… docker compose -f deploy/docker/compose.yaml up --build
```

The image runs as UID 65532. For webhook mode in a container, change
`host: '127.0.0.1'` to `'0.0.0.0'` in
`examples/echo_bot_webhook.php` so `docker run -p 8080:8080` can reach
it.

## What's next

Browse the [cookbook](../how-to/index.md) for task-oriented recipes
or the [concepts pages](../concepts/index.md) for the architectural
deep dive.
```

- [ ] **Step 8: Run the gate chain locally**

```bash
VERSION=0.1.0-dev bash scripts/build-docs.sh 2>&1 | tail -15
```

Expected: every gate passes (or the cumulative output points at the first failure for fixing). If `check-docs-examples.php` complains about missing examples (it shouldn't — Phase 9 shipped them), check the `examples/` directory contains the referenced filenames.

- [ ] **Step 9: Commit**

```bash
git add docs/guide/en/index.md docs/guide/en/tutorial/
git commit -m "phase-10: top-level landing + 5 tutorial pages"
```

---

## Task 17: Author how-to section (21 pages: index + 20 recipes)

**Files:**
- Create: `docs/guide/en/how-to/index.md`
- Create: `docs/guide/en/how-to/<one file per recipe>` × 20

> **Execution order:** complete **Task 18** (concepts) **before** Task 17 (how-to). Several recipe pages in this task cross-link into `../concepts/<topic>.md`; running the gate chain between batches will emit `Document with name 'X' not found` warnings (which `check-docs-build-log.php` treats as failures) for any concept page that doesn't yet exist. Authoring concepts first inverts that dependency. The task numbering reflects Diataxis ordering, not execution order — physically execute 18, then 17.

Each recipe page follows the structure: **When to use this** → solution code → **Pitfalls**. Per spec's manual-review checklist.

- [ ] **Step 1: Write the how-to landing**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/how-to/index.md`:

```markdown
# How-to recipes

Task-oriented cookbook. Each recipe starts with "When to use this",
shows working code, and ends with "Pitfalls". Skim by intent group.

## Routing and filters

- [Add a custom filter](custom-filter.md)
- [Pass kwargs from a filter to the handler](dependency-injection.md)

## Conversational flow

- [Build a wizard with scenes](scenes-wizard.md)
- [Handle deep-link `/start` payloads](deep-linking.md)
- [Track chat-member transitions](chat-member-updated.md)

## Storage and state

- [Use Redis or MongoDB for FSM storage](redis-mongo-fsm.md)
- [Plug in a custom storage backend](custom-storage.md)

## Media and content

- [Upload and download files](file-upload-download.md)
- [Send a media group (album)](media-group.md)
- [Show "typing…" while a slow handler runs](chat-action-typing.md)

## Payments and Web Apps

- [Sell something via Telegram Stars](telegram-stars-payment.md)
- [Validate Web App initData](web-app-data.md)

## Deployment and operations

- [Serve webhooks without amphp/http-server](webhook-without-amphp-server.md)
- [Run multiple bots from one process](multi-bot.md)
- [Rate-limit outgoing API calls](rate-limiting.md)
- [Acknowledge callback queries cleanly](callback-answer.md)
- [Run code outside the dispatcher](background-tasks.md)

## Quality

- [Handle errors globally](error-handling.md)
- [Test bots with the MockedSession](testing-bots.md)
- [Internationalise message payloads](i18n-payloads.md)
```

- [ ] **Step 2: Write each how-to recipe**

For each filename in the index above, create the file under
`docs/guide/en/how-to/` with the following template (substitute the
recipe's content for each placeholder block):

```markdown
# <Recipe title>

## When to use this

<2-3 sentences describing the user need this recipe addresses.>

## Solution

<Working code block (linted via `php -l`), followed by a brief explanation
of the key API calls (with sentinel-URL links into the API ref where
appropriate).>

## Pitfalls

- <bullet 1: gotcha #1>
- <bullet 2: gotcha #2>
- <bullet 3: cross-reference to a deeper concepts page>
```

**Per-recipe content contract** — each file must satisfy ALL of:

1. Single H1 with the recipe title.
2. Exactly three H2s: `When to use this`, `Solution`, `Pitfalls` (in that
   order). The manual-review checklist in the spec rejects any deviation.
3. At least one fenced `php` block with a working code snippet drawn from
   the source-of-truth file. `scripts/lint-docs.php` will fail the build
   if any `php` fence emits a parse error.
4. At least one sentinel-URL hyperlink
   (`https://api.phpbotgram.local/Gruven-PhpBotGram-...`) into the API
   reference. Use the longest-symbol-name form
   (`Gruven-PhpBotGram-Client-Session-BaseSession.html#method_request`)
   rather than a short alias.
5. The Pitfalls section has 2–4 bullets — empty bullets fail review,
   and more than 4 should be split into a Concepts page.
6. Total length: 30–80 lines (excluding the H1 line and trailing newline).

**Worked example — `error-handling.md`** (use this as the formal
template for the other 19 recipes; one filled-in example beats 20
skeletons):

```markdown
# Handle errors globally

## When to use this

Bots that run unattended need to log uncaught exceptions instead of
losing them to the polling loop. Register an error observer once on
the dispatcher and every handler — across every router — inherits it.

## Solution

```php
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Types\ErrorEvent;

$dispatcher = new Dispatcher();
$dispatcher->errors->register(static function (ErrorEvent $event): void {
    error_log(sprintf(
        '[%s] uncaught: %s — %s',
        date('c'),
        get_class($event->exception),
        $event->exception->getMessage(),
    ));
});
```

The `errors` observer fires whenever a handler raises an uncaught
exception. The
[`ErrorEvent`](https://api.phpbotgram.local/Gruven-PhpBotGram-Types-ErrorEvent.html)
carries both the original update and the raised throwable. Returning
without re-raising swallows the exception; rethrowing escalates to the
polling loop's exit path.

## Pitfalls

- The error observer runs in the same fiber as the failing handler. If
  it raises again, the dispatcher logs and continues — but the update
  is lost. Keep error handlers free of network I/O.
- Errors raised inside `outerMiddleware` *before* dispatch reach
  `Dispatcher::errors` only if the middleware re-enters the observer
  loop. See [Middlewares](../concepts/middlewares.md) for the call
  order.
- `TelegramRetryAfter` is *not* delivered to `errors`; the polling
  loop's backoff (`PollingOptions::$backoff`) handles it directly.
```

Each remaining recipe page is 30–80 lines and follows the same shape.
Use the existing `examples/*.php` files as content sources (column 2 of
the table below). The manual-content checklist in
`docs/superpowers/specs/2026-05-15-narrative-docs-design.md`
§"Manual content review checklist" enforces the structure during review;
batch authoring is acceptable provided every file satisfies the
six-point content contract above.

**Specific recipe-to-source-of-truth mapping:**

| File | Source of truth |
| --- | --- |
| `custom-filter.md` | `examples/own_filter.php` |
| `dependency-injection.md` | `examples/context_addition_from_filter.php` |
| `scenes-wizard.md` | `examples/scene.php` + `examples/quiz_scene.php` |
| `deep-linking.md` | `src/Utils/DeepLinking.php` |
| `chat-member-updated.md` | `src/Filters/ChatMemberUpdatedFilter.php` |
| `redis-mongo-fsm.md` | `src/Fsm/Storage/{Redis,Mongo}Storage.php` |
| `custom-storage.md` | `src/Fsm/Storage/BaseStorage.php` |
| `file-upload-download.md` | `src/Client/BotShortcuts.php::downloadFile` |
| `media-group.md` | `src/Utils/MediaGroup/MediaGroupBuilder.php` |
| `chat-action-typing.md` | `src/Utils/ChatAction/ChatActionSender.php` |
| `telegram-stars-payment.md` | `examples/stars_invoice.php` |
| `web-app-data.md` | `src/Utils/WebApp/WebAppSignature.php` |
| `webhook-without-amphp-server.md` | `src/Webhook/SimpleRequestHandler.php` |
| `multi-bot.md` | `examples/multibot.php` |
| `rate-limiting.md` | `src/Utils/Backoff.php` |
| `callback-answer.md` | `src/Utils/CallbackAnswer/CallbackAnswerMiddleware.php` |
| `background-tasks.md` | `src/Utils/ChatAction/ChatActionSender.php::raceDelay` |
| `error-handling.md` | `examples/error_handling.php` |
| `testing-bots.md` | `tests/Support/MockedSession.php` + `tests/Support/RecordingDispatcher.php` (or analogous) |
| `i18n-payloads.md` | mbstring + ext-intl considerations |

- [ ] **Step 3: Run the gates after each batch**

After authoring a batch of recipes (e.g. 5 at a time), run:

```bash
VERSION=0.1.0-dev bash scripts/build-docs.sh 2>&1 | tail -20
```

Fix any gate failures before continuing.

- [ ] **Step 4: Commit all how-to pages**

```bash
git add docs/guide/en/how-to/
git commit -m "phase-10: 20 cookbook recipes + how-to landing"
```

---

## Task 18: Author concepts section (17 pages: index + 16 concepts)

**Files:**
- Create: `docs/guide/en/concepts/index.md`
- Create: `docs/guide/en/concepts/<one file per concept>` × 16

> **Execution order:** run this task **before** Task 17. The recipe pages authored in Task 17 link into `../concepts/<topic>.md`; without those targets, the gate chain between batches fails on unresolved cross-page references.

Each concepts page follows: introduction → "How it works" → "Trade-offs" → cross-reference into the API. Per spec's checklist, every concept page must contain at least one sentinel hyperlink to the API.

- [ ] **Step 1: Write the concepts landing**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/concepts/index.md`:

```markdown
# Concepts

Understanding-oriented prose. Each page explains *why* a phpbotgram
subsystem works the way it does, not just how to use it. Read these
when you want to know the design intent.

## Core runtime

- [Bot and Session](bot-and-session.md)
- [Dispatcher](dispatcher.md)
- [Routers](routers.md)
- [Middlewares](middlewares.md)
- [Flags](flags.md)

## Filtering and routing

- [Filters](filters.md)
- [F-DSL](f-dsl.md)
- [CallbackData](callback-data.md)

## State

- [FSM](fsm.md)
- [Scenes](scenes.md)

## I/O

- [Webhook](webhook.md)
- [Keyboards](keyboards.md)
- [Text decoration](text-decoration.md)

## Errors and serialization

- [Error model](error-model.md)
- [Serialization](serialization.md)

## Project decisions

- [Architecture decisions](architecture-decisions.md)
```

- [ ] **Step 2: Author each concept page**

For each filename in the index above, write a 100–200 line page using
the structure:

```markdown
# <Concept title>

<Lead paragraph: one sentence summarising what this subsystem is.>

## How it works

<2-4 paragraphs explaining the internal design with at least one
sentinel-URL link to the relevant API class. Example:>

The [`Bot`](https://api.phpbotgram.local/Gruven-PhpBotGram-Bot.html)
class delegates every API call through its
[`BaseSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-BaseSession.html).
The session owns the HTTP transport, retry logic, and middleware
pipeline.

## Trade-offs

<2-3 paragraphs explaining the design choices and what the framework
gives up to make them. Example: "phpbotgram chose fiber-based amphp
over native PHP threads to keep the dispatcher synchronous from the
caller's perspective. The trade-off is that handler code can't
genuinely run in parallel within a single process; bots that need
that should run multiple processes behind nginx.">

## See also

- [Dispatcher](dispatcher.md)
- [API reference: Bot](https://api.phpbotgram.local/Gruven-PhpBotGram-Bot.html)
```

**Per-concept content contract** — each file must satisfy ALL of:

1. Single H1 with the concept title.
2. Lead paragraph immediately after the H1 (one sentence, no fence,
   no bullet list).
3. Exactly three H2s: `How it works`, `Trade-offs`, `See also` (in that
   order).
4. At least two sentinel-URL hyperlinks into the API reference; at
   least one in `How it works` (per spec's checklist) and one in
   `See also`.
5. The `See also` section has 2–5 bullets, each either a relative
   link to another concept page (`../how-to/...md` or `dispatcher.md`)
   or a sentinel-URL API link.
6. Total length: 100–200 lines.
7. No fenced `php-fragment` blocks unless the pilot pass (Task 1 Step
   4b) confirmed they render distinguishably; otherwise use plain `php`
   fences.

**Worked example — `dispatcher.md`** (use this as the formal template
for the other 15 concept pages; one filled-in example beats 16
skeletons):

```markdown
# Dispatcher

The dispatcher is the heart of a phpbotgram bot. It owns the polling
loop, the update-type observer map, and the router cascade.

## How it works

[`Dispatcher`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
extends `Router`, so the same registration API (`->message->register`,
`->callbackQuery->register`, …) is available at the top level. When
you call `runPolling`, the dispatcher opens an
[`AmphpSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-AmphpSession.html)
on the bot, then enters a fiber that calls `getUpdates` in a loop. Each
returned update is fed through `feedUpdate`, which walks the
25-observer map and resolves the correct observer
(`message`, `callbackQuery`, `chatMember`, etc.) by attribute presence.

For each observer the dispatcher applies the global filter chain,
then per-handler filters, then enters the middleware stack
(`outerMiddleware` → handler → `innerMiddleware`). The handler's
return value is ignored; side effects (`$event->answer(...)`) are
the contract.

Graceful shutdown: the dispatcher registers `SIGINT`/`SIGTERM`
handlers on `runPolling`. On signal it stops fetching new updates,
lets in-flight handlers finish, and exits with code 0. This means
production bots running under systemd can be restarted without
losing updates already delivered.

## Trade-offs

The dispatcher is the *only* update-fetching entry point in the
framework. Webhook mode also goes through `feedUpdate`, just from
a different fiber. The duplication aiogram has (`Dispatcher` vs.
`Bot.start_webhook`) is collapsed; this trades flexibility (you
can't have a separate "command bot" object) for a single source of
truth.

`runPolling` is blocking. If you need to mix the bot with other
amphp services, use the lower-level `startPolling` and join its
future yourself. See [Webhook](webhook.md) for the long-running
mode.

## See also

- [Routers](routers.md)
- [Middlewares](middlewares.md)
- [API reference: Dispatcher](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Dispatcher.html)
- [API reference: PollingOptions](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-PollingOptions.html)
```

Each remaining concept page is 100–200 lines and follows the same
shape. Use the source-class column of the table below as the read-the-
code anchor. Batch authoring is acceptable provided every file
satisfies the seven-point content contract above.

**Source-of-truth mapping:**

| File | Source class(es) |
| --- | --- |
| `bot-and-session.md` | `src/Bot.php`, `src/Client/Session/BaseSession.php` |
| `dispatcher.md` | `src/Dispatcher/Dispatcher.php` |
| `routers.md` | `src/Dispatcher/Router.php` |
| `middlewares.md` | `src/Dispatcher/Middlewares/BaseMiddleware.php` |
| `flags.md` | `src/Dispatcher/Flags/*` |
| `filters.md` | `src/Filters/Filter.php` |
| `f-dsl.md` | `src/F.php`, `src/Utils/MagicFilter/MagicFilter.php` |
| `callback-data.md` | `src/Filters/CallbackData.php` |
| `fsm.md` | `src/Fsm/FsmContext.php`, `src/Fsm/FsmStrategy.php` |
| `scenes.md` | `src/Fsm/Scene/*` |
| `webhook.md` | `src/Webhook/{BaseRequestHandler,IpFilter,Setup}.php` |
| `keyboards.md` | `src/Utils/Keyboard/*Builder.php` |
| `text-decoration.md` | `src/Utils/Text/{HtmlDecoration,MarkdownDecoration}.php` |
| `error-model.md` | `src/Exceptions/Telegram*Exception.php` |
| `serialization.md` | `src/Client/Serializer.php` + `BaseSession::prepareValue/checkResponse` |
| `architecture-decisions.md` | divergences from aiogram (no async/await, explicit scenes, etc.) |

- [ ] **Step 3: Run the gate chain after each batch**

```bash
VERSION=0.1.0-dev bash scripts/build-docs.sh 2>&1 | tail -20
```

Fix any gate failures (most commonly: sentinel target with a non-existent
class — check the filename pattern in `build/docs/api/classes/`).

- [ ] **Step 4: Commit concepts pages**

```bash
git add docs/guide/en/concepts/
git commit -m "phase-10: 16 concept pages + concepts landing"
```

---

### Page-count sanity check (after Tasks 16-18)

Before moving on, verify file counts match the spec:

```bash
test "$(find docs/guide/en/tutorial -name '*.md' | wc -l | tr -d ' ')" -eq 6  # 5 numbered + index
test "$(find docs/guide/en/how-to -name '*.md' | wc -l | tr -d ' ')" -eq 21  # 20 recipes + index
test "$(find docs/guide/en/concepts -name '*.md' | wc -l | tr -d ' ')" -eq 17  # 16 concepts + index
```

Any failure means a page is missing from the relevant authoring task — locate the gap by `diff`-ing the spec's content-tree list against `ls docs/guide/en/<section>/`.

---

## Task 19: Author reference stub + migration placeholder + shared assets

**Files:**
- Create: `docs/guide/en/reference/index.md`
- Create: `docs/guide/en/migration/.gitkeep`
- Create: `docs/guide/shared/assets/diagrams/.gitkeep`
- Create: `docs/guide/shared/assets/code-snippets/.gitkeep`

- [ ] **Step 1: Reference stub**

Create `/Users/gruven/repository/github/phpbotgram/docs/guide/en/reference/index.md`:

```markdown
# API reference

The full API reference is auto-generated from the source code and lives
alongside this narrative in the rendered site:

- Class index: see the navbar's "API" section, or jump to a specific
  class via the sentinel URL pattern
  `classes/Gruven-PhpBotGram-<Namespace>-<Class>.html`.
- Source files: `files/`
- Namespaces: `namespaces/`

Regenerate locally with `composer docs-api`.
```

- [ ] **Step 2: Placeholder files**

```bash
mkdir -p docs/guide/en/migration docs/guide/shared/assets/diagrams docs/guide/shared/assets/code-snippets
touch docs/guide/en/migration/.gitkeep \
      docs/guide/shared/assets/diagrams/.gitkeep \
      docs/guide/shared/assets/code-snippets/.gitkeep
```

- [ ] **Step 3: Final gate check**

```bash
VERSION=0.1.0-dev bash scripts/build-docs.sh 2>&1 | tail -25
```

Expected: every gate passes. The rendered site at `build/docs/api/`
should contain narrative + API + the navbar switcher.

- [ ] **Step 4: Commit**

```bash
git add docs/guide/en/reference/ docs/guide/en/migration/ docs/guide/shared/
git commit -m "phase-10: reference stub + migration placeholder + shared assets"
```

---

## Task 20: README + CHANGELOG polish

**Files:**
- Modify: `README.md` (add docs-site URL)
- Modify: `CHANGELOG.md` (Phase 10 entry)

- [ ] **Step 1: Add docs-site reference to README**

Open `/Users/gruven/repository/github/phpbotgram/README.md`. In the
"Links" section, find the bullet that currently says
`https://gruven.github.io/phpbotgram/` and add a sibling pointing at
the narrative:

```markdown
- **Narrative documentation:** <https://gruven.github.io/phpbotgram/en/dev/guide/>
  (tutorial, cookbook, concepts).
```

- [ ] **Step 2: Add Phase 10 CHANGELOG entry**

Open `/Users/gruven/repository/github/phpbotgram/CHANGELOG.md`. Above
the existing `## [0.1.0] — Initial release` section, add:

```markdown
## [Unreleased]

### Added — Phase 10 narrative documentation

- Diataxis-structured narrative site under `docs/guide/en/` (46 committed pages + 2 build-time copies of CHANGELOG / CONTRIBUTING → 48 rendered)
  (5 tutorial, 20 how-to, 16 concept pages, plus indexes, reference
  stub, copy of CHANGELOG/CONTRIBUTING).
- `phpdoc.dist.xml.tpl` template (envsubst → `phpdoc.dist.xml`)
  rendering narrative + API into a single site under
  `build/docs/api/`.
- `.phpdoc/template/` Twig override injecting a navbar
  language+version switcher driven by `versions.json` and
  `languages.json` served from the gh-pages branch root.
- Seven post-build CI gates in `scripts/build-docs.sh`:
  - `check-docs-build-log.php` greps phpdoc stderr for unresolved
    refs / orphan docs / missing-title warnings.
  - `check-docs-links.php` verifies every sentinel-URL
    (`https://api.phpbotgram.local/...`) points at a real API page.
  - `rewrite-api-links.php` HTML-aware DOM rewrite of sentinel URLs
    to `classes/...`, with a post-rewrite assertion.
  - `check-internal-links.php` walks rendered HTML for non-sentinel
    internal links and validates them against `<base href>`.
  - `lint-docs.php` runs `php -l` on every fenced ```php block and
    bans inline raw HTML in narrative prose.
  - `check-docs-examples.php` verifies every `examples/X.php` link
    resolves to an existing file.
  - `markdownlint-cli2@0.22.1` for prose style.
- `.github/workflows/docs.yml` migrated from Pages "workflow mode" to
  "branch mode" via `peaceiris/actions-gh-pages@v4`; publishes
  master pushes to `/en/dev/`.
- New `.github/workflows/docs-release.yml` publishes tag pushes to
  `/en/<tag>/` + `/en/latest/` + updates `versions.json`.
- `update-versions-json.php` atomic CLI with `stable=auto`
  semver-aware backport handling.
- `copy-root-docs.php` mirrors project-root `CHANGELOG.md` and
  `CONTRIBUTING.md` into `docs/guide/en/` pre-build (gitignored
  copies with AUTO-GENERATED banner).
- `CONTRIBUTING.md` at project root.
- `.markdownlint.jsonc` config compatible with the copied
  CHANGELOG/CONTRIBUTING.
- `composer.json` constraint for `phpdocumentor/shim` tightened from
  `^3` to `~3.10.0`.
- `composer docs-api` and `make docs-api` both delegate to
  `bash scripts/build-docs.sh`.

```

- [ ] **Step 3: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "phase-10: README link + CHANGELOG entry"
```

---

## Task 21: One-time manual gh-pages bootstrap + Pages UI flip

**This task is NOT executed by automation.** It is a manual setup that
the implementer (with repo admin rights) performs once, before merging
the Phase 10 PR. The plan documents the exact sequence so a future
phase reviewer can audit it.

**Files referenced:** `docs/superpowers/notes/2026-05-15-phase-10-deploy-runbook.md`
(written below — this IS a checked-in file).

- [ ] **Step 1: Write the runbook file**

Create `/Users/gruven/repository/github/phpbotgram/docs/superpowers/notes/2026-05-15-phase-10-deploy-runbook.md`:

```markdown
# Phase 10 deploy runbook (one-time setup)

Run **before** merging the Phase 10 PR. Requires:
- repo admin rights (Pages settings).
- git 2.42+ (`git worktree add --orphan`).

## 1. Bootstrap the orphan gh-pages branch

```bash
# Inside the main repo working tree (master checked out, unchanged):
git worktree add --orphan -B gh-pages /tmp/phpbotgram-gh-pages

cd /tmp/phpbotgram-gh-pages

cat > index.html <<'HTML'
<!doctype html><html><head><meta charset="utf-8"><title>phpbotgram</title></head>
<body><script>
fetch('versions.json', {cache: 'no-store'}).then(r => r.json()).then(data => {
  const stable = (data.versions || []).find(v => v.stable);
  const pick = stable || (data.versions || []).find(v => v.id === 'dev')
                      || (data.versions || [])[0];
  if (pick) location.replace(pick.path);
  else document.body.textContent = 'No versions published yet.';
}).catch(() => document.body.textContent = 'Failed to load versions.json.');
</script><noscript>This site requires JavaScript to pick a docs version.</noscript></body></html>
HTML

cat > versions.json <<'JSON'
{"versions": []}
JSON

cat > languages.json <<'JSON'
{"languages": [{"id": "en", "label": "English"}]}
JSON

# .nojekyll disables GitHub Pages' default Jekyll preprocessing. Without
# this file, Jekyll silently drops anything that starts with `_` —
# including phpDocumentor's `_static/` asset directories — and the
# deployed site loses its CSS and JS. peaceiris/actions-gh-pages writes
# `.nojekyll` automatically on each publish (its default behaviour with
# `disable_nojekyll: false`), but bootstrapping it here removes the
# window between the initial branch push and the first peaceiris run
# where Jekyll could still try to process the placeholders.
touch .nojekyll

git add index.html versions.json languages.json .nojekyll
git commit -m "Bootstrap gh-pages with JS redirect + empty inventories + .nojekyll"
git push -u origin gh-pages

cd /Users/gruven/repository/github/phpbotgram
git worktree remove /tmp/phpbotgram-gh-pages
```

For git < 2.42, use the tempdir-clone fallback from the spec
§"One-time setup" → fallback recipe.

## 2. Merge the Phase 10 PR

The first `master` push post-merge triggers `docs.yml`, which appends a
`dev` entry to `versions.json`.

## 3. Verify gh-pages contents

```bash
git fetch origin gh-pages
git ls-tree origin/gh-pages -r --name-only | head -10
```

Expected output includes `en/dev/index.html` and `versions.json`.

## 4. Flip Pages source via the GitHub UI

Repo Settings → Pages → "Source" → "Deploy from a branch":
- Branch: `gh-pages`
- Folder: `/`

Save. Brief downtime (seconds-to-minutes) during the flip is normal.

## 5. Smoke test

```bash
# Root: HTML with inline JS redirect (200 OK, body contains location.replace).
curl -s https://gruven.github.io/phpbotgram/ | grep -F "location.replace"
# Version page: 200 OK (direct serve, no redirect).
curl -sI https://gruven.github.io/phpbotgram/en/dev/index.html | head -1
```

Expected: the first command's output contains `location.replace(...)`
inside the `<script>` block — open the URL in a browser to see the
client-side redirect to `/en/dev/`. The second command's first
header is `HTTP/2 200`.
```

- [ ] **Step 2: Commit the runbook**

```bash
git add docs/superpowers/notes/2026-05-15-phase-10-deploy-runbook.md
git commit -m "phase-10: deploy runbook (manual one-time setup)"
```

---

## Task 22: Add `docs-api` test target + master plan update

**Files:**
- Modify: `docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md` — mark Phase 10 tasks complete.

- [ ] **Step 1: Update the master implementation plan**

Open `/Users/gruven/repository/github/phpbotgram/docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md`,
find the `## Phase 10` section (currently empty placeholder — verify),
and add a one-line entry referencing this plan:

```markdown
## Phase 10 — Narrative documentation

See `docs/superpowers/plans/2026-05-15-phpbotgram-narrative-docs.md`.

Status: implementation in progress / complete (update when shipped).
```

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/plans/2026-05-12-phpbotgram-implementation.md
git commit -m "phase-10: link master plan to narrative-docs sub-plan"
```

---

## Task 23: Phase 10 acceptance gate

- [ ] **Step 1: Run the full local pipeline**

```bash
composer test
composer coverage-gate
composer stan
composer lint
VERSION=0.1.0-dev bash scripts/build-docs.sh
```

Expected: every command exits 0. Inspect `build/docs/api/index.html`
in a browser; confirm the navbar shows the language + version
switchers (currently `[en]` and `[0.1.0-dev]` only).

- [ ] **Step 2: Push the branch**

```bash
git push -u origin feat/phase-10-narrative-docs
```

- [ ] **Step 3: Open a PR**

Title: `phase-10: narrative documentation`

PR body (template):

```markdown
## Summary

- 46 committed narrative pages under `docs/guide/en/` (1 top-level
  + 6 tutorial + 21 how-to + 17 concept + 1 reference stub) plus 2
  build-time copies (CHANGELOG, CONTRIBUTING) → 48 rendered pages.
- `phpdoc.dist.xml.tpl` + `.phpdoc/template/` override producing a
  single phpDocumentor site with narrative + API + a navbar
  language/version switcher.
- `scripts/build-docs.sh` runs phpdoc + 7 doc-quality gates.
- `docs.yml` migrated from Pages workflow mode to branch mode via
  `peaceiris/actions-gh-pages@v4`; new `docs-release.yml` handles
  tag pushes.

## Pre-merge runbook (manual, one-time)

See `docs/superpowers/notes/2026-05-15-phase-10-deploy-runbook.md`:

1. Bootstrap orphan `gh-pages` branch with root index/versions/languages.
2. Merge this PR.
3. Verify gh-pages contains `en/dev/index.html`.
4. Flip Pages source to "Deploy from branch: gh-pages /" in repo Settings.
5. Smoke-test https://gruven.github.io/phpbotgram/ redirects to `/en/dev/`.

## Test plan

- [ ] CI green on the merge commit (7 doc-quality gates).
- [ ] After merge, master workflow run publishes `en/dev/`.
- [ ] gh-pages root URL redirects to `/en/dev/`.
- [ ] Navbar switcher shows `[en]` + `[dev]` and is interactive.
- [ ] Sentinel-URL cross-link from a concept page lands on the right
      API page.
```

- [ ] **Step 4: After merge, complete the runbook**

Walk steps 1-5 of `docs/superpowers/notes/2026-05-15-phase-10-deploy-runbook.md`.
Smoke-test the deployed site.

- [ ] **Step 5: Tag phase-10-complete**

```bash
git tag phase-10-complete master
git push origin phase-10-complete
```

---

## Self-review checklist (run after completing the plan)

- [ ] Every spec section maps to at least one task.
  - Goals 1-6 → Tasks 16-19 + 14-15 + 5-10 + 12 → ✓
  - Build pipeline (envsubst, phpdoc, gates) → Tasks 2, 3, 5-10.
  - Cross-references (sentinel + rewrite) → Tasks 7, 8.
  - CI gate strategy → Tasks 1, 3, 5.
  - Code examples (hybrid) → Tasks 6, 10.
  - Versioning + deploy → Tasks 11, 14, 15, 21.
  - Switcher template override → Task 12.
  - i18n foundation → Task 19 (shared/), Task 16+17+18 (docs/guide/en/).
  - Content tree → Tasks 16-19.
  - Components table → cross-checked above.
  - Definition of done → Task 23.
- [ ] No placeholder language ("TBD", "implement later", etc.). Search: `grep -nE 'TBD|TODO|FIXME|implement later' docs/superpowers/plans/2026-05-15-phpbotgram-narrative-docs.md` returns nothing.
- [ ] Type/name consistency: `scripts/build-docs.sh` is called by `composer docs-api` script (Task 3) and `Makefile` target (Task 3) — both reference the same path. All 8 PHP scripts (`copy-root-docs`, `lint-docs`, `check-docs-build-log`, `check-docs-links`, `rewrite-api-links`, `check-internal-links`, `check-docs-examples`, `update-versions-json`) have matching test classes under `tests/Scripts/`. The bash wrapper `build-docs.sh` is covered indirectly by the gate chain it invokes. Both workflows reference the same gh-pages branch and shared concurrency group.
