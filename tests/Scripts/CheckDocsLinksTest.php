<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CheckDocsLinksTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/checkdocslinks-' . uniqid();
    mkdir($this->tmp . '/build/docs/api/guide/concepts', recursive: true);
    mkdir($this->tmp . '/build/docs/api/classes', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesWhenSentinelTargetExists(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/classes/Foo-Bar.html',
      '<html><body><h2 id="method_baz">baz</h2></body></html>',
    );
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Foo-Bar.html#method_baz">x</a></body></html>',
    );

    self::assertSame(0, $this->runScript());
  }

  public function testFailsWhenSentinelTargetFileMissing(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Missing.html">x</a></body></html>',
    );

    self::assertSame(1, $this->runScript());
  }

  public function testFailsWhenAnchorMissing(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/classes/Foo-Bar.html',
      '<html><body><h2 id="method_other">other</h2></body></html>',
    );
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Foo-Bar.html#method_baz">x</a></body></html>',
    );

    self::assertSame(1, $this->runScript());
  }

  public function testIgnoresNonSentinelLinks(): void
  {
    file_put_contents(
      $this->tmp . '/build/docs/api/guide/concepts/x.html',
      '<html><body><a href="https://example.com/x">external</a><a href="#anchor">frag</a></body></html>',
    );

    self::assertSame(0, $this->runScript());
  }

  private function runScript(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-docs-links.php';
    $cmd = sprintf(
      'PHPBOTGRAM_BUILD_ROOT=%s php %s 2>&1',
      escapeshellarg($this->tmp . '/build/docs/api'),
      escapeshellarg($script),
    );
    exec($cmd, $output, $rc);

    return $rc;
  }

  private function rrmdir(string $dir): void
  {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $e) {
      if ($e === '.' || $e === '..') continue;
      $p = $dir . '/' . $e;
      is_dir($p) && !is_link($p) ? $this->rrmdir($p) : unlink($p);
    }
    rmdir($dir);
  }
}
