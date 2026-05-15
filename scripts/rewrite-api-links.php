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
    if (!$a instanceof DOMElement) {
      continue;
    }
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
