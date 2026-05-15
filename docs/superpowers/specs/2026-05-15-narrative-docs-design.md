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
   both the narrative and the API reference into the same output tree.
4. **Per-version site** — `/en/dev/`, `/en/v0.1.0/`, `/en/latest/`, all
   live concurrently on `gh-pages` with a navbar switcher.
5. **i18n-foundation, not i18n-now** — the file layout slots a future
   `ru/` mirror in without restructuring; adding RU requires the
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
  phpDocumentor v3.10 phar into `vendor/bin/phpdoc`. Phase 10 narrows
  this constraint to `phpdocumentor/shim: ~3.10.0` (a composer-level
  guard) so a future shim major that bumps the phar doesn't silently
  break `<source dsn=".">` resolution, the navbar template overrides,
  or the Markdown-link survival behaviour described below (verified
  observed-working against phpDocumentor 3.10.0 by an empirical build
  with a `<guide>` block).
- `phpdoc.dist.xml` rendering API docs from `src/` into
  `build/docs/api/`.
- `composer docs-api` script + `make docs-api` Makefile target.
- `.github/workflows/docs.yml` publishing `build/docs/api/` to GitHub
  Pages via `actions/upload-pages-artifact@v3` +
  `actions/deploy-pages@v4` (Pages "workflow mode") with `permissions:
  contents: read, pages: write, id-token: write`, environment
  `github-pages`, and `concurrency: { group: pages,
  cancel-in-progress: true }`.

Phase 10 explicitly **migrates** the Phase 9 workflow from Pages
"workflow mode" to **Pages branch mode** so multiple versions can
coexist on a single `gh-pages` branch (see §Versioning + deploy).

### Build pipeline (single tool, two content roots)

Phase 10 extends `phpdoc.dist.xml` with a `<guide>` block so the same
phpDocumentor phar renders narrative Markdown alongside the API
reference. Because the `<version number="…">` value must change
between dev and tag builds, the file becomes a **template**
(`phpdoc.dist.xml.tpl`, committed); the rendered `phpdoc.dist.xml`
is gitignored.

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
    <ignore-tags>
      <!-- Preserved from Phase 9: codegen-output classes carry
           @generated; suppress to keep API docs readable. -->
      <ignore-tag>generated</ignore-tag>
    </ignore-tags>
  </version>
  <!-- Template overrides live at .phpdoc/template/ (next to this XML),
       auto-discovered by phpDocumentor's
       ProvideTemplateOverridePathMiddleware. <template> declarations
       belong at THIS top level per the v3 XSD (versionType is a
       sequence of folder?, api*, guide* — no template child). -->
</phpdocumentor>
```

Notes verified against the v3.10.0 phar (`/Users/gruven/repository/github/phpbotgram/vendor/bin/phpdoc`):

- `<template>` is a sibling of `<version>`, not a child. The XSD at
  `phar://.../data/xsd/phpdoc.xsd` defines `versionType` as a sequence
  of `folder?`, `api*`, `guide*` only.
- phpDocumentor auto-loads template overrides from
  `.phpdoc/template/` resolved relative to the **config file's
  directory** (`ProvideTemplateOverridePathMiddleware::PATH_TO_TEMPLATE_OVERRIDES`).
  No XML element wires this up; that path is the only auto-discovered
  location. Phase 10 puts overrides at `.phpdoc/template/` next to
  `phpdoc.dist.xml.tpl` (project root).
- `<source dsn=".">` matches the Phase 9 working file exactly; verified
  buildable.

`${VERSION}` is substituted via the **allow-listed** form
`envsubst '${VERSION}' < phpdoc.dist.xml.tpl > phpdoc.dist.xml`
immediately before each phpdoc invocation. The single-quoted argument
restricts substitution to that one variable; without it, `envsubst`
expands every `${VAR}` in the input — including things like `$HOME`,
`$PATH`, or `$GITHUB_REF_NAME` that happen to appear in the runner
environment, which would silently corrupt the file.

`envsubst` is part of GNU `gettext`, preinstalled on `ubuntu-latest`
runners. On macOS, Homebrew installs gettext keg-only — the build
wrapper script falls back to `/usr/local/opt/gettext/bin/envsubst`
or `/opt/homebrew/opt/gettext/bin/envsubst` if the default `envsubst`
isn't on `$PATH`.

**Local prerequisites for `composer docs-api`** (CI installs them
explicitly; document for contributors):

- PHP 8.5 (already a project hard requirement).
- `gettext` (`envsubst`). Ubuntu: preinstalled. macOS: `brew install gettext`.
- Node 20+ for `npx markdownlint-cli2`. macOS: `brew install node`.
  Ubuntu: `nvm install 20` or system Node. Add a note in
  `CONTRIBUTING.md` so first-time contributors see the requirement.

`VERSION` values:

- Locally: `0.1.0-dev` (build-wrapper default).
- CI (master push): `dev`.
- CI (tag push `vX.Y.Z`): `vX.Y.Z`.

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

`<base href>` injection (phpDocumentor behaviour, verified
empirically): every rendered page gets a `<base href>` whose value is
depth-adaptive — the relative path from the page back to
`build/docs/api/`. Examples observed on a real build:

| Rendered page | `<base href>` |
| --- | --- |
| `build/docs/api/index.html` | `./` |
| `build/docs/api/classes/Foo.html` | `../` |
| `build/docs/api/guide/index.html` | `../` |
| `build/docs/api/guide/concepts/dispatcher.html` | `../../` |

This means: a relative URL written in any rendered page resolves
against the output root regardless of the page's depth. Authors and
scripts only need one rule: **relative paths are relative to
`build/docs/api/`**. The check-docs-links script reads `<base href>`
per-page; it does not assume a constant.

### Cross-references: three link kinds, one rendering pipeline

phpDocumentor v3.10's Markdown plugin routes every CommonMark `Link`
node through a reference-resolver chain
(`phpdocumentor/guides/src/ReferenceResolvers/`). Three kinds of
links survive rendering; everything else is logged as `Reference X
could not be resolved` and the `<a>` is dropped from the AST.
Empirically verified by feeding a guide fixture through phpdoc 3.10:

| Markdown source | What survives |
| --- | --- |
| `[A](other-page.md)` (in-narrative `.md` link, **whole-page**) | Routed through `DocReferenceResolver`; rewritten to the rendered HTML path of the target doc. |
| `[A2](other-page.md#section)` (`.md` link with fragment) | **DROPPED.** `DocReferenceResolver` looks up the whole `other-page.md#section` string as a single doc name and fails to match. Fragment is not peeled off. CI gate fires `could not be resolved`. |
| `[B](classes/Foo.html)` (relative `.html`) | Falls to fallback resolver; `<a>` stripped. CI gate fires `could not be resolved`. |
| `[C](https://example.com/x)` (external HTTPS) | `ExternalReferenceResolver` allow-lists `http/https/mailto`; `<a>` preserved verbatim. |
| `<a href="classes/Foo.html">D</a>` (inline raw HTML) | Symfony `HtmlSanitizer` discards the relative `href` attribute silently — **no warning logged**, `<a>` collapses to bare text. Caught only by a positive regex check inside `scripts/lint-docs.php` (see below); the CI gate's grep patterns do NOT catch this. |
| `[E](#anchor)` (in-page anchor) | Treated as same-page fragment; `<a>` preserved verbatim. |

**Authoring rules:**

- **Guide → guide (whole page):** write `[Other](../concepts/dispatcher.md)`.
  phpdoc handles the rewrite. `scripts/check-internal-links.php`
  validates the rendered output.
- **Guide → guide (section deep-link):** **NOT supported in
  phpDocumentor 3.10's Markdown plugin.** Link to the whole target
  page and let the reader scroll, or use phpdoc's RST extension
  (out of scope). The CI gate fires `could not be resolved` if an
  author writes `[X](other.md#section)`.
