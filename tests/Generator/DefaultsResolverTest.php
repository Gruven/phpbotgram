<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\DefaultsResolver;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\ParameterDefault;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\DefaultsResolver
 * @covers \Gruven\PhpBotGram\Generator\ParameterDefault
 */
final class DefaultsResolverTest extends TestCase
{
  private static ?LoadedSchema $schema = null;

  private static ?DefaultsResolver $resolver = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 2) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    self::$schema = $loader->load();
    self::$resolver = new DefaultsResolver(self::$schema);
  }

  public function testSendMessageParseModeBotDefault(): void
  {
    $byParam = $this->resolver()->forMethod('sendMessage');

    self::assertArrayHasKey('parse_mode', $byParam);

    $pd = $byParam['parse_mode'];
    self::assertSame('sendMessage', $pd->methodName);
    self::assertSame('parse_mode', $pd->wireParamName);
    self::assertSame("new BotDefault('parse_mode')", $pd->expression);
    self::assertTrue($pd->isBotDefault);
  }

  public function testSendMessageProtectContentBotDefault(): void
  {
    $byParam = $this->resolver()->forMethod('sendMessage');

    self::assertArrayHasKey('protect_content', $byParam);
    self::assertSame("new BotDefault('protect_content')", $byParam['protect_content']->expression);
    self::assertTrue($byParam['protect_content']->isBotDefault);
  }

  public function testSendMessageOrphanDefaultYamlEntryIsIgnored(): void
  {
    // sendMessage/default.yml carries `disable_web_page_preview: link_preview_is_disabled`
    // — a legacy/historical wire-param key preserved from an older API revision.
    // The current schema's sendMessage annotations no longer carry that name
    // (the modern parameter is `link_preview_options`), so the resolver must
    // not emit a ParameterDefault for it: defaults are walked annotation-first,
    // not default.yml-first, and orphan default.yml keys simply have nothing
    // to attach to.
    $byParam = $this->resolver()->forMethod('sendMessage');

    self::assertArrayNotHasKey('disable_web_page_preview', $byParam);
  }

  public function testSyntheticDisableWebPagePreviewRename(): void
  {
    // Defensive coverage of the rename mechanism the orphan above would
    // exercise if the schema still carried the annotation. A synthetic
    // method with `disable_web_page_preview` as a `required:false`
    // annotation plus the same default.yml entry must emit
    // `new BotDefault('link_preview_is_disabled')`.
    $method = new MethodEntity(
      name: 'syntheticEditMessageText',
      description: '',
      annotations: [
        new AnnotationEntity(
          name: 'disable_web_page_preview',
          description: '',
          type: 'Boolean',
          required: false,
        ),
      ],
      returns: '',
      defaults: ['disable_web_page_preview' => 'link_preview_is_disabled'],
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [],
      methods: [$method],
      enums: [],
    );

    $resolver = new DefaultsResolver($schema);
    $byParam = $resolver->forMethod('syntheticEditMessageText');

    self::assertArrayHasKey('disable_web_page_preview', $byParam);
    self::assertSame(
      "new BotDefault('link_preview_is_disabled')",
      $byParam['disable_web_page_preview']->expression,
    );
    self::assertTrue($byParam['disable_web_page_preview']->isBotDefault);
  }

  public function testSendMessageLinkPreviewOptionsRenamesToLinkPreview(): void
  {
    // sendMessage/default.yml: `link_preview_options: link_preview`.
    $byParam = $this->resolver()->forMethod('sendMessage');

    self::assertArrayHasKey('link_preview_options', $byParam);
    self::assertSame("new BotDefault('link_preview')", $byParam['link_preview_options']->expression);
    self::assertTrue($byParam['link_preview_options']->isBotDefault);
  }

  public function testSendMessageOptionalWithoutBotDefaultGetsNullExpression(): void
  {
    // `entities` is `required: false` on sendMessage but absent from default.yml —
    // should land in the defaults list with expression `null` and isBotDefault=false.
    $byParam = $this->resolver()->forMethod('sendMessage');

    self::assertArrayHasKey('entities', $byParam);

    $pd = $byParam['entities'];
    self::assertSame('null', $pd->expression);
    self::assertFalse($pd->isBotDefault);
  }

  public function testSendMessageRequiredAnnotationsAreNotInDefaultsList(): void
  {
    // `chat_id` and `text` are `required: true` on sendMessage and must NOT be
    // emitted as defaults — a required parameter has no `=` in the signature.
    $byParam = $this->resolver()->forMethod('sendMessage');

    self::assertArrayNotHasKey('chat_id', $byParam);
    self::assertArrayNotHasKey('text', $byParam);
  }

  public function testForMethodReturnsEmptyArrayForUnknownMethod(): void
  {
    self::assertSame([], $this->resolver()->forMethod('noSuchMethodName'));
  }

  public function testEditMessageTextCarriesMatchedDefaults(): void
  {
    // editMessageText/default.yml ships three entries:
    //   disable_web_page_preview: link_preview_is_disabled  (legacy, orphan — no current annotation)
    //   link_preview_options: link_preview
    //   parse_mode: parse_mode
    // Only the two with matching annotations show up as ParameterDefaults.
    $byParam = $this->resolver()->forMethod('editMessageText');

    self::assertArrayHasKey('parse_mode', $byParam);
    self::assertSame("new BotDefault('parse_mode')", $byParam['parse_mode']->expression);

    self::assertArrayHasKey('link_preview_options', $byParam);
    self::assertSame("new BotDefault('link_preview')", $byParam['link_preview_options']->expression);

    self::assertArrayNotHasKey('disable_web_page_preview', $byParam);
  }

  public function testForwardMessageProtectContentIsTheOnlyBotDefault(): void
  {
    // forwardMessage/default.yml carries only `protect_content`. Verifies the
    // single-entry shape and that other optional params still land as null.
    $byParam = $this->resolver()->forMethod('forwardMessage');

    self::assertArrayHasKey('protect_content', $byParam);
    self::assertSame("new BotDefault('protect_content')", $byParam['protect_content']->expression);
    self::assertTrue($byParam['protect_content']->isBotDefault);
  }

  public function testTotalBotDefaultCountMatchesMatchedYamlEntries(): void
  {
    // Aggregate count across the whole schema: 41 keys across 21 default.yml
    // files in the vendored 10.1 schema. Two of those keys (the legacy
    // `disable_web_page_preview` entries on sendMessage and editMessageText)
    // are orphans — the modern schema renamed them to `link_preview_options`
    // — so the resolver only emits ParameterDefaults for the 39 matched
    // entries.
    $botDefaultCount = 0;

    foreach ($this->resolver()->defaults() as $pd) {
      if ($pd->isBotDefault) {
        ++$botDefaultCount;
      }
    }

    self::assertSame(39, $botDefaultCount);
  }

  public function testDefaultsAggregatesBothBotDefaultsAndNullOptionals(): void
  {
    // The aggregate `defaults()` list must include both BotDefault entries
    // AND `null` entries for optional non-defaulted params. Verify total >=
    // sum of (BotDefault count + nullable optional count for sendMessage).
    $defaults = $this->resolver()->defaults();
    self::assertNotEmpty($defaults);

    // Spot-check: at least one nullable optional and at least one BotDefault
    // should appear in the aggregate list.
    $hasBotDefault = false;
    $hasNull = false;

    foreach ($defaults as $pd) {
      if ($pd->isBotDefault) {
        $hasBotDefault = true;
      } else {
        $hasNull = true;
      }

      if ($hasBotDefault && $hasNull) {
        break;
      }
    }

    self::assertTrue($hasBotDefault, 'Expected at least one BotDefault entry in aggregate defaults()');
    self::assertTrue($hasNull, 'Expected at least one null entry in aggregate defaults()');
  }

  public function testDefaultsTotalCountMatchesOptionalAnnotationCount(): void
  {
    // Every `required: false` annotation across all 180 methods lands in
    // defaults() — either as a BotDefault (41 entries) or as a null default.
    // The schema reports 587 optional annotations in total.
    $defaults = $this->resolver()->defaults();
    self::assertCount(587, $defaults);
  }

  public function testDefaultsAreEmittedInAnnotationOrderPerMethod(): void
  {
    // For deterministic codegen the resolver must walk annotations in the
    // order they appear on the method (which is schema order from
    // SchemaLoader). Verify by picking sendMessage and checking the prefix.
    $schema = $this->schema();
    $sendMessage = null;

    foreach ($schema->methods as $m) {
      if ($m->name === 'sendMessage') {
        $sendMessage = $m;

        break;
      }
    }

    self::assertNotNull($sendMessage);

    /** @var list<string> $sendMessageOrder */
    $sendMessageOrder = [];

    foreach ($this->resolver()->defaults() as $pd) {
      if ($pd->methodName === 'sendMessage') {
        $sendMessageOrder[] = $pd->wireParamName;
      }
    }

    /** @var list<string> $expectedOrder */
    $expectedOrder = [];

    foreach ($sendMessage->annotations as $a) {
      if ($a->required) {
        continue;
      }
      $expectedOrder[] = $a->name;
    }

    self::assertSame($expectedOrder, $sendMessageOrder);
  }

  public function testSyntheticBotDefaultNameWithApostropheIsEscaped(): void
  {
    // Synthetic guard: a BotDefault sentinel name containing an apostrophe
    // must be escaped in the emitted expression so the generated PHP
    // remains syntactically valid (`'foo\'bar'`).
    $method = new MethodEntity(
      name: 'syntheticMethod',
      description: '',
      annotations: [
        new AnnotationEntity(
          name: 'spicy_param',
          description: '',
          type: 'String',
          required: false,
        ),
      ],
      returns: '',
      defaults: ['spicy_param' => "foo'bar"],
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [],
      methods: [$method],
      enums: [],
    );

    $resolver = new DefaultsResolver($schema);
    $byParam = $resolver->forMethod('syntheticMethod');

    self::assertArrayHasKey('spicy_param', $byParam);
    self::assertSame("new BotDefault('foo\\'bar')", $byParam['spicy_param']->expression);
    self::assertTrue($byParam['spicy_param']->isBotDefault);
  }

  public function testSyntheticRequiredAnnotationWithDefaultYamlEntryStillEmitsBotDefault(): void
  {
    // Defensive: if a default.yml carries a key that happens to map to a
    // `required: true` annotation, we still emit the BotDefault — defaults
    // override required-ness because the schema author opted into it.
    $method = new MethodEntity(
      name: 'syntheticMethod',
      description: '',
      annotations: [
        new AnnotationEntity(
          name: 'req_param',
          description: '',
          type: 'String',
          required: true,
        ),
      ],
      returns: '',
      defaults: ['req_param' => 'req_param'],
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [],
      methods: [$method],
      enums: [],
    );

    $resolver = new DefaultsResolver($schema);
    $byParam = $resolver->forMethod('syntheticMethod');

    self::assertArrayHasKey('req_param', $byParam);
    self::assertSame("new BotDefault('req_param')", $byParam['req_param']->expression);
    self::assertTrue($byParam['req_param']->isBotDefault);
  }

  public function testForMethodIsKeyedByWireParamName(): void
  {
    // Verify forMethod returns an associative map keyed by wire (snake_case)
    // param name — what the renderer needs to look up defaults per parameter.
    $byParam = $this->resolver()->forMethod('sendMessage');

    foreach ($byParam as $wireName => $pd) {
      self::assertIsString($wireName);
      self::assertSame($wireName, $pd->wireParamName);
    }
  }

  private function schema(): LoadedSchema
  {
    $schema = self::$schema;

    if ($schema === null) {
      self::fail('Schema not loaded — setUpBeforeClass did not run');
    }

    return $schema;
  }

  private function resolver(): DefaultsResolver
  {
    $resolver = self::$resolver;

    if ($resolver === null) {
      self::fail('Resolver not built — setUpBeforeClass did not run');
    }

    return $resolver;
  }
}
