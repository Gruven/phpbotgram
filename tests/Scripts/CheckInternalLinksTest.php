<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CheckInternalLinksTest extends TestCase
{
  private string $tmp;
  private string $apiRoot;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/checkinternal-' . uniqid();
    $this->apiRoot = $this->tmp . '/build/docs/api';
    mkdir($this->apiRoot . '/guide/concepts', recursive: true);
    mkdir($this->apiRoot . '/guide/how-to', recursive: true);
    mkdir($this->apiRoot . '/classes', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesOnValidLinks(): void
  {
    file_put_contents($this->apiRoot . '/classes/Foo.html', '<html><body><span id="method_bar"></span></body></html>');
    file_put_contents($this->apiRoot . '/guide/how-to/other.html', '<html></html>');
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><head><base href="../../"></head><body>'
      . '<a href="guide/how-to/other.html">cross</a>'
      . '<a href="classes/Foo.html#method_bar">api</a>'
      . '<a href="#section1">frag</a>'
      . '<span id="section1"></span>'
      . '</body></html>',
    );

    self::assertSame(0, $this->runScript());
  }

  public function testFailsOnMissingFile(): void
  {
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><head><base href="../../"></head><body><a href="guide/missing.html">x</a></body></html>',
    );

    self::assertSame(1, $this->runScript());
  }

  public function testFailsOnMissingFragmentAnchor(): void
  {
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><body><a href="#nowhere">x</a></body></html>',
    );

    self::assertSame(1, $this->runScript());
  }

  public function testIgnoresExternalAndMailto(): void
  {
    file_put_contents(
      $this->apiRoot . '/guide/concepts/x.html',
      '<html><body>'
      . '<a href="https://example.com/x">ext</a>'
      . '<a href="mailto:foo@bar">mail</a>'
      . '</body></html>',
    );

    self::assertSame(0, $this->runScript());
  }

  public function testResolvesShallowBaseHref(): void
  {
    file_put_contents($this->apiRoot . '/classes/Foo.html', '<html><body></body></html>');
    file_put_contents(
      $this->apiRoot . '/guide/index.html',
      '<html><head><base href="../"></head><body>'
      . '<a href="classes/Foo.html">api</a>'
      . '</body></html>',
    );

    self::assertSame(0, $this->runScript());
  }

  public function testFailsOnMissingRootLandingLink(): void
  {
    file_put_contents(
      $this->apiRoot . '/index.html',
      '<html><head><base href="./"></head><body><a href="guide/missing.html">missing</a></body></html>',
    );

    self::assertSame(1, $this->runScript());
  }

  private function runScript(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-internal-links.php';
    $cmd = sprintf(
      'PHPBOTGRAM_BUILD_ROOT=%s php %s 2>&1',
      escapeshellarg($this->apiRoot),
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