- **Guide → API:** write the **sentinel HTTPS URL** (see below).
- **No raw inline `<a>` HTML.** Symfony's sanitizer silently
  discards relative hrefs and phpdoc emits no warning, so the CI
  gate based on grep patterns cannot catch this case.
  `scripts/lint-docs.php` performs a positive regex check on every
  `.md` source. The algorithm must avoid two false-positive classes
  (fenced code blocks; inline backtick spans like `` `<a href>` ``
  in narrative prose):
  ```
  for each source file under docs/guide/en/**/*.md:
    inside_fence = false
    for each line in source:
      if line matches ^```: inside_fence = !inside_fence; continue
      if inside_fence: continue
      # Strip every inline backtick span — pairs of single-`,
      # double-``, or n-` (CommonMark) — before applying the regex.
      stripped = preg_replace('/`+[^`]*`+/', '', line)
      if stripped matches </?(?:a|div|span|table|tr|td|th|img|iframe|script|style)\b:
        record (file, line, match)
  exit 1 if any matches recorded; print structured summary
  ```
  Authors who need to **show** an HTML tag as code (in a tutorial
  explaining what NOT to do) place it inside backticks; the lint
  skips them. Authors who genuinely write inline HTML get a clear
  rejection message ("use a Markdown construct or the sentinel
  HTTPS URL instead").
  Only `<a>` is currently a documented stripping case; the rest
  (`<div>`, `<span>`, `<table>`, `<tr>`, `<td>`, `<th>`, `<img>`,
  `<iframe>`, `<script>`, `<style>`) are defensive — added because
  Symfony's sanitizer has its own allow-list that may or may not
  match phpdoc's `<base href>` machinery; safer to reject all and
  prompt the author for an explicit decision.
- **Anything else:** disallowed; the CI gate fails the build with a
  `could not be resolved` message pointing at the offending file.

Phase 10's guide → API mechanism uses a **sentinel HTTPS URL +
post-build rewrite** because relative `.html` and inline raw `<a>`
both fail:

**Authoring convention.** API cross-links use the reserved sentinel
host `https://api.phpbotgram.local/` followed by the rendered API
filename:

```markdown
The dispatcher delegates to
[`Router::propagateEvent`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Router.html#method_propagateEvent).
```

The sentinel host has no DNS record (`.local` is reserved by
RFC 6762). phpDocumentor emits the link verbatim:
`<a href="https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Router.html#method_propagateEvent">Router::propagateEvent</a>`.

**Post-build rewrite.** `scripts/rewrite-api-links.php` is
**HTML-aware**, not a naive string replacement. A code sample that
demonstrates the sentinel convention (a tutorial showing what to
type, or a `<pre><code>` block containing a literal sentinel URL)
would be silently corrupted by a `sed`-style rewrite. The script:

1. Loads each `*.html` under `build/docs/api/guide/` with
   `DOMDocument` (or `Symfony\Component\DomCrawler`).
2. Iterates over every `<a>` element only.
3. For each `<a>` whose `href` starts with
   `https://api.phpbotgram.local/`: rewrites the `href` attribute
   only — text content, `<pre>`/`<code>`/`<kbd>`/`<samp>` descendants,
   and any other attributes stay untouched.
