<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CheckDocsBuildLogTest extends TestCase
{
  public function testPassesOnCleanLog(): void
  {
    $log = $this->makeTempLog("0/23 [===]   0%\nAll done in 12 seconds!\n");
    self::assertSame(0, $this->runScript($log));
  }

  public function testFailsOnUnresolvedReference(): void
  {
    $log = $this->makeTempLog("0/23 [===]   0%\n Reference other.md#section could not be resolved in index\nAll done.\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnNoParent(): void
  {
    $log = $this->makeTempLog("No parent found for file \"orphan/page\" attaching it to the document root instead.\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnMissingTitle(): void
  {
    $log = $this->makeTempLog("Document has no title for orphan/page\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnMissingDocument(): void
  {
    $log = $this->makeTempLog("Document with name 'foo' not found\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnMissingIndexFile(): void
  {
    $log = $this->makeTempLog("Could not find an index file 'docs/guide/en/orphan/'\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testFailsOnMissingAltText(): void
  {
    // Pilot pass observation: phpdoc emits this during parsing for any
    // ![](...) with empty alt text. Treated as a gate (accessibility check).
    $log = $this->makeTempLog("does not have an alternative text. Add an alternative text like this: ![](image.png)\n");
    self::assertSame(1, $this->runScript($log));
  }

  public function testIgnoresAllowedSubstrings(): void
  {
    $log = $this->makeTempLog("Image reference not found '../shared/foo.svg'\nAll done.\n");
    self::assertSame(0, $this->runScript($log));
  }

  public function testFailsWhenLogFileMissing(): void
  {
    self::assertSame(2, $this->runScript('/tmp/nonexistent-log-' . uniqid()));
  }

  private function makeTempLog(string $content): string
  {
    $path = tempnam(sys_get_temp_dir(), 'buildlog');
    file_put_contents($path, $content);

    return $path;
  }

  private function runScript(string $logPath): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-docs-build-log.php';
    $cmd = sprintf('php %s %s 2>&1', escapeshellarg($script), escapeshellarg($logPath));
    exec($cmd, $output, $rc);

    return $rc;
  }
}
