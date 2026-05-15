<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class RewriteApiLinksTest extends TestCase
{
  private string $tmp;
  private string $guideRoot;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/rewriteapi-' . uniqid();
    $this->guideRoot = $this->tmp . '/build/docs/api/guide';
    mkdir($this->guideRoot, recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testRewritesSentinelHref(): void
  {
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><a href="https://api.phpbotgram.local/Foo-Bar.html#method_baz">label</a></body></html>',
    );
    self::assertSame(0, $this->runScript());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringContainsString('href="classes/Foo-Bar.html#method_baz"', $after);
    self::assertStringNotContainsString('https://api.phpbotgram.local/', $after);
  }

  public function testPreservesSentinelInCodeBlock(): void
  {
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><pre><code>[X](https://api.phpbotgram.local/Foo.html)</code></pre></body></html>',
    );
    self::assertSame(0, $this->runScript());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringContainsString('[X](https://api.phpbotgram.local/Foo.html)', $after);
  }

  public function testPreservesSentinelInImgAlt(): void
  {
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><img alt="see https://api.phpbotgram.local/Foo.html" src="diagram.svg"></body></html>',
    );
    self::assertSame(0, $this->runScript());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringContainsString('alt="see https://api.phpbotgram.local/Foo.html"', $after);
  }

  public function testFailsOnLeftoverSentinelInPageBody(): void
  {
    file_put_contents(
      $this->guideRoot . '/x.html',
      '<html><body><p>Visit https://api.phpbotgram.local/Foo.html</p></body></html>',
    );
    self::assertSame(1, $this->runScript());
  }

  public function testPreservesHtml5Doctype(): void
  {
    $original = "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"UTF-8\"><title>x</title></head><body><a href=\"https://api.phpbotgram.local/Foo.html\">x</a></body></html>";
    file_put_contents($this->guideRoot . '/x.html', $original);
    self::assertSame(0, $this->runScript());

    $after = file_get_contents($this->guideRoot . '/x.html');
    self::assertStringStartsWith('<!DOCTYPE html>', ltrim($after));
    self::assertStringNotContainsString('-//W3C//DTD HTML 4.01//EN', $after);
    self::assertStringContainsString('href="classes/Foo.html"', $after);
  }

  private function runScript(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/rewrite-api-links.php';
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
