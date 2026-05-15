<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class CheckDocsExamplesTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/checkexamples-' . uniqid();
    mkdir($this->tmp . '/docs/guide/en/tutorial', recursive: true);
    mkdir($this->tmp . '/examples', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesWhenAllExampleFilesExist(): void
  {
    touch($this->tmp . '/examples/echo_bot.php');
    touch($this->tmp . '/examples/webhook_bot.php');
    file_put_contents(
      $this->tmp . '/docs/guide/en/tutorial/02-first-bot.md',
      "See [echo](https://github.com/Gruven/phpbotgram/blob/master/examples/echo_bot.php).\n"
      . "Or [webhook](examples/webhook_bot.php).\n",
    );

    self::assertSame(0, $this->runScript());
  }

  public function testFailsWhenExampleMissing(): void
  {
    file_put_contents(
      $this->tmp . '/docs/guide/en/tutorial/02-first-bot.md',
      "See [echo](examples/missing.php).\n",
    );

    self::assertSame(1, $this->runScript());
  }

  private function runScript(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/check-docs-examples.php';
    $cmd = sprintf(
      'PHPBOTGRAM_DOCS_ROOT=%s PHPBOTGRAM_EXAMPLES_ROOT=%s php %s 2>&1',
      escapeshellarg($this->tmp . '/docs/guide/en'),
      escapeshellarg($this->tmp . '/examples'),
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
