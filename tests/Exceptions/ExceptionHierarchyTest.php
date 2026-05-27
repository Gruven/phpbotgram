<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Exceptions;

use Gruven\PhpBotGram\Exceptions\PhpBotGramException;
use Gruven\PhpBotGram\Exceptions\TelegramApiException;
use Gruven\PhpBotGram\Exceptions\TelegramBadRequestException;
use Gruven\PhpBotGram\Exceptions\TelegramMigrateToChat;
use Gruven\PhpBotGram\Exceptions\TelegramRetryAfter;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
final class ExceptionHierarchyTest extends TestCase
{
  public function testApiInheritsFromBase(): void
  {
    $e = new TelegramApiException($this->anonymousMethod(), 'test');
    self::assertInstanceOf(PhpBotGramException::class, $e);
  }

  public function testRetryAfterCarriesPayload(): void
  {
    $method = $this->anonymousMethod();
    $e = new TelegramRetryAfter($method, 'Flood control', retryAfter: 30);
    self::assertSame(30, $e->retryAfter);
    self::assertSame($method, $e->method);
  }

  public function testMigrateToChatPayload(): void
  {
    $method = $this->anonymousMethod();
    $e = new TelegramMigrateToChat($method, 'Migrated', migrateToChatId: -100123);
    self::assertSame(-100123, $e->migrateToChatId);
  }

  public function testBadRequestInheritsFromApiException(): void
  {
    $e = new TelegramBadRequestException($this->anonymousMethod(), 'test');
    self::assertInstanceOf(TelegramApiException::class, $e);
  }

  public function testRetryAfterAppendsDocsUrlOnStringify(): void
  {
    $e = new TelegramRetryAfter($this->anonymousMethod(), 'Flood control', retryAfter: 5);
    $rendered = (string)$e;
    self::assertStringContainsString('Telegram server says', $rendered);
    self::assertStringContainsString('Flood control exceeded', $rendered);
    self::assertStringContainsString('(background on this error at: https://core.telegram.org', $rendered);
    self::assertStringNotContainsString('background on this error', $e->getMessage());
  }

  /**
   * @return TelegramMethod<mixed>
   */
  private function anonymousMethod(): TelegramMethod
  {
    // @extends TelegramMethod<mixed>
    return new class extends TelegramMethod {
      public const string ApiMethod = 'x';
      public const string ReturnsType = stdClass::class;
    };
  }
}
