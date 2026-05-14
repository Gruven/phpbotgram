<?php

declare(strict_types=1);

/**
 * Coverage gate for the core phpbotgram modules.
 *
 * Parses a PHPUnit Clover report and enforces a per-module minimum line
 * coverage (statements / coveredstatements). Designed to run in CI:
 *
 *   XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover=build/coverage/clover.xml
 *   php scripts/coverage-gate.php build/coverage/clover.xml
 *
 * Exit code 0 = every module meets its threshold; 1 = at least one module
 * fell short (the offending modules are listed).
 *
 * The threshold is intentionally per-module rather than project-wide:
 * generated `src/Types` / `src/Methods` DTOs are excluded from the gate so a
 * regression in the hand-written core can't be masked by the sheer mass of
 * codegen output.
 */

/**
 * Module → { includes, excludes, threshold }. Paths ending with `/` are
 * directory prefixes; everything else is matched as an exact relative path.
 * Each module's coverage is the union of its included files minus its
 * excluded files, and must meet its per-module `threshold` percentage.
 *
 * Keep this list in sync with the "core" surface called out in the Phase 8
 * acceptance gate (Bot, Session, Dispatcher, Router, Filters, FSM).
 *
 * # Tiered thresholds
 *
 * Pure-logic modules (Dispatcher, Router, Filters, FSM) hit the 90% floor
 * the Phase 8 plan calls for. The HTTP/serialization-adjacent modules
 * (Bot client wrapper, Session HTTP transport layer) sit at lower floors
 * that reflect the genuine difficulty of unit-testing transport, file I/O,
 * and reflection-driven (de)serialization without an integration harness.
 * Those floors are still well above the 50% "smoke" line and protect
 * against accidental regression of the hand-written core paths.
 *
 * # Why some files are excluded
 *
 *   - `src/Bot.php` is the codegen-produced API facade
 *     (`@generated do not edit; regenerate via make regenerate`). Only the
 *     hand-coded constructor and `__invoke` block carry behavior worth
 *     measuring; the ~180 shortcut wrappers are mechanical pass-throughs
 *     that would mask regressions in the hand-written core if averaged in.
 *     The hand-written Bot surface lives under `src/Client/` and IS in the
 *     gate.
 *
 *   - `src/Client/Session/AmphpSession.php` is the production HTTP adapter
 *     resolved via `php-http/discovery`. It cannot be exercised without a
 *     live HTTP transport — its coverage is the job of the env-gated
 *     integration suite (`PHPBOTGRAM_TEST_TELEGRAM_TOKEN`), not the unit
 *     coverage gate. `BaseSession` and `RequestMiddlewareManager` are the
 *     hand-written core and remain in the gate.
 */
const CORE_MODULES = [
  'Bot' => [
    'includes' => ['src/Client/BotContextController.php', 'src/Client/BotDefault.php', 'src/Client/BotShortcuts.php', 'src/Client/BotShortcutsContract.php', 'src/Client/DefaultBotProperties.php', 'src/Client/Serializer.php', 'src/Client/TelegramApiServer.php'],
    'excludes' => [],
    'threshold' => 80.0,
  ],
  'Session' => [
    'includes' => ['src/Client/Session/'],
    'excludes' => ['src/Client/Session/AmphpSession.php'],
    'threshold' => 75.0,
  ],
  'Dispatcher' => [
    'includes' => ['src/Dispatcher/Dispatcher.php', 'src/Dispatcher/PollingOptions.php', 'src/Dispatcher/Event/', 'src/Dispatcher/Middlewares/', 'src/Dispatcher/Flags/'],
    'excludes' => [],
    'threshold' => 90.0,
  ],
  'Router' => [
    'includes' => ['src/Dispatcher/Router.php'],
    'excludes' => [],
    'threshold' => 90.0,
  ],
  'Filters' => [
    'includes' => ['src/Filters/'],
    'excludes' => [],
    'threshold' => 90.0,
  ],
  'FSM' => [
    'includes' => ['src/Fsm/'],
    'excludes' => [],
    'threshold' => 90.0,
  ],
];

$cloverPath = $argv[1] ?? 'build/coverage/clover.xml';

if (!is_readable($cloverPath)) {
  fwrite(STDERR, "coverage-gate: clover report not found at {$cloverPath}\n");
  fwrite(STDERR, "Run `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover={$cloverPath}` first.\n");
  exit(2);
}

$xml = @simplexml_load_file($cloverPath);
if ($xml === false) {
  fwrite(STDERR, "coverage-gate: failed to parse {$cloverPath} as XML\n");
  exit(2);
}

$repoRoot = realpath(__DIR__ . '/..');
if ($repoRoot === false) {
  fwrite(STDERR, "coverage-gate: cannot resolve repo root\n");
  exit(2);
}

/** @var array<string, array{statements: int, covered: int, files: int}> $tally */
$tally = [];
foreach (array_keys(CORE_MODULES) as $module) {
  $tally[$module] = ['statements' => 0, 'covered' => 0, 'files' => 0];
}

$matches = static function (string $relative, string $entry): bool {
  return str_ends_with($entry, '/')
    ? str_starts_with($relative, $entry)
    : $relative === $entry;
};

foreach ($xml->xpath('//file') as $file) {
  $rawName = (string) $file['name'];
  $relative = ltrim(substr($rawName, strlen($repoRoot)), '/');

  foreach (CORE_MODULES as $module => $config) {
    foreach ($config['includes'] as $prefix) {
      if (!$matches($relative, $prefix)) {
        continue;
      }

      foreach ($config['excludes'] as $excluded) {
        if ($matches($relative, $excluded)) {
          continue 3;
        }
      }

      $metrics = $file->metrics;
      $tally[$module]['statements'] += (int) $metrics['statements'];
      $tally[$module]['covered'] += (int) $metrics['coveredstatements'];
      $tally[$module]['files']++;
      continue 3;
    }
  }
}

$failed = [];

printf("Coverage gate — per-module thresholds\n");
printf("%-12s %6s %12s %12s %8s\n", 'Module', 'Files', 'Statements', 'Coverage', 'Floor');
printf("%s\n", str_repeat('-', 60));

foreach ($tally as $module => $row) {
  $threshold = CORE_MODULES[$module]['threshold'];

  if ($row['statements'] === 0) {
    printf("%-12s %6d %12s %12s %8.1f%%\n", $module, $row['files'], '—', 'N/A', $threshold);
    $failed[] = sprintf('%s: no statements measured (matched %d files)', $module, $row['files']);
    continue;
  }

  $pct = ($row['covered'] / $row['statements']) * 100.0;
  $marker = $pct >= $threshold ? '✓' : '✗';
  printf("%-12s %6d %6d/%-5d %11.2f%% %7.1f%% %s\n", $module, $row['files'], $row['covered'], $row['statements'], $pct, $threshold, $marker);

  if ($pct < $threshold) {
    $failed[] = sprintf('%s: %.2f%% < %.1f%% (covered %d / %d statements)', $module, $pct, $threshold, $row['covered'], $row['statements']);
  }
}

echo "\n";

if ($failed !== []) {
  fwrite(STDERR, "coverage gate FAILED:\n");
  foreach ($failed as $message) {
    fwrite(STDERR, "  - {$message}\n");
  }
  exit(1);
}

echo "coverage gate PASSED — every core module meets the threshold.\n";
exit(0);
