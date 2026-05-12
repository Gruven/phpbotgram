<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Tests\Support\RunAsyncTrait;
use Gruven\PhpBotGram\Types\BufferedInputFile;
use Gruven\PhpBotGram\Types\FsInputFile;
use PHPUnit\Framework\TestCase;

final class InputFileTest extends TestCase
{
  use RunAsyncTrait;

  public function testBufferedReadsBytes(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $file = new BufferedInputFile('hello world', 'greeting.txt');
    $buf = $this->runAsync(static function () use ($file, $bot): string {
      $stream = $file->read($bot);
      $out = '';

      while (($chunk = $stream->read()) !== null) {
        $out .= $chunk;
      }

      return $out;
    });
    self::assertSame('hello world', $buf);
  }

  public function testFsReadsFromDisk(): void
  {
    $bot = new Bot(token: '1:test', session: new MockedSession());
    $tmp = (string)tempnam(sys_get_temp_dir(), 'phpbg');
    file_put_contents($tmp, 'on disk');

    try {
      $file = new FsInputFile($tmp);
      $buf = $this->runAsync(static function () use ($file, $bot): string {
        $stream = $file->read($bot);
        $out = '';

        while (($chunk = $stream->read()) !== null) {
          $out .= $chunk;
        }

        return $out;
      });
      self::assertSame('on disk', $buf);
    } finally {
      unlink($tmp);
    }
  }
}
