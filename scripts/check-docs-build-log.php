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

// Patterns pinned via pilot pass (docs/superpowers/notes/2026-05-15-phase-10-pilot.md).
// Image reference not found stays on allow-list because shared-asset cp runs
// AFTER phpdoc in build-docs.sh (phpdoc rewrites image paths depth-adaptively
// to guide/shared/<name>, so cp must follow phpdoc — see pilot notes).
// "does not have an alternative text" is a pilot-discovered accessibility
// gate not in the spec's default list.
const GATE_PATTERNS = [
  'could not be resolved',
  'Document with name',
  'No parent found for file',
  'Document has no title',
  'Could not find an index file', // belt-and-braces; usually phpdoc exits non-zero
  'does not have an alternative text', // pilot-discovered accessibility gate
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

fwrite(STDERR, 'check-docs-build-log: FAIL — ' . count($failures) . " gate pattern matches in {$path}\n");

foreach ($failures as $f) {
  fwrite(STDERR, sprintf("  line %d (matched '%s'): %s\n", $f['line'], $f['pattern'], $f['text']));
}

exit(1);
