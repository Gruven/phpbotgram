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
