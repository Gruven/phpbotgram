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
    // Resolve against <base href>. Canonicalise $pageDir via realpath()
    // so symlink-traversal mismatches (notably macOS /var → /private/var)
    // don't make the str_starts_with check unconditionally fail.
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
