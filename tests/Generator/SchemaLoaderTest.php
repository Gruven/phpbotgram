<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\EnumEntity;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeEntity;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\AnnotationEntity
 * @covers \Gruven\PhpBotGram\Generator\EnumEntity
 * @covers \Gruven\PhpBotGram\Generator\LoadedSchema
 * @covers \Gruven\PhpBotGram\Generator\MethodEntity
 * @covers \Gruven\PhpBotGram\Generator\SchemaLoader
 * @covers \Gruven\PhpBotGram\Generator\TypeEntity
 */
final class SchemaLoaderTest extends TestCase
{
  private static ?LoadedSchema $loaded = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 2) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    self::$loaded = $loader->load();
  }

  public function testApiVersionAndReleaseDate(): void
  {
    $loaded = $this->loaded();
    self::assertSame('10.0', $loaded->apiVersion);
    self::assertSame('2026-05-08', $loaded->releaseDate);
  }

  public function testCountsTypesMethodsEnums(): void
  {
    // schema.json itself has 303 children with category=types (KeyboardButtonRequestUser
    // and UserShared exist on the filesystem as legacy/deprecated, but are no longer
    // listed under items[].children[]).
    $loaded = $this->loaded();
    self::assertCount(303, $loaded->types);
    self::assertCount(176, $loaded->methods);
    self::assertCount(34, $loaded->enums);
  }

  public function testMessageTypeWithRequiredMessageIdAnnotation(): void
  {
    $message = $this->findType('Message');

    self::assertSame('Message', $message->name);
    self::assertNotSame('', $message->description);

    $messageId = $this->findAnnotation($message->annotations, 'message_id');
    self::assertTrue($messageId->required);
    self::assertSame('Integer', $messageId->type);
  }

  public function testMessageDateAnnotationParsedTypeFromReplaceYml(): void
  {
    $message = $this->findType('Message');
    $date = $this->findAnnotation($message->annotations, 'date');

    self::assertNotNull($date->parsedType);
    self::assertSame('std', $date->parsedType['type']);
    self::assertSame('DateTime', $date->parsedType['name']);
  }

  public function testSendMessageMethodHasParseModeDefault(): void
  {
    $send = $this->findMethod('sendMessage');

    self::assertSame('sendMessage', $send->name);
    self::assertArrayHasKey('parse_mode', $send->defaults);
    self::assertSame('parse_mode', $send->defaults['parse_mode']);
    // sendMessage/default.yml also carries the link_preview/protect_content/disable_web_page_preview keys.
    self::assertArrayHasKey('protect_content', $send->defaults);
    self::assertSame('protect_content', $send->defaults['protect_content']);
  }

  public function testGetChatMemberCarriesReplaceYmlReturningOverride(): void
  {
    $method = $this->findMethod('getChatMember');

    self::assertNotNull($method->parsedReturning);
    self::assertSame('union', $method->parsedReturning['type']);
    self::assertNotEmpty($method->parsedReturning['items']);
  }

  public function testChatTypeEnum(): void
  {
    $chatType = $this->findEnum('ChatType');

    self::assertSame('ChatType', $chatType->name);
    self::assertNotNull($chatType->parse);
    self::assertSame('Chat', $chatType->parse['entity']);
    self::assertSame('type', $chatType->parse['attribute']);
    self::assertArrayHasKey('SENDER', $chatType->static);
    self::assertSame('sender', $chatType->static['SENDER']);
  }

  public function testBotCommandScopeTypeEnumUsesMultiParse(): void
  {
    $enum = $this->findEnum('BotCommandScopeType');

    self::assertNull($enum->parse);
    self::assertNotNull($enum->multiParse);
    self::assertSame('type', $enum->multiParse['attribute']);
    self::assertContains('BotCommandScopeDefault', $enum->multiParse['entities']);
  }

  public function testUpdateTypeEnumUsesExtract(): void
  {
    $enum = $this->findEnum('UpdateType');

    self::assertNotNull($enum->extract);
    self::assertSame('Update', $enum->extract['entity']);
  }

  public function testUserTypeAliasesYmlIsLoaded(): void
  {
    $user = $this->findType('User');

    self::assertNotEmpty($user->aliases);
    self::assertArrayHasKey('get_profile_photos', $user->aliases);
  }

  public function testTypeWithoutAliasesYmlHasEmptyArray(): void
  {
    $update = $this->findType('Update');
    self::assertSame([], $update->aliases);
  }

  /**
   * Cycle 2 review fix: `.butcher/types/<X>/default.yml` was silently
   * dropped on load — `TypeEntity::$defaults` simply didn't exist. After
   * the fix, every type carrying a `default.yml` surfaces the
   * wire_field => bot_default_sentinel map on the loaded entity for the
   * renderer to consume.
   */
  public function testLinkPreviewOptionsDefaultsAreLoaded(): void
  {
    $type = $this->findType('LinkPreviewOptions');

    self::assertSame([
      'is_disabled' => 'link_preview_is_disabled',
      'prefer_small_media' => 'link_preview_prefer_small_media',
      'prefer_large_media' => 'link_preview_prefer_large_media',
      'show_above_text' => 'link_preview_show_above_text',
    ], $type->defaults);
  }

  public function testReplyParametersDefaultsAreLoaded(): void
  {
    $type = $this->findType('ReplyParameters');

    self::assertSame([
      'quote_parse_mode' => 'parse_mode',
      'allow_sending_without_reply' => 'allow_sending_without_reply',
    ], $type->defaults);
  }

  public function testTypeWithoutDefaultYmlHasEmptyDefaults(): void
  {
    $update = $this->findType('Update');
    self::assertSame([], $update->defaults);
  }

  public function testChatFullInfoBasesIsReadFromReplaceYml(): void
  {
    $chat = $this->findType('ChatFullInfo');

    self::assertSame(['Chat'], $chat->bases);
  }

  public function testInlineKeyboardMarkupBasesMutableLift(): void
  {
    $type = $this->findType('InlineKeyboardMarkup');

    self::assertSame(['MutableTelegramObject'], $type->bases);
  }

  public function testTypeWithoutBasesOverrideHasNullBases(): void
  {
    $message = $this->findType('Message');
    self::assertNull($message->bases);
  }

  public function testBackgroundFillIsUnionParentWithSubtypes(): void
  {
    $bg = $this->findType('BackgroundFill');

    self::assertNotNull($bg->subtypes);
    self::assertContains('BackgroundFillSolid', $bg->subtypes);
    self::assertContains('BackgroundFillGradient', $bg->subtypes);
    self::assertContains('BackgroundFillFreeformGradient', $bg->subtypes);
    self::assertSame('type', $bg->discriminator);
    self::assertNull($bg->subtypeOf);
  }

  public function testBackgroundFillSolidIsUnionChild(): void
  {
    $solid = $this->findType('BackgroundFillSolid');

    self::assertSame('BackgroundFill', $solid->subtypeOf);
    self::assertNull($solid->subtypes);
    self::assertNull($solid->discriminator);
  }

  public function testTypesPreserveSchemaOrder(): void
  {
    // Update is the very first child in items[0].children[0] of schema.json.
    self::assertSame('Update', $this->loaded()->types[0]->name);
  }

  public function testGetMeMethodHasNoDefaults(): void
  {
    $method = $this->findMethod('getMe');

    self::assertSame([], $method->defaults);
    self::assertNull($method->parsedReturning);
  }

  public function testMessageDateAnnotationStillCarriesWireType(): void
  {
    // The wire-level "type" remains untouched even when parsed_type overrides it —
    // downstream TypeResolver consults both fields.
    $message = $this->findType('Message');
    $date = $this->findAnnotation($message->annotations, 'date');
    self::assertSame('Integer', $date->type);
  }

  public function testSendMessageChatIdAnnotationIsRequired(): void
  {
    $send = $this->findMethod('sendMessage');
    $chatId = $this->findAnnotation($send->annotations, 'chat_id');

    self::assertTrue($chatId->required);
    self::assertSame('Integer or String', $chatId->type);
  }

  private function loaded(): LoadedSchema
  {
    $loaded = self::$loaded;

    if ($loaded === null) {
      self::fail('Schema not loaded — setUpBeforeClass did not run');
    }

    return $loaded;
  }

  private function findType(string $name): TypeEntity
  {
    foreach ($this->loaded()->types as $t) {
      if ($t->name === $name) {
        return $t;
      }
    }

    self::fail("Type {$name} not found");
  }

  private function findMethod(string $name): MethodEntity
  {
    foreach ($this->loaded()->methods as $m) {
      if ($m->name === $name) {
        return $m;
      }
    }

    self::fail("Method {$name} not found");
  }

  private function findEnum(string $name): EnumEntity
  {
    foreach ($this->loaded()->enums as $e) {
      if ($e->name === $name) {
        return $e;
      }
    }

    self::fail("Enum {$name} not found");
  }

  /**
   * @param list<AnnotationEntity> $annotations
   */
  private function findAnnotation(array $annotations, string $name): AnnotationEntity
  {
    foreach ($annotations as $a) {
      if ($a->name === $name) {
        return $a;
      }
    }

    self::fail("Annotation {$name} not found");
  }
}
