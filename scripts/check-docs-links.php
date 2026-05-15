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

  if (preg_match_all('~href="' . preg_quote(SENTINEL_PREFIX, '~') . '([^"#]+)(#[^"]+)?"~', $body, $m, PREG_SET_ORDER)) {
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
