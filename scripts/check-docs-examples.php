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
  if (!$file->isFile() || $file->getExtension() !== 'md') {
    continue;
  }
  $body = file_get_contents((string)$file);

  if ($body === false) {
    continue;
  }

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

fwrite(STDERR, 'check-docs-examples: FAIL — ' . count($errors) . " missing example(s)\n");

foreach ($errors as $e) {
  fwrite(STDERR, "  {$e}\n");
}

exit(1);
