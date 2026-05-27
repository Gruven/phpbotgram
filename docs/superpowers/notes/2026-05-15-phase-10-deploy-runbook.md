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

For git < 2.42, use the tempdir-clone fallback: `git clone --depth=1 .
/tmp/ghpages-fallback && cd /tmp/ghpages-fallback && git checkout
--orphan gh-pages && git rm -rf .` then proceed with the same `cat`
heredocs.

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
