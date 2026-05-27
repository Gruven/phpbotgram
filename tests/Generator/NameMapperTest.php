<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\NameMapper
 */
final class NameMapperTest extends TestCase
{
  private static ?LoadedSchema $loaded = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 2) . '/.butcher';
    self::$loaded = new SchemaLoader($schemaDir)->load();
  }

  public function testPropertyConvertsChatIdToCamelCase(): void
  {
    self::assertSame('chatId', new NameMapper()->property('chat_id'));
  }

  public function testPropertyConvertsMessageIdToCamelCase(): void
  {
    self::assertSame('messageId', new NameMapper()->property('message_id'));
  }

  public function testPropertyConvertsFirstNameToCamelCase(): void
  {
    self::assertSame('firstName', new NameMapper()->property('first_name'));
  }

  public function testPropertyConvertsIsBotToCamelCase(): void
  {
    self::assertSame('isBot', new NameMapper()->property('is_bot'));
  }

  public function testPropertyRenamesFromToFromUser(): void
  {
    // Canonical aiogram-pinned rename: `from` is a PHP reserved keyword.
    self::assertSame('fromUser', new NameMapper()->property('from'));
  }

  public function testPropertyConvertsUserIdToCamelCase(): void
  {
    self::assertSame('userId', new NameMapper()->property('user_id'));
  }

  public function testPropertyConvertsLanguageCodeToCamelCase(): void
  {
    self::assertSame('languageCode', new NameMapper()->property('language_code'));
  }

  public function testPropertySingleWordPassesThrough(): void
  {
    self::assertSame('text', new NameMapper()->property('text'));
  }

  public function testPropertyAlreadyCamelCasePassesThrough(): void
  {
    // Defensive: nested-Union member field names sometimes arrive in camelCase form.
    self::assertSame('inlineKeyboard', new NameMapper()->property('inlineKeyboard'));
  }

  public function testPropertyStripsTrailingUnderscore(): void
  {
    // Wire names with trailing underscores are rare-but-legal in the JSON; strip
    // them before transformation so the resulting PHP identifier is clean.
    self::assertSame('foo', new NameMapper()->property('foo_'));
  }

  public function testPropertyEmptyStringThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);
    new NameMapper()->property('');
  }

  public function testMethodPassesThroughSendMessage(): void
  {
    self::assertSame('sendMessage', new NameMapper()->method('sendMessage'));
  }

  public function testMethodPassesThroughGetMe(): void
  {
    self::assertSame('getMe', new NameMapper()->method('getMe'));
  }

  public function testMethodPassesThroughGetMyShortDescription(): void
  {
    self::assertSame('getMyShortDescription', new NameMapper()->method('getMyShortDescription'));
  }

  public function testTypePassesThroughMessage(): void
  {
    self::assertSame('Message', new NameMapper()->type('Message'));
  }

  public function testTypePassesThroughChatPermissions(): void
  {
    self::assertSame('ChatPermissions', new NameMapper()->type('ChatPermissions'));
  }

  public function testTypePassesThroughBackgroundFill(): void
  {
    self::assertSame('BackgroundFill', new NameMapper()->type('BackgroundFill'));
  }

  public function testWirePropertyNameConvertsChatIdToSnakeCase(): void
  {
    self::assertSame('chat_id', new NameMapper()->wirePropertyName('chatId'));
  }

  public function testWirePropertyNameInvertsFromUserRename(): void
  {
    // Reverse the canonical rename: PHP `fromUser` -> wire `from`.
    self::assertSame('from', new NameMapper()->wirePropertyName('fromUser'));
  }

  public function testWirePropertyNameConvertsMessageIdToSnakeCase(): void
  {
    self::assertSame('message_id', new NameMapper()->wirePropertyName('messageId'));
  }

  public function testPropertyReservedKeywordWithoutRenameThrows(): void
  {
    // `list` is a PHP reserved keyword. The schema doesn't actually contain a
    // property named `list` today, but the mapper must fail closed rather than
    // silently emit broken PHP.
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage("Reserved keyword 'list' has no rename defined");
    new NameMapper()->property('list');
  }

  public function testPropertyPreservesNumericSegmentsInPhotoUrl(): void
  {
    self::assertSame('photoUrl', new NameMapper()->property('photo_url'));
  }

  public function testPropertyPreservesNumericSegmentsInThumbUrl64(): void
  {
    // Numbers stay attached to the segment they appear in.
    self::assertSame('thumbUrl64', new NameMapper()->property('thumb_url_64'));
  }

  public function testPropertyHandlesMpeg4FileId(): void
  {
    // Real wire name with digits inside the first segment.
    self::assertSame('mpeg4FileId', new NameMapper()->property('mpeg4_file_id'));
  }

  public function testWirePropertyNameHandlesNumericSuffix(): void
  {
    self::assertSame('street_line1', new NameMapper()->wirePropertyName('streetLine1'));
  }

  public function testEveryWireAnnotationMapsWithoutThrowing(): void
  {
    // Sanity sweep: every annotation across every type and method must round-trip
    // through `property()` without triggering the fail-closed branch.
    $mapper = new NameMapper();
    $loaded = self::$loaded;

    if ($loaded === null) {
      self::fail('Schema not loaded — setUpBeforeClass did not run');
    }

    $count = 0;

    foreach ($loaded->types as $type) {
      foreach ($type->annotations as $annotation) {
        $mapper->property($annotation->name);
        ++$count;
      }
    }

    foreach ($loaded->methods as $method) {
      foreach ($method->annotations as $annotation) {
        $mapper->property($annotation->name);
        ++$count;
      }
    }

    self::assertGreaterThan(0, $count);
  }
}
