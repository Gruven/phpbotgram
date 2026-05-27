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