4. Asserts no sentinel-URL substring remains in any rendered
   page after the rewrite, **with these exclusions** (so legitimate
   authoring constructs don't trip the gate):
   - Text content of `<pre>`, `<code>`, `<kbd>`, `<samp>`
     descendants (a literal sentinel inside a code block or inline
     code span is intentional and must survive).
   - Attribute values on any element except `<a>@href` —
     specifically `<img alt="…">`, `<a title="…">`, `<abbr title="…">`,
     and the like. Authors describing the sentinel convention in
     a screenshot caption or accessibility text shouldn't trip the
     rewrite. The rewrite only ever touches `<a>@href`, so other
     attribute values are out of scope for both the rewrite and
     the assertion.

   Authoring rule: **bare sentinel URLs in narrative prose are
   discouraged outside of `<a>@href`.** When you must show a literal
   sentinel URL as text (tutorial step demonstrating the syntax),
   wrap it in an inline backtick span: `` `https://api.phpbotgram.local/Foo.html` ``.
   This renders as `<code>` and survives both the rewrite and the
   assertion.

Rewrite math: from a page at `guide/concepts/foo.html` with
`<base href="../../">` (i.e. resolved baseURI =
`<repo-root>/<lang>/<version>/`), replacing
`href="https://api.phpbotgram.local/X"` with `href="classes/X"`
produces a browser-resolvable URL. The browser resolves `classes/X`
against the resolved baseURI to
`<repo-root>/<lang>/<version>/classes/X` — exactly the rendered API
page URL. The same rule applies at every depth — the depth-adaptive
`<base href>` does the work.

**Author-visible footgun: bare sentinel URLs in narrative prose
become CommonMark autolinks.** `Use https://api.phpbotgram.local/Foo.html`
in prose renders as a clickable `<a>` whose text and href both
display the sentinel — and the rewrite then changes the **href but
not the text**, leaving the visible URL as the literal sentinel
even though the click goes to the rewritten target. The script
optionally rewrites text content of `<a>` elements that match its
own previous text (i.e. autolink case), but the safer convention
in §"Authoring rules" is to never paste a bare sentinel; always
wrap as `[label](https://api.phpbotgram.local/...)`. If a tutorial
needs to **show** a sentinel URL as text, use an inline backtick
span: `` `https://api.phpbotgram.local/Foo.html` `` — this renders
as `<code>` and is skipped by the rewriter.

**Path-pattern contract.** phpDocumentor's default template renders
each API class to `classes/<Namespace-with-dashes>-<ClassName>.html`
with anchors `#method_<name>`, `#property_<name>`, etc. If a future
phpdoc bump changes this filename pattern, `scripts/check-docs-links.php`
fails CI loudly, and the migration trigger is "rewrite the sentinel
mapping" rather than "rewrite every guide page".

**Validation gates.**

- `scripts/check-docs-links.php` (run before the rewrite):
  - For every `https://api.phpbotgram.local/X` URL found in the
    rendered guide HTML, verify `build/docs/api/classes/X` exists on
    disk (anchor `#fragment` is also verified by greppping the target
    HTML for `id="<fragment>"`).
  - Fail CI if any sentinel URL points at a non-existent class or
    anchor.
- `scripts/rewrite-api-links.php` (post-rewrite): asserts no
  remaining `https://api.phpbotgram.local/` substring exists in any
  `*.html` under `build/docs/api/guide/`. Belt-and-braces: rewrite
  every page, then prove the rewrite was complete.
- `scripts/check-internal-links.php` (separate concern): for every
  non-sentinel internal href in `build/docs/api/guide/**/*.html`
  (e.g. `[See also](other-page.html)` cross-linking within the
  narrative), verify the link target resolves under `<base href>`.
  Excludes external `https://` links, `mailto:`, and `#anchor`-only.

### CI gate strategy

phpDocumentor v3.10 returns exit code `0` even with unresolved
references or render warnings. The `--fail-on=*` CLI flag does not
exist (verified via `vendor/bin/phpdoc --help` and exhaustive grep of
the phar). The `--log=PATH` flag exists but **does not produce a file
when the run has nothing to log** — verified empirically: a
successful build leaves no log file at the requested path.

Phase 10 therefore drops `--log=` and captures stderr+stdout into a
build-output file, then post-processes:

```bash
#!/usr/bin/env bash
# scripts/build-docs.sh
set -euo pipefail
: "${VERSION:?VERSION env var must be set}"
cd "$(dirname "$0")/.."          # anchor cwd to repo root so .phpdoc/template/ resolves
mkdir -p build/docs              # AFTER the cd, so it lands at repo/build/docs
envsubst '${VERSION}' < phpdoc.dist.xml.tpl > phpdoc.dist.xml
php scripts/copy-root-docs.php   # CHANGELOG/CONTRIBUTING → docs/guide/en/
vendor/bin/phpdoc -c phpdoc.dist.xml --no-ansi --no-progress 2>&1 \
  | tee build/docs/build.out
php scripts/check-docs-build-log.php build/docs/build.out
# Copy language-agnostic assets (SVG diagrams, code-snippets, …)
# into the rendered output so guide pages can reference them via
# relative paths under <base href>.
mkdir -p build/docs/api/guide/shared
cp -r docs/guide/shared/. build/docs/api/guide/shared/
php scripts/check-docs-links.php
php scripts/rewrite-api-links.php
php scripts/check-internal-links.php
php scripts/lint-docs.php
php scripts/check-docs-examples.php
npx markdownlint-cli2@0.22.1 'docs/guide/en/**/*.md'
```

`--no-ansi --no-progress` keep `build.out` text-clean: without them
phpdoc colour-codes its output and prepends a Unicode progress bar
to the first warning, making grep patterns brittle and CI logs
illegible.

`composer docs-api` and `make docs-api` both invoke
`scripts/build-docs.sh` — never `vendor/bin/phpdoc` directly — so
local and CI builds run the identical gate chain.

`set -euo pipefail` propagates phpdoc's real exit code through `tee`
(without `pipefail`, `tee`'s `0` would mask a phpdoc crash). The
script's shebang is explicit `#!/usr/bin/env bash` to avoid
`/bin/sh` POSIX-only behaviour on systems where `sh` is `dash`.

**Grep patterns for `scripts/check-docs-build-log.php`** (verified
empirically against phpdoc 3.10 with deliberately-broken Markdown):

phpDocumentor's default-verbosity output for doc-quality issues is
prose, not Monolog-prefixed. Empirically observed substrings on
deliberately-broken fixtures (3.10.0, May 2026):

- `could not be resolved` — emitted by
  `phpdocumentor/guides/src/ReferenceResolvers/ReferenceResolverPreRender.php`'s
  `logger->warning(...)` for any unresolved reference. The most
  common surface (most relative `.html` paths, sentinel-URL typos,
  bad `.md` doc names with fragments, dead `:ref:` directives).
- `Document with name` — emitted by `DocReferenceResolver` for an
  in-narrative `.md` link whose target doesn't exist. Sometimes
  shadowed by the fallback `could not be resolved` message; pin
  both.
- `No parent found for file` — emitted as a warning by
  `AutomaticMenuPass` when an orphan document fails to attach to
  any menu/parent. Real-world cause: a `.md` page exists outside
  any `index.md`'s `toctree`/parent path.
- `Document has no title` — emitted by
  `DocumentEntryRegistrationTransformer` when a `.md` page has no
  H1. The resulting page has an empty title/nav entry; catch
  loudly.

**Patterns explicitly NOT used as gates:**

- `Failed to ` — a phar-wide grep matches ≥30 unrelated exception
  strings (Twig, Symfony, Monolog, dependencies). When phpdoc
  exits non-zero on a true catastrophic failure, the script's
  `set -euo pipefail` already kills the build; the pattern would
  only fire on backtrace lines and risks false-positive gating.
- `We do not support plain HTML` — exists in `RawNodeEscapeTransformer`
  but only fires for `RawNode` instances which Markdown does not
  produce in 3.10. Verified empirically: inline `<a>` and
  block-level `<div>` HTML pass through phpdoc without any warning.
  `scripts/lint-docs.php` catches this case via a positive regex
  on the Markdown source (see §"Code examples" / §"Cross-references").
- Monolog level keywords (` ERROR `, ` WARNING `, ` CRITICAL `) —
  do NOT appear in default phpdoc output.

`Could not find an index file` (a hard exception thrown by
`ParseDirectoryHandler` when a guide subdirectory lacks
`index.md`) exits phpdoc with code 1 directly — `set -euo pipefail`
catches that natively. Listed in the gate patterns anyway as
belt-and-braces in case a future phpdoc switches the surface from
exception to warning.

The implementation plan's first task is a pilot pass that
re-confirms these substrings on the latest phar at implementation
time and pins them into `check-docs-build-log.php` constants. If
phpdoc adds new warning surfaces between versions, the pilot
catches them.

### Code examples (hybrid pattern)

Two presentation styles used together:

1. **Inline snippet** — fenced ```` ```php ```` block. Used for short
   conceptual illustrations (3–15 lines). Validated by
   `scripts/lint-docs.php`:

   Algorithm (handles edge cases explicitly):
   ```
   for each *.md file under docs/guide/en/:
     for each fenced code block:
       info = first word of fence info string
       if info == "php-fragment": skip (eye-review only; see below)
       if info != "php": skip
       body = block content
       body = ltrim(body)                          # remove leading whitespace
       if not body starts_with "<?php":
         body = "<?php\n" + body                   # auto-wrap for `php -l`
       write body to a temp file
       run `php -l <tempfile>`
       record (file, line, exit code, parse error message)
   exit 1 if any errors recorded; print structured summary
   ```

   Edge cases this algorithm handles correctly:
   - Block already starts with `<?php` → not doubled.
   - Block starts with `declare(strict_types=1);` (no `<?php`) →
     auto-prefixed `<?php\n` produces a valid file.
   - Block tagged `php-fragment` (class-body or expression-level
     snippet) → skipped because such fragments cannot be linted
     standalone. Authors should prefer `php` whenever possible
     because the rendered docs site can syntax-highlight known
     languages while `php-fragment` typically falls back to plain
     `<pre>`. Empirically verify highlighting during the pilot pass.

2. **Full runnable bot** — link to a real file in `examples/`:

   ```markdown
   See the [full example](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot.php).
   ```

   Validated by `scripts/check-docs-examples.php`: every link of the
   shape `examples/<name>.php` (relative or absolute github URL) must
   resolve to an existing file under `examples/` in the working tree.

### Versioning + deploy on `gh-pages` (branch mode)

Phase 9 uses Pages **workflow mode**, incompatible with multi-version
directories. Phase 10 migrates to **branch mode**.

#### One-time setup (recorded explicitly in the Phase 10 implementation plan)

**Required sequence** (out-of-order operations break the migration or
create an extended broken-site window):

1. **Bootstrap the orphan `gh-pages` branch with production root
   files**, not a placeholder. peaceiris with `keep_files: true`
   never overwrites top-level `gh-pages/index.html`, so this commit
   becomes the permanent landing-page payload.

   Use `git worktree add` so the main working tree is never
   touched (no `git rm -rf .` inside the live repo, no race window
   where the working tree is empty):

   ```bash
   # Inside the main repo working tree (master checked out, unchanged):
   git worktree add --orphan -B gh-pages /tmp/phpbotgram-gh-pages

   cd /tmp/phpbotgram-gh-pages

   # Production gh-pages/index.html — JS redirect (see "Branch layout" below for the algorithm).
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

   # Initial versions.json — empty until the first workflow run appends `dev`.
   cat > versions.json <<'JSON'
   {"versions": []}
   JSON

   # Initial languages.json — only `en` for now; future RU will append.
   cat > languages.json <<'JSON'
   {"languages": [{"id": "en", "label": "English"}]}
   JSON

   git add index.html versions.json languages.json
   git commit -m "Bootstrap gh-pages with JS redirect + empty versions/languages inventories"
   git push -u origin gh-pages

   # Clean up the worktree; the main repo's working tree was untouched.
   cd /Users/gruven/repository/github/phpbotgram
   git worktree remove /tmp/phpbotgram-gh-pages
   ```

   The `git worktree --orphan -B gh-pages` flag (git 2.42+) is the
   safe way to materialise an orphan branch in a sibling directory.
   On older git, the fallback is a tempdir clone that explicitly
   re-points `origin` at the GitHub remote (the `git clone .` step
   sets `origin` to the LOCAL repo path, not GitHub):

   ```bash
   git clone --no-checkout . /tmp/phpbotgram-gh-pages
   cd /tmp/phpbotgram-gh-pages
   git remote set-url origin git@github.com:Gruven/phpbotgram.git
   git checkout --orphan gh-pages
   git reset --hard
   # …write index.html, versions.json, languages.json…
   git add . && git commit -m "Bootstrap gh-pages…"
   git push -u origin gh-pages
   ```

   Document the chosen path (worktree vs tempdir-clone) in the
   implementation plan.

   The first peaceiris run (step 3 below) will publish `/en/dev/`
   alongside this root content; the JS redirect resolves to `/en/dev/`
   because `versions.json` will then contain an entry with `id: dev`.
2. **Merge the Phase 10 PR**, which contains:
   - The reworked `docs.yml` (see below).
   - The new `docs-release.yml`.
   - `scripts/build-docs.sh` plus the gate scripts.
   - The `.phpdoc/template/` overrides.
   - Updated workflow that on its first `master` push run also calls
     `scripts/update-versions-json.php` to add a `{"id":"dev",
     "path":"en/dev/", "stable": false}` entry to the bootstrapped
     `versions.json` (so the redirect picks it up immediately).
3. **Verify** `git ls-tree origin/gh-pages -r --name-only` lists
   `en/dev/index.html` after the first `master` workflow run
   completes.
4. **Flip the repo Pages source via the GitHub UI**: settings → Pages
   → "Source" from "GitHub Actions" to "Deploy from a branch", branch
   `gh-pages`, folder `/`. By doing this AFTER `gh-pages` already
   carries the production landing-page payload and at least one
   `/en/dev/` version, the public site never serves the placeholder
   string. GitHub may take seconds-to-minutes to reflect the new
   source; brief Pages downtime during the flip is acknowledged.
5. **Smoke test:** verify `https://gruven.github.io/phpbotgram/`
   redirects to `/en/dev/index.html` (which returns `HTTP 200`). The
   smoke-test step is part of the implementation plan's DoD
   verification (manual, one-time).

This order eliminates the "site shows literal `bootstrap` string"
window that an obvious-but-wrong ordering would produce.

#### `docs.yml` migration (Phase 9 → Phase 10)

The Phase 9 workflow has **two jobs** — `build` (composer install +
phpdoc + `actions/upload-pages-artifact@v3`) and `deploy` (`needs:
build` + `environment: github-pages` + `actions/deploy-pages@v4`).

Phase 10 **collapses these into a single `build` job** with peaceiris
as the final step. The `deploy` job and the artifact upload/download
disappear. This is the cleaner of the two viable structures (the
alternative — keep two jobs, pass `build/docs/api/` via
`actions/upload-artifact` + `actions/download-artifact` — adds I/O
overhead without any benefit since peaceiris commits directly to the
branch).

**Phase 9 fields preserved unchanged in the rewritten workflow:**

- `name: API documentation`
- `on: { push: { branches: [master] }, workflow_dispatch: {} }`
- `runs-on: ubuntu-latest` on the (single) `build` job
- `actions/checkout@v5` step
- `shivammathur/setup-php@v2` step with
  `php-version: "8.5"`, `extensions: mbstring, sodium, json`,
  `tools: composer:v2`, `coverage: none`
- `actions/cache@v4` step caching `vendor/` keyed on `composer.lock`
- `composer install --no-interaction --no-progress --prefer-dist`
- `composer docs-api` (whose internals are rewritten per
  §"CI gate strategy" but the workflow line stays the same) —
  with one new addition: the step gains an `env: VERSION: dev`
  block (or `env: VERSION: ${{ github.ref_name }}` in
  `docs-release.yml`) so `scripts/build-docs.sh`'s
  `: "${VERSION:?...}"` assertion is satisfied. Step shape:
  ```yaml
  - name: Build docs
    env:
      VERSION: dev                 # docs.yml
      # VERSION: ${{ github.ref_name }}   # docs-release.yml
    run: composer docs-api
  ```

**Phase 9 fields modified or removed:**

| Field | Phase 9 value | Phase 10 value |
| --- | --- | --- |
| `permissions.contents` | `read` | `write` (peaceiris pushes gh-pages) |
| `permissions.pages` | `write` | **remove** (branch mode doesn't use Pages API) |
| `permissions.id-token` | `write` | **remove** (no OIDC token needed) |
| `environment` block | `name: github-pages` + `url: …page_url` | **remove the entire block** |
| `concurrency.group` | `pages` | `pages-write` |
| `concurrency.cancel-in-progress` | `true` | `false` (queue, don't cancel) |
| `actions/upload-pages-artifact@v3` step | present | **remove** |
| `deploy` job (entire job) | present (`needs: build`) | **remove the entire job** |
| `actions/deploy-pages@v4` step | present in `deploy` | **remove** (lives in the deleted job) |

**Phase 10 NEW steps added to the single `build` job**:

0. **Between `actions/cache@v4` and `composer install`** — insert
   `actions/setup-node@v4` with `node-version: '20'`. Required by
   `npx markdownlint-cli2 'docs/guide/en/**/*.md'` inside
   `scripts/build-docs.sh`. Phase 9 did not need Node; Phase 10
   introduces this dependency.

After `composer docs-api`, in order:

1. **Checkout `gh-pages` into a worktree**:
   ```yaml
   - name: Checkout gh-pages
     uses: actions/checkout@v5
     with:
       ref: gh-pages
       path: gh-pages-worktree
   ```
   Provides a writable view of the current `gh-pages` tip so the
   versions.json update can run locally.
2. **Update `versions.json`** for the `dev` entry:
   ```yaml
   - name: Update versions.json with dev entry
     run: |
       php scripts/update-versions-json.php \
         gh-pages-worktree/versions.json \
         --upsert id=dev path=en/dev/ label="dev (master)" stable=false
       mkdir -p build/docs/root-publish
       cp gh-pages-worktree/versions.json build/docs/root-publish/versions.json
   ```
   `update-versions-json.php` parses each `key=value` arg with
   `explode('=', $arg, 2)` so label values containing `=` survive.
3. **Publish the rendered site** to `en/dev/`:
   ```yaml
   - name: Publish en/dev
     uses: peaceiris/actions-gh-pages@v4
     with:
       github_token: ${{ secrets.GITHUB_TOKEN }}
       publish_branch: gh-pages
       publish_dir: build/docs/api
       destination_dir: en/dev
       keep_files: false   # let peaceiris wipe en/dev/ before re-upload
       commit_message: "publish ${{ github.sha }} → en/dev"
   ```
   peaceiris' internal git-rm wipe runs only inside `destination_dir`,
   so `en/dev/` is replaced atomically while other version dirs and
   root files are preserved unconditionally. `keep_files: false`
   prevents stale dead files (deleted classes/pages/assets) from
   accumulating across builds.
4. **Publish the updated `versions.json`** to gh-pages root:
   ```yaml
   - name: Publish versions.json
     uses: peaceiris/actions-gh-pages@v4
     with:
       github_token: ${{ secrets.GITHUB_TOKEN }}
       publish_branch: gh-pages
       publish_dir: build/docs/root-publish
       destination_dir: ''
       keep_files: true    # PRESERVE other root files (index.html, languages.json, en/, …)
       commit_message: "update versions.json → dev=${{ github.sha }}"
   ```
   Here `destination_dir: ''` means "the branch root" — peaceiris
   would wipe the entire branch if `keep_files: false`. `keep_files:
   true` restricts the rewrite to files in `publish_dir`, which is
   the scratch dir containing only the new `versions.json`.

**Critical: `peaceiris/actions-gh-pages@v4` requires explicit
`github_token: ${{ secrets.GITHUB_TOKEN }}`.** The action does NOT
auto-pick up the environment-exposed token; verified by reading the
action's `get-inputs.ts`. Every peaceiris step in both workflows
must set this input.

`commit_message` per step gives `gh-pages` a greppable history of
which workflow run touched which path.

#### `docs-release.yml` (new)

Triggered on `push: tags: 'v*.*.*'`. Same build pipeline as
`docs.yml`, then a sequence of steps that handles versions.json
atomically:

1. **Materialise the `gh-pages` worktree** via a second
   `actions/checkout@v5` step:
   ```yaml
   - name: Checkout gh-pages
     uses: actions/checkout@v5
     with:
       ref: gh-pages
       path: gh-pages-worktree
   ```
   This gives the workflow a read-write filesystem view of the
   current `gh-pages` tip.
2. **Update `versions.json`** in the worktree:
   ```bash
   php scripts/update-versions-json.php \
     gh-pages-worktree/versions.json \
     --upsert id="${GITHUB_REF_NAME}" \
              path="en/${GITHUB_REF_NAME}/" \
              label="${GITHUB_REF_NAME}" \
              stable=auto
   ```
   The contract (see below) dedups by `id` and flips prior entries
   to `stable: false`.
3. **Publish three things via three peaceiris invocations, in
   this exact order**, all serialised behind the shared
   `concurrency.group: pages-write`:
   1. `build/docs/api/` → `gh-pages/en/<tag>/`: `keep_files: false`
      (re-upload the version dir atomically; peaceiris wipes
      inside `destination_dir` only).
   2. `build/docs/api/` → `gh-pages/en/latest/`: `keep_files: false`
      (same reasoning).
   3. Updated `versions.json` → `gh-pages/`: copy
      `gh-pages-worktree/versions.json` into a scratch
      `build/docs/root-publish/` directory, then publish with
      `publish_dir: build/docs/root-publish`, `destination_dir: ''`,
      `keep_files: true`. Here `keep_files: true` is **required** —
      `destination_dir: ''` means "branch root", which would wipe
      `en/`, `index.html`, and `languages.json` if `keep_files`
      were false. With it true, the rewrite is scoped to files
      present in `publish_dir` (the single `versions.json` file).

**Order matters.** Step 3 publishes `versions.json` after steps 1
and 2 so the JS redirect on `gh-pages/index.html` never points at a
path that isn't yet live. If step 3 ran first, visitors hitting
`https://gruven.github.io/phpbotgram/` between step 3 and step 1
would be redirected to `/en/<tag>/index.html` and see a 404.

All four peaceiris invocations (one in `docs.yml`, three in
`docs-release.yml`) use:

- `github_token: ${{ secrets.GITHUB_TOKEN }}` — the action does NOT
  auto-pick up the env-exposed token; verified by reading
  `peaceiris/actions-gh-pages` source.
- `commit_message: "release ${{ github.ref_name }}: <destination>"`
  (per-step, for grep-friendliness in `gh-pages` history).

Race analysis: the `gh-pages` worktree captured in step 1 is a
snapshot. If a concurrent `docs.yml` master-push run modifies
`versions.json` between step 1 and step 3's third invocation, the
release run's `versions.json` write will succeed but lose the
master-push entry. **The shared `concurrency.group: pages-write`
with `cancel-in-progress: false` serialises both workflows at the
workflow level**, so this race cannot happen unless GitHub silently
drops the queue ordering. peaceiris does not auto-rebase on push
conflict (verified at the action's source); if a manual rerun
out-of-order produces a push conflict, the failing workflow must
be re-run by hand.

#### Concurrency

Both workflows share **one** concurrency group, declared at the
**workflow top level** (outside `jobs:`, in both `docs.yml` and
`docs-release.yml`). Job-level concurrency would only serialise
jobs within the same workflow, not across workflows:

```yaml
# Top-level, alongside `name:`, `on:`, `permissions:`
concurrency:
  group: pages-write
  cancel-in-progress: false
```

`peaceiris/actions-gh-pages@v4` does not auto-rebase or retry on
push conflict (verified at the action's README and source). With one
shared group + `cancel-in-progress: false`, a tag push during a
master push queues behind it. The trade-off: a slow build delays
releases. Acceptable for v0.1 release cadence.

If a race still occurs (e.g. manual reruns triggered out-of-order),
the failing workflow must be re-run by hand — the spec does not
promise auto-recovery.

#### Branch layout on `gh-pages`

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
    latest/                  # full copy of newest stable tag; missing until v0.1.0
    v0.1.0/                  # built from tag (missing pre-release)
```

`gh-pages/index.html` is a small inline-JS page that:

1. Computes the repo-root URL by walking `location.pathname` up
   one segment (e.g. from `https://gruven.github.io/phpbotgram/` the
   root is the current page). `fetch('versions.json')` (no leading
   slash) resolves correctly against the document URL.
2. Fetches `versions.json`.
3. Picks the first version flagged `stable: true`, or falls back to
   `"dev"` if none exists yet.
4. `window.location.replace(...)` to that path.

**No leading-slash absolute URLs.** This avoids the user-pages
footgun where `/versions.json` resolves to
`https://gruven.github.io/versions.json` (the owner's root, not the
project's). The same convention applies to the navbar switcher Twig
override — see below.

This redirect works before `v0.1.0`: visitors are redirected to
`/en/dev/` until the first tag lands.

`versions.json` schema:

```json
{
  "versions": [
    { "id": "v0.1.0",  "label": "v0.1.0",       "path": "en/v0.1.0/", "stable": true  },
    { "id": "dev",     "label": "dev (master)", "path": "en/dev/",    "stable": false }
  ]
}
```

`label` is the static display name. The switcher Twig partial
appends `(latest)` dynamically by reading the entry's `stable` flag,
so labels stay correct as new releases land (no need to rewrite
prior entries' labels when their `stable` flips to `false`).

`scripts/update-versions-json.php` contract:

CLI: `php scripts/update-versions-json.php <path-to-versions.json>
--upsert id=<id> path=<path> label=<label> stable=<true|false|auto>`.

Each `key=value` arg is parsed with `explode('=', $arg, 2)` so values
containing `=` survive intact.

Algorithm:

1. Load existing JSON (or initialise to `{"versions": []}` if
   missing/empty).
2. **Dedup:** remove any existing entry whose `id` equals the new
   one (handles tag force-push / re-publish without producing
   duplicate dropdown rows).
3. **Stable flag handling:**
   - If `stable=auto` (used for tag pushes): use `version_compare`
     to check the new `id` against every existing tag-shaped `id`
     in the file (entries whose `id` matches `/^v\d+\.\d+\.\d+/`,
     so the `dev` entry is excluded). If the new id is strictly
     greater than all existing tag ids, set this entry to `stable:
     true` AND flip every other entry's `stable` to `false`.
     Otherwise (the new tag is older than an existing one — a
     backport / out-of-order publish): set this entry's `stable:
     false` and leave every other entry's `stable` flag
     untouched. The dropdown still lists the backport so users can
     read its docs, but the landing redirect continues to point
     at the actually-newest release.
   - If `stable=true` (explicit override): flip every other
     entry's `stable` to `false`, set this entry stable.
   - If `stable=false` (used for dev pushes): leave all other
     entries alone.
4. **Insertion order:** insert the new entry as the first array
   element so the dropdown lists newest-on-top. Authors who care
   about explicit ordering can post-process the JSON manually.
5. Write atomically (write-to-temp + rename) to the same path.

This guarantees: (a) the redirect on `gh-pages/index.html` always
points at the newest stable version (when one exists); (b) force-
pushing a tag doesn't produce a phantom duplicate row in the
switcher; (c) the `dev` entry is appended on the first master push
post-bootstrap without poisoning any later `stable: true` flags;
(d) an out-of-order backport publish (e.g. v0.1.0 after v0.2.0 is
already shipped) does not demote the actual newest release to
`stable: false`.

The `docs-release.yml` workflow uses `stable=auto` so the semver
comparison runs unconditionally; the `docs.yml` workflow uses
`stable=false` for the `dev` entry.

`languages.json` schema:

```json
{ "languages": [ { "id": "en", "label": "English" } ] }
```

Each workflow runs:

1. Checkout repo on the appropriate ref.
2. `composer install --no-interaction --no-progress --prefer-dist`.
3. `VERSION=<dev|vX.Y.Z> composer docs-api` (envsubst + phpdoc +
   all post-build gates via `scripts/build-docs.sh`).
4. `peaceiris/actions-gh-pages@v4` publishes `build/docs/api/` to
   `en/<VERSION>/`.
5. Tag workflow only: additional peaceiris invocations as listed
   above (publish to `en/latest/`, then publish updated
   `versions.json`).

Old versions never auto-delete; manual cleanup via direct `gh-pages`
commit if it ever matters.

### Version + language switcher: template override via `.phpdoc/template/`

Post-build HTML rewriting is fragile (phpDocumentor template HTML
shape can change between point releases). Phase 10 uses
phpDocumentor's **template-override** mechanism, auto-loaded from
`.phpdoc/template/` relative to the config file's directory.

Phase 10 ships `.phpdoc/template/` at the project root, containing
**only** the Twig files we need to override (typically
`base.html.twig` + a new `_includes/switcher.html.twig`). Files not
overridden compose from the upstream default template.

The switcher partial:

- Renders two `<select>` elements in the navbar.
- Inline-`<script>` fetches `versions.json` and `languages.json` at
  page load.
- **Path resolution is language-agnostic.** A leading-slash absolute
  URL (`fetch('/versions.json')`) would 404 on user-pages because it
  resolves to `https://gruven.github.io/versions.json` (owner root,
  not project root). The partial computes the repo root by stripping
  exactly two trailing path segments from `document.baseURI` —
  phpDocumentor's `<base href>` always resolves to
  `<repo-root>/<lang>/<version>/<page-dir>/`, so stripping
  `<page-dir>` and `<version>` lands on the language root, and one
  more strip lands on the repo root. This holds for any language
  code (`en`, `ru`, future others) without re-tuning the algorithm:

  ```js
  // document.baseURI for guide/concepts/test.html with <base href="../../">
  // resolves to https://gruven.github.io/phpbotgram/en/dev/
  // After stripping the last two segments we land on /phpbotgram/.
  const base = new URL(document.baseURI);
  const langRoot = base.pathname.replace(/\/[^/]+\/$/, '/');  // strip /<version>/
  const repoRoot = langRoot.replace(/\/[^/]+\/$/, '/');       // strip /<lang>/
  // For project-as-root deployments (no /phpbotgram/ subpath),
  // repoRoot is '/'. For subpath deployments it's '/phpbotgram/'.
  const repoRootUrl = base.origin + repoRoot;
  Promise.all([
    fetch(repoRootUrl + 'versions.json').then(r => r.json()),
    fetch(repoRootUrl + 'languages.json').then(r => r.json()),
  ]).then(([versions, languages]) => /* populate selects */);
  ```

  The algorithm is language-name-blind, so adding RU later requires
  zero switcher-code changes.

- Populates options, marks the current selection, disables
  unavailable cross-products (e.g. `ru/v0.1.0/` before RU is
  translated).

**Maintenance burden:** on each phpDocumentor major bump, diff
`.phpdoc/template/` against the new upstream `data/templates/default/`
inside the new phar to catch structural drift. CI does not enforce
this automatically; it's a release-engineering checklist item flagged
when `phpdocumentor/shim` is bumped.

### i18n: foundation, not feature

The Markdown source lives under `docs/guide/en/`. The build config
has one `<guide>` block targeting `docs/guide/en`. The output lands at
`build/docs/api/guide/` and is published to `gh-pages/en/<version>/`.

**Adding a Russian translation later requires (exhaustive list):**

1. Author `docs/guide/ru/` mirroring `en/`'s tree.
2. **Per-language phpdoc invocation strategy.** Building two `<guide>`
   blocks under one `<version>` would conflict on `output="guide"`.
   The chosen strategy: invoke phpdoc twice (once per language) into
   separate `paths.output` (e.g. `build/docs/en/` and
   `build/docs/ru/`); rsync each output to its own
   `/<lang>/<version>/` path on `gh-pages`.
3. **Per-language search index.** phpDocumentor's default template
   builds `js/searchIndex.js` per render. With two invocations there
   are two indexes; the navbar switcher must load the correct one
   for the current page (the switcher already knows the active
   language).
4. **Default-language redirect.** Today `gh-pages/index.html` picks a
   version. When RU lands, it must first pick a language (browser
   `navigator.language`-aware, falling back to `en`), then a version.
5. Update `languages.json` to add `ru`.
6. Update `versions.json` if some versions only exist in one language
   (status flag per entry).
7. Update the switcher Twig override to gracefully handle "this page
   has no `ru` translation" — link disabled or fall through to `en`
   with a banner.
8. Asset paths under `<base href>` from RU pages mirror the EN
   conventions; `docs/guide/shared/` references remain identical.
9. Decide on translation-freshness policy (none in this spec).
10. If a sitemap is added later (currently out of scope), update it
    to enumerate both languages.
11. Two parallel `scripts/rewrite-api-links.php` invocations (one per
    language) operating on each language's rendered output.

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
      media-group.md
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
      chat-member-updated.md          # bot-was-added-to-group transition handling
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
      serialization.md                # Bot DTO ↔ wire JSON: Serializer::dump/load, BotContextController
      architecture-decisions.md       # phpbotgram-specific; divergences from aiogram
    reference/
      index.md                        # stub linking to ../../classes/ (API)
    changelog.md                      # generated copy of CHANGELOG.md (gitignored)
    contributing.md                   # generated copy of CONTRIBUTING.md (gitignored)
    migration/
      .gitkeep                        # reserved for future breaking changes; empty by design
                                      # NOTE: when the first migration page is written,
                                      # also add migration/index.md to keep navigation tidy.
  shared/
    assets/
      diagrams/                       # SVG/PNG, language-agnostic
      code-snippets/                  # *.php fragments, included into .md
```

**Page count (honest):** 5 tutorial pages + 20 how-to pages + 16
concept pages (adds `serialization.md`) + 4 Diataxis index pages
(one of which is the reference-stub) + top-level landing + changelog
+ contributing = **48 pages**. Plus a `.gitkeep` placeholder in
`migration/` (phpDocumentor scans the directory but emits nothing
from a hidden file — verified harmless on a real build).

**`docs/guide/en/changelog.md` and `docs/guide/en/contributing.md`:**
Markdown has no `include::` directive. `scripts/copy-root-docs.php`
runs early in `scripts/build-docs.sh` (after envsubst, before phpdoc)
and copies the project-root `CHANGELOG.md` and `CONTRIBUTING.md` into
`docs/guide/en/`. The script's contract:

- Prepends a `<!-- AUTO-GENERATED — do not edit; source: /CHANGELOG.md -->`
  banner to each copy so contributors don't accidentally edit the
  generated file.
- Preserves the source's mtime via `touch(target, sourceMtime)` so
  phpDocumentor's `<paths><cache>` mtime check doesn't force a full
  rebuild on every CI run.
- Exits non-zero with a clear message if either source file is
  missing or the target path is a directory.

Phase 10 scope also includes:

- Adding three explicit `.gitignore` lines (anchored with leading `/`
  to the repo root):
  ```
  /phpdoc.dist.xml
  /docs/guide/en/changelog.md
  /docs/guide/en/contributing.md
  ```
- Creating `CONTRIBUTING.md` at the project root with sections: how
  to open issues, PR workflow (branch from master, run `composer
  test` and `composer docs-api`), coding standards (PHPStan level 9 +
  php-cs-fixer + the project's existing tooling targets), commit
  message convention. The file is committed as part of Phase 10.
- Committing `.markdownlint.jsonc` so the copied files pass — either
  by including a permissive line-length rule (CHANGELOG entries
  often run long) or by excluding the two generated file paths from
  lint scope. The implementation plan pins the chosen approach
  after a pilot lint run.

**`docs/superpowers/` exclusion guard:** the template's
`<guide><source>` points at `docs/guide/en` exactly.
`docs/superpowers/` (specs + plans, internal-only) is never scanned.
This is stated explicitly here to prevent future "let's just point
at `docs/`" simplifications.

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
| `scripts/copy-root-docs.php` | Copy CHANGELOG/CONTRIBUTING to `docs/guide/en/` pre-build; banner + mtime preservation | repo |
| `scripts/lint-docs.php` | Extract `\`\`\`php` blocks (auto-prepend `<?php\n`), `php -l`; skip `php-fragment` blocks | repo |
| `scripts/check-docs-build-log.php` | Grep `build.out` for `could not be resolved`, `No parent found for file` (pilot-pinned at implementation time) | repo |
| `scripts/check-docs-links.php` | Verify every `https://api.phpbotgram.local/X` sentinel URL points at an existing `build/docs/api/classes/X` file + anchor | repo |
| `scripts/rewrite-api-links.php` | Replace every `https://api.phpbotgram.local/` → `classes/` in `build/docs/api/guide/**/*.html`; assert no sentinel remains afterwards | repo |
| `scripts/check-internal-links.php` | `<base href>`-aware: verify every non-sentinel, non-external internal href in `build/docs/api/guide/**/*.html` resolves; anchor IDs checked when present | repo |
| `scripts/check-docs-examples.php` | Verify every `examples/*.php` link is real | repo |
| `scripts/update-versions-json.php` | Atomic versions.json updater; flips `stable: false` on prior entries, appends new entry as `stable: true` | repo |
| `versions.json`, `languages.json` | Switcher inventory | `gh-pages` root |
| `.github/workflows/docs.yml` | (Migrated from Phase 9) master → `/en/dev/` | repo |
| `.github/workflows/docs-release.yml` | tag → `/en/<tag>/` + `latest/` + `versions.json` | repo |
| `composer docs-api` / `make docs-api` | Both delegate to `scripts/build-docs.sh` (sync'd in lockstep) | repo |
| `CONTRIBUTING.md` | Created by Phase 10 at project root | repo root |

### `scripts/check-internal-links.php` contract

- Walks every `*.html` under `build/docs/api/guide/`.
- Extracts every `href` attribute.
- Skips:
  - `http://` / `https://` URLs (external; not our concern except for
    the sentinel host which is handled by `check-docs-links.php`).
  - `mailto:` URLs.
- **Fragment-only (`#…`) links** (no path part): validated as
  anchors against the SAME page they appear on. The script opens
  the containing HTML once, builds a set of `id="…"` attribute
  values, and rejects any `#fragment` link whose target isn't in
  that set.
- For each remaining link with a path part: resolves it against
  the page's `<base href>` (parsed from the same HTML), joins with
  the rendered output directory, and verifies the target file
  exists.
- For links with an anchor (`#method_foo`), opens the target HTML
  and verifies an `id="method_foo"` attribute exists. Anchor IDs
  are part of the phpDocumentor template contract; broken anchors
  are CI-blocking and signal a template-format change worth
  investigating.

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
    │   ├─ scripts/check-docs-build-log.php   (gate)
    │   ├─ scripts/check-docs-links.php       (gate: validate sentinel URLs)
    │   ├─ scripts/rewrite-api-links.php      (rewrite sentinel → classes/, assert clean)
    │   ├─ scripts/check-internal-links.php   (gate: post-rewrite, base-href-aware)
    │   ├─ scripts/lint-docs.php              (gate)
    │   ├─ scripts/check-docs-examples.php    (gate)
    │   └─ markdownlint-cli2 'docs/guide/en/**/*.md'  (gate)
    ├─ scripts/update-versions-json.php gh-pages-worktree/versions.json
    │     --upsert id=dev path=en/dev/ label="dev (master)" stable=false
    ├─ cp gh-pages-worktree/versions.json build/docs/root-publish/versions.json
    ├─ peaceiris (publish_dir=build/docs/api, destination_dir=en/dev,
    │             keep_files=false)
    └─ peaceiris (publish_dir=build/docs/root-publish, destination_dir='',
                  keep_files=true)
    # NB: shared-asset copy (docs/guide/shared/ → build/docs/api/guide/shared/)
    # happens inside scripts/build-docs.sh, not as a separate workflow step.

tag push v0.1.0
    │
    ▼
docs-release.yml workflow
    ├─ checkout master @ tag, setup-php, setup-node, cache, composer install
    ├─ env VERSION=${{ github.ref_name }} composer docs-api  (build + gates)
    ├─ actions/checkout@v5 ref=gh-pages path=gh-pages-worktree
    ├─ scripts/update-versions-json.php gh-pages-worktree/versions.json
    │     --upsert id=${REF} path=en/${REF}/ label="${REF}" stable=auto
    ├─ cp gh-pages-worktree/versions.json build/docs/root-publish/versions.json
    │
    ├─ peaceiris (1) build/docs/api → en/${REF}/   keep_files=false
    ├─ peaceiris (2) build/docs/api → en/latest/    keep_files=false
    └─ peaceiris (3) build/docs/root-publish → ''   keep_files=true
       # Order matters: versions.json (step 3) is published LAST so
       # the JS redirect never points at a path that isn't yet live.
       # Shared-asset copy is inside scripts/build-docs.sh, not a workflow step.
```

## Error handling

| Failure | Detected by | Behaviour |
| --- | --- | --- |
| `${VERSION}` env var unset | `: "${VERSION:?...}"` in `scripts/build-docs.sh` | Build fails before phpdoc |
| `tee` masks phpdoc crash | `set -euo pipefail` in `build-docs.sh` | pipefail propagates real exit code |
| Markdown syntax error / unresolved reference | `phpdoc` stderr (patterns: `could not be resolved`, `No parent found for file` — pilot-pinned) → `scripts/check-docs-build-log.php` | Build fails |
| Broken sentinel API link | `scripts/check-docs-links.php` | Build fails |
| Post-rewrite has leftover `https://api.phpbotgram.local/` | `scripts/rewrite-api-links.php` (self-assertion) | Build fails |
| Broken inter-narrative hyperlink | `scripts/check-internal-links.php` post-build walk with `<base href>` resolution | Build fails |
| `\`\`\`php` block fails `php -l` | `scripts/lint-docs.php` with `<?php\n` prepended | Build fails |
| Linked `examples/X.php` missing | `scripts/check-docs-examples.php` | Build fails |
| Markdown style violation | `markdownlint-cli2` with `.markdownlint.jsonc` config (excludes copied changelog/contributing) | Build fails |
| Pages source not switched to branch mode | First Phase 10 deploy: site does not update (stale Phase 9 artifact) | Implementation plan flags as a one-time UI step before merge |
| `gh-pages` branch doesn't exist | `peaceiris/actions-gh-pages@v4` errors on first push | Implementation plan includes the bootstrap commit |
| `gh-pages` push race between dev and release | Single shared `concurrency.group: pages-write` with `cancel-in-progress: false` | Queued, not cancelled |
| `versions.json` corrupted by overlapping releases | `scripts/update-versions-json.php` writes atomically (write-rename) | Self-healing |
| Future phpdoc bump changes API page filenames | `scripts/check-docs-links.php` fails CI | Phase 10+1 migration trigger |
| Future phpdoc bump changes `<base href>` shape | `scripts/check-internal-links.php` fails CI | Same trigger |
| `CONTRIBUTING.md` missing | `scripts/copy-root-docs.php` exits non-zero with a clear message | Phase 10 creates the file as part of scope |
| Switcher `fetch()` resolves to wrong origin | Switcher uses `<base href>`-derived path (not leading-slash absolute) — no user-pages footgun | Won't occur |

## Testing strategy

### Per-PR (gate)

1. `composer test` — existing unit test suite still passes.
2. `make coverage-gate` — per-module floors still met.
3. `composer docs-api` — runs the full pipeline (build + all gates).
4. The pipeline itself contains 7 doc-quality gates (build-log grep,
   sentinel-link check, rewrite-completeness assertion, internal-link
   resolver, php-l, example resolver, markdownlint).

### Per-release

5. The published `/en/<tag>/index.html` returns HTTP 200.
6. Spot-check: navbar switcher template-override loaded the new
   version.
7. Spot-check: API reference within the new version still works
   (click through a guide → API sentinel link, end up on the right
   class page).
8. `versions.json` lists the new entry with `stable: true`; every
   prior entry has `stable: false`.

### Manual content review checklist (per page)

- **tutorial/**: contains either a runnable snippet (linted) or a
  `[full example](examples/X.php)` link pointing to a real file under
  `examples/`.
- **concepts/**: ≥ 1 sentinel hyperlink (`https://api.phpbotgram.local/...`)
  into the API reference.
- **how-to/**: starts with "When to use this", ends with "Pitfalls".
- **index.md**: 4 Diataxis quadrants linked, each with a one-sentence
  hook.
- Fenced blocks tagged `php-fragment` are eye-reviewed (not
  auto-linted).

No content-quality automation in scope; reviewer judgment covers
grammar, voice, and accuracy.

### Pilot pass (first task of the implementation plan)

Before pinning the grep-pattern constants and the sentinel-rewrite
assumptions, the implementation plan runs a controlled empirical
pilot:

1. Author one deliberately-broken Markdown page (broken sentinel
   link, broken cross-link, broken markdown directive).
2. Run `vendor/bin/phpdoc -c phpdoc.dist.xml 2>&1 | tee
   build/docs/build.out`.
3. Inspect `build.out` for the actual warning/error format phpdoc
   emits.
4. Pin the exact pattern constants in `check-docs-build-log.php`.
5. Verify the sentinel-URL passthrough behaviour holds (the
   `https://api.phpbotgram.local/...` href is preserved by phpdoc).
6. Verify `<base href>`'s depth-adaptive value on a deep narrative
   page (e.g. `concepts/dispatcher.html`) is what the rewrite script
   expects.
7. Verify `php-fragment` fence syntax-highlighting on the rendered
   page (acceptable or fallback-to-plain noted in §Code examples).
8. Remove the deliberately-broken page.

This avoids guessing at log formats or template behaviour that may
have changed between phpdoc point releases.

## Definition of done

- `docs/guide/en/` populated per the content tree (5 tutorial + 20
  how-to + 16 concepts + 4 Diataxis index pages including
  `reference/index.md` + top-level landing + `changelog.md` +
  `contributing.md` = **48 pages**, plus a `migration/.gitkeep`).
- `phpdoc.dist.xml.tpl` produces a single site combining narrative +
  API reference; sentinel cross-link strategy validates end-to-end
  (sentinel survives phpdoc rendering, rewrite produces correct
  relative paths, browser resolution against `<base href>` reaches
  the right API page).
- `.phpdoc/template/` ships the navbar switcher; phpdoc loads it
  successfully (the rendered HTML contains the two `<select>`
  elements; their JSON fetch uses `<base href>`-derived URLs, not
  leading-slash absolute).
- `.markdownlint.jsonc` ships at the repo root with rules the
  generated `changelog.md` and `contributing.md` pass.
- `CONTRIBUTING.md` created at the project root with the documented
  section list.
- `.github/workflows/docs.yml` migrated to branch-mode per the
  Phase-9-to-Phase-10 field-by-field table; publishes master push to
  `/en/dev/`.
- `.github/workflows/docs-release.yml` publishes tag push to
  `/en/<tag>/` + `/en/latest/` + `versions.json` update.
- Repo Pages settings switched to "Deploy from branch: gh-pages /"
  (one-time manual UI step, documented in the implementation plan;
  brief Pages downtime acknowledged).
- `gh-pages` orphan branch bootstrapped (one-time manual commit,
  documented in the implementation plan).
- Phase 9's `concurrency.group: pages` renamed to `pages-write` AND
  `cancel-in-progress` flipped from `true` to `false` in both
  workflows.
- Phase 9's `permissions:` block updated to `contents: write` only
  (drop `pages: write` and `id-token: write`); `environment:
  github-pages` block removed.
- `composer.json` script `docs-api` rewritten to invoke
  `scripts/build-docs.sh` (replaces `phpdoc -c phpdoc.dist.xml`).
- Makefile `docs-api` target rewritten to invoke
  `scripts/build-docs.sh` (kept in sync with the `composer docs-api`
  script so local and CI behave identically).
- `composer.json` constraint for `phpdocumentor/shim` tightened from
  `^3` to `~3.10.0` (`>=3.10.0 <3.11.0`; composer-level guard pinning
  to the 3.10.x minor against an undisclosed phpdoc minor bump). The
  bare `~3.10` form expands to `>=3.10.0 <4.0.0` and is identical
  to `^3.10`, which is NOT the intended guard.
- `.gitignore` extended with `/phpdoc.dist.xml`,
  `/docs/guide/en/changelog.md`, `/docs/guide/en/contributing.md`.
- `https://gruven.github.io/phpbotgram/en/dev/index.html` renders
  with navbar showing language + version switchers (currently `[en]`
  and `[dev]` entries only).
- All seven pipeline gates green on the Phase 10 merge commit.
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
- Replacing the sentinel-URL rewrite strategy with a proper
  phpDocumentor guides extension (cleaner architecturally but
  invasive; can revisit when phpdoc adds Markdown text-role support
  upstream).

## Open questions / unverified assumptions

The toolchain claims that **are** mechanically verified against the
v3.10.0 phar bundled by `phpdocumentor/shim ^3` (verified
2026-05-15):

- `<guide format="md">` block exists and renders Markdown via the
  bundled `phpdocumentor/guides-markdown` package.
- `<template>` is a sibling of `<version>` per the XSD; template
  overrides are auto-loaded from `.phpdoc/template/` next to the
  config file.
- `vendor/bin/phpdoc --help` confirms the absence of `--fail-on=*`
  and the presence of `--log=` (which does not produce a file when
  the run has no log content).
- `<source dsn=".">` is observed-working.
- phpDocumentor's Markdown plugin discards `<a>` tags whose href is
  not http/https/mailto/anchor — verified empirically. Sentinel
  HTTPS URLs (e.g. `https://api.phpbotgram.local/...`) pass through
  intact.
- `<base href>` is depth-adaptive (`./`, `../`, `../../`, …) per
  rendered page; verified empirically.
- Doc-quality warning surface uses prose substrings (`could not be
  resolved`, `No parent found for file`) without Monolog level
  prefixes.

Items the **implementation plan's pilot pass** re-confirms at
implementation time (so a phpdoc point bump doesn't silently
invalidate the spec):

- The exact pattern set for `check-docs-build-log.php`.
- The `<base href>` depth-adaptivity at every Diataxis page depth.
- Sentinel-URL passthrough behaviour.
- `php-fragment` fence rendering on the rendered docs site.

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
- Verified against phpDocumentor 3.10.0 phar bundled by
  `phpdocumentor/shim ^3` on 2026-05-15.
