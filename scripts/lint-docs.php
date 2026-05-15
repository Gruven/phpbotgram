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

    // Tolerate up to 3 leading spaces (CommonMark's "indented fence"
    // rule). Recipes nested inside list items legitimately produce
    // fences like `    ` ` ``` ` `; with the regex anchored at column 0,
    // the closing fence would never match and lint would never exit
    // $inside_fence, mis-attributing every later inline-HTML hit.
    if (preg_match('/^ {0,3}```\s*(\S*)/', $line, $m)) {
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
