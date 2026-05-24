<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CopyRootDocsTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/copyrootdocs-' . uniqid();
    mkdir($this->tmp . '/source', recursive: true);
    mkdir($this->tmp . '/target', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testCopiesSourceFileWithBanner(): void
  {
    file_put_contents($this->tmp . '/source/CHANGELOG.md', "# CL\nbody\n");
    touch($this->tmp . '/source/CHANGELOG.md', 1_700_000_000);

    $this->runScript([$this->tmp . '/source/CHANGELOG.md', $this->tmp . '/target/changelog.md']);

    $copy = file_get_contents($this->tmp . '/target/changelog.md');
    self::assertStringContainsString('AUTO-GENERATED', $copy);
    self::assertStringContainsString('source: ' . $this->tmp . '/source/CHANGELOG.md', $copy);
    self::assertStringContainsString("# CL\nbody\n", $copy);
    self::assertSame(1_700_000_000, filemtime($this->tmp . '/target/changelog.md'));
  }

  public function testExitsNonZeroWhenSourceMissing(): void
  {
    $rc = $this->runScriptExpectingFailure([$this->tmp . '/source/missing.md', $this->tmp . '/target/x.md']);
    self::assertSame(1, $rc);
  }

  public function testExitsNonZeroWhenTargetIsDirectory(): void
  {
    file_put_contents($this->tmp . '/source/CHANGELOG.md', "body\n");
    mkdir($this->tmp . '/target/changelog.md');

    $rc = $this->runScriptExpectingFailure([$this->tmp . '/source/CHANGELOG.md', $this->tmp . '/target/changelog.md']);
    self::assertSame(1, $rc);
  }

  /** @param list<string> $args */
  private function runScript(array $args): void
  {
    $script = dirname(__DIR__, 2) . '/scripts/copy-root-docs.php';
    $cmd = sprintf('php %s %s 2>&1', escapeshellarg($script), implode(' ', array_map(escapeshellarg(...), $args)));
    exec($cmd, $output, $rc);
    self::assertSame(0, $rc, 'Script failed: ' . implode("\n", $output));
  }

  /** @param list<string> $args */
  private function runScriptExpectingFailure(array $args): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/copy-root-docs.php';
    $cmd = sprintf('php %s %s 2>&1', escapeshellarg($script), implode(' ', array_map(escapeshellarg(...), $args)));
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) {
      return;
    }

    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') {
        continue;
      }
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
