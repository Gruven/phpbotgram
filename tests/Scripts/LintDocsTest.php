<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class LintDocsTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/lintdocs-' . uniqid();
    mkdir($this->tmp . '/docs/guide/en', recursive: true);
  }

  protected function tearDown(): void
  {
    $this->rrmdir($this->tmp);
  }

  public function testPassesOnValidPhpBlock(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\n\$a = 1;\necho \$a;\n```\n");
    self::assertSame(0, $this->runScript());
  }

  public function testFailsOnInvalidPhpBlock(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\nfunction broken( {\n```\n");
    self::assertSame(1, $this->runScript());
  }

  public function testValidBlockWithExplicitOpener(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\n<?php\n\$a = 1;\n```\n");
    self::assertSame(0, $this->runScript());
  }

  public function testValidBlockStartingWithDeclare(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php\ndeclare(strict_types=1);\n\$a = 1;\n```\n");
    self::assertSame(0, $this->runScript());
  }

  public function testPhpFragmentBlockSkipped(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```php-fragment\npublic function foo(): void {}\n```\n");
    self::assertSame(0, $this->runScript());
  }

  public function testFailsOnInlineAnchorTag(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\nUse <a href=\"foo\">link</a>\n");
    self::assertSame(1, $this->runScript());
  }

  public function testPassesWithInlineAnchorInBacktickSpan(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\nThe `<a>` element is stripped.\n");
    self::assertSame(0, $this->runScript());
  }

  public function testFencedBlockHidesInlineHtml(): void
  {
    file_put_contents($this->tmp . '/docs/guide/en/x.md', "# X\n\n```html\n<a href=\"foo\">in fence</a>\n```\n");
    self::assertSame(0, $this->runScript());
  }

  private function runScript(): int
  {
    $script = dirname(__DIR__, 2) . '/scripts/lint-docs.php';
    $cmd = sprintf(
      'PHPBOTGRAM_DOCS_ROOT=%s php %s 2>&1',
      escapeshellarg($this->tmp . '/docs/guide/en'),
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
