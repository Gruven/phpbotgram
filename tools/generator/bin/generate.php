#!/usr/bin/env php
<?php

declare(strict_types=1);

use Gruven\PhpBotGram\Generator\Pipeline;

$autoload = __DIR__ . '/../../../vendor/autoload.php';

if (!file_exists($autoload)) {
  fwrite(STDERR, "vendor/autoload.php not found; run composer install first.\n");

  exit(1);
}

require $autoload;

$opts = getopt('', ['schema:', 'out:']);
$repoRoot = realpath(__DIR__ . '/../../../');

if ($repoRoot === false) {
  fwrite(STDERR, "Failed to resolve repository root.\n");

  exit(1);
}

$schemaDir = is_string($opts['schema'] ?? null) ? $opts['schema'] : $repoRoot . '/.butcher';
$outDir = is_string($opts['out'] ?? null) ? $opts['out'] : $repoRoot . '/src';

$pipeline = new Pipeline($schemaDir, $outDir, repoRoot: $repoRoot);
$manifest = $pipeline->run();

printf(
  "Generator complete:\n  written: %d\n  skipped: %d\n",
  count($manifest['written']),
  count($manifest['skipped']),
);

exit(0);
