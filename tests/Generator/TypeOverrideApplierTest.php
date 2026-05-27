<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeEntity;
use Gruven\PhpBotGram\Generator\TypeOverrideApplier;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\TypeOverrideApplier
 */
final class TypeOverrideApplierTest extends TestCase
{
  private static ?LoadedSchema $rawSchema = null;
  private static ?LoadedSchema $applied = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 2) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    self::$rawSchema = $loader->load();
    self::$applied = new TypeOverrideApplier(self::$rawSchema)->apply();
  }

  public function testApplyReturnsFreshLoadedSchemaInstance(): void
  {
    $raw = $this->raw();
    $applied = $this->applied();

    self::assertNotSame($raw, $applied);
    self::assertInstanceOf(LoadedSchema::class, $applied);
  }

  public function testApiVersionAndReleaseDateAreCarriedThrough(): void
  {
    $raw = $this->raw();
    $applied = $this->applied();

    self::assertSame($raw->apiVersion, $applied->apiVersion);
    self::assertSame($raw->releaseDate, $applied->releaseDate);
  }

  public function testTypeMethodEnumCountsPreserved(): void
  {
    // Match the Task 2.2 reconciliation: 303 / 176 / 34.
    $applied = $this->applied();

    self::assertCount(303, $applied->types);
    self::assertCount(176, $applied->methods);
    self::assertCount(34, $applied->enums);
  }

  public function testChatFullInfoBasesPassThroughUnchanged(): void
  {
    // ChatFullInfo declares `bases: [Chat]` directly in its replace.yml
    // (a non-union inheritance override). Applier must not touch it.
    $type = $this->findAppliedType('ChatFullInfo');

    self::assertSame(['Chat'], $type->bases);
  }

  public function testInlineKeyboardMarkupBasesPassThroughUnchanged(): void
  {
    // Standalone (non-union) MutableTelegramObject lift.
    $type = $this->findAppliedType('InlineKeyboardMarkup');

    self::assertSame(['MutableTelegramObject'], $type->bases);
  }

  public function testReplyKeyboardFamilyBasesPassThroughUnchanged(): void
  {
    // Spot-check the rest of the keyboard family that lifts to MutableTelegramObject
    // via their own replace.yml (no union propagation involved).
    foreach (['ReplyKeyboardMarkup', 'ReplyKeyboardRemove', 'ForceReply'] as $name) {
      $type = $this->findAppliedType($name);
      self::assertSame(['MutableTelegramObject'], $type->bases, "Expected MutableTelegramObject base on {$name}");
    }
  }

  public function testInputMediaUnionParentPropagatesBasesToChildren(): void
  {
    // InputMedia is a union parent declaring `bases: [MutableTelegramObject]` —
    // every subtype enumerated in its description's bullet-list should inherit
    // the parent's bases.
    $parent = $this->findAppliedType('InputMedia');

    self::assertSame(['MutableTelegramObject'], $parent->bases);
    self::assertNotNull($parent->subtypes);
    self::assertNotEmpty($parent->subtypes);

    foreach ($parent->subtypes as $childName) {
      $child = $this->findAppliedType($childName);
      self::assertSame(
        ['MutableTelegramObject'],
        $child->bases,
        "Expected propagated MutableTelegramObject base on InputMedia subtype {$childName}",
      );
    }
  }

  public function testInputMessageContentUnionPropagation(): void
  {
    // 5-child union; none of the children declare own bases — straight propagation.
    $parent = $this->findAppliedType('InputMessageContent');

    self::assertSame(['MutableTelegramObject'], $parent->bases);
    self::assertNotNull($parent->subtypes);

    foreach ($parent->subtypes as $childName) {
      $child = $this->findAppliedType($childName);
      self::assertSame(['MutableTelegramObject'], $child->bases, "Propagation expected for {$childName}");
    }
  }

  public function testInlineQueryResultUnionPropagation(): void
  {
    // The 20-child InlineQueryResult tree must propagate uniformly.
    $parent = $this->findAppliedType('InlineQueryResult');

    self::assertSame(['MutableTelegramObject'], $parent->bases);
    self::assertNotNull($parent->subtypes);
    self::assertNotEmpty($parent->subtypes);

    foreach ($parent->subtypes as $childName) {
      $child = $this->findAppliedType($childName);
      self::assertSame(['MutableTelegramObject'], $child->bases, "Propagation expected for {$childName}");
    }
  }

  public function testMenuButtonAndPassportElementErrorUnionPropagation(): void
  {
    foreach (['MenuButton', 'PassportElementError'] as $parentName) {
      $parent = $this->findAppliedType($parentName);
      self::assertSame(['MutableTelegramObject'], $parent->bases);
      self::assertNotNull($parent->subtypes);

      foreach ($parent->subtypes as $childName) {
        $child = $this->findAppliedType($childName);
        self::assertSame(
          ['MutableTelegramObject'],
          $child->bases,
          "Propagation expected for {$parentName} subtype {$childName}",
        );
      }
    }
  }

  public function testNonUnionTypesWithoutBasesAreUnchanged(): void
  {
    // Message has no `bases:` override and is not part of any union — the
    // applier must leave its `bases` null.
    $message = $this->findAppliedType('Message');
    self::assertNull($message->bases);

    // Update is similarly a plain type.
    $update = $this->findAppliedType('Update');
    self::assertNull($update->bases);
  }

  public function testUnionParentWithoutBasesDoesNotPoisonChildren(): void
  {
    // BackgroundFill is a union parent that has NO `bases:` in its replace.yml,
    // so its children should remain bases-less even after the applier runs.
    $parent = $this->findAppliedType('BackgroundFill');

    self::assertNull($parent->bases);
    self::assertNotNull($parent->subtypes);

    foreach ($parent->subtypes as $childName) {
      $child = $this->findAppliedType($childName);
      self::assertNull($child->bases, "BackgroundFill child {$childName} should retain null bases");
    }
  }

  public function testChildExplicitBasesAreNotOverwrittenByParent(): void
  {
    // Empirically the vendored schema has no Union child that declares its own
    // `bases:` against a parent that also declares one — so build a synthetic
    // LoadedSchema and assert the applier preserves the child's own bases.
    $parent = new TypeEntity(
      name: 'SyntheticUnion',
      description: '',
      annotations: [],
      bases: ['ParentBase'],
      subtypes: ['SyntheticChildA', 'SyntheticChildB'],
      subtypeOf: null,
      discriminator: 'kind',
    );

    $childA = new TypeEntity(
      name: 'SyntheticChildA',
      description: '',
      annotations: [],
      bases: ['ChildOwnBase'],
      subtypeOf: 'SyntheticUnion',
    );

    $childB = new TypeEntity(
      name: 'SyntheticChildB',
      description: '',
      annotations: [],
      bases: null,
      subtypeOf: 'SyntheticUnion',
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$parent, $childA, $childB],
      methods: [],
      enums: [],
    );

    $applied = new TypeOverrideApplier($schema)->apply();
    $appliedTypes = [];

    foreach ($applied->types as $t) {
      $appliedTypes[$t->name] = $t;
    }

    self::assertSame(['ParentBase'], $appliedTypes['SyntheticUnion']->bases);
    self::assertSame(['ChildOwnBase'], $appliedTypes['SyntheticChildA']->bases, 'Child override must win over propagation');
    self::assertSame(['ParentBase'], $appliedTypes['SyntheticChildB']->bases, 'Child without own bases must inherit parent bases');
  }

  public function testApplyIsIdempotent(): void
  {
    // Applying twice (re-feeding the first output to a fresh applier) must
    // produce a structurally identical schema.
    $applied1 = $this->applied();
    $applied2 = new TypeOverrideApplier($applied1)->apply();

    self::assertSame(serialize($applied1), serialize($applied2));
  }

  public function testInputDoesNotMutateInPlace(): void
  {
    // The raw schema's affected child types must still carry null bases
    // (because the applier rebuilds rather than mutating in place).
    // InputMediaPhoto is a known union child whose parent (InputMedia)
    // declares MutableTelegramObject — but the raw entity should have
    // had no bases of its own.
    $raw = $this->raw();
    $rawInputMediaPhoto = null;

    foreach ($raw->types as $t) {
      if ($t->name === 'InputMediaPhoto') {
        $rawInputMediaPhoto = $t;

        break;
      }
    }
    self::assertNotNull($rawInputMediaPhoto);
    self::assertNull($rawInputMediaPhoto->bases, 'Raw schema must not be mutated in place');

    // And the applied version should have inherited the parent's MutableTelegramObject.
    $appliedInputMediaPhoto = $this->findAppliedType('InputMediaPhoto');
    self::assertSame(['MutableTelegramObject'], $appliedInputMediaPhoto->bases);
  }

  public function testMethodsAndEnumsPassThroughUnchanged(): void
  {
    $raw = $this->raw();
    $applied = $this->applied();

    // Deep-equality on methods and enums — the applier does not touch them.
    self::assertEquals($raw->methods, $applied->methods);
    self::assertEquals($raw->enums, $applied->enums);
  }

  public function testAnnotationsAreCarriedThroughVerbatim(): void
  {
    // The applier does not touch annotations (SchemaLoader already populated
    // parsedType). Sample-check a few well-known shape carriers.
    $rawMessage = $this->findRawType('Message');
    $appliedMessage = $this->findAppliedType('Message');

    self::assertEquals($rawMessage->annotations, $appliedMessage->annotations);

    // And a propagation target's annotations are also untouched.
    $rawInputMediaAudio = $this->findRawType('InputMediaAudio');
    $appliedInputMediaAudio = $this->findAppliedType('InputMediaAudio');
    self::assertEquals($rawInputMediaAudio->annotations, $appliedInputMediaAudio->annotations);

    // Subtypes / subtypeOf / discriminator / aliases all carry through.
    self::assertSame($rawInputMediaAudio->subtypeOf, $appliedInputMediaAudio->subtypeOf);
    self::assertSame($rawInputMediaAudio->discriminator, $appliedInputMediaAudio->discriminator);
    self::assertSame($rawInputMediaAudio->aliases, $appliedInputMediaAudio->aliases);
  }

  public function testParsedTypeIsLeftAloneByApplier(): void
  {
    // Message.date has a parsed_type override populated by SchemaLoader; the
    // applier must not touch it.
    $applied = $this->findAppliedType('Message');
    $date = $this->findAnnotation($applied->annotations, 'date');

    self::assertNotNull($date->parsedType);
    self::assertSame('std', $date->parsedType['type']);
    self::assertSame('DateTime', $date->parsedType['name']);
  }

  public function testTypesPreserveSchemaOrder(): void
  {
    $applied = $this->applied();
    // Update is the very first child in schema.json — and the applier must
    // not re-order the list.
    self::assertSame('Update', $applied->types[0]->name);

    // Also check a stable pair of consecutive types from the raw vs applied lists.
    $raw = $this->raw();
    $rawNames = array_map(static fn(TypeEntity $t): string => $t->name, $raw->types);
    $appliedNames = array_map(static fn(TypeEntity $t): string => $t->name, $applied->types);
    self::assertSame($rawNames, $appliedNames);
  }

  private function raw(): LoadedSchema
  {
    $raw = self::$rawSchema;

    if ($raw === null) {
      self::fail('Raw schema not loaded — setUpBeforeClass did not run');
    }

    return $raw;
  }

  private function applied(): LoadedSchema
  {
    $applied = self::$applied;

    if ($applied === null) {
      self::fail('Applied schema not built — setUpBeforeClass did not run');
    }

    return $applied;
  }

  private function findAppliedType(string $name): TypeEntity
  {
    foreach ($this->applied()->types as $t) {
      if ($t->name === $name) {
        return $t;
      }
    }

    self::fail("Type {$name} not found in applied schema");
  }

  private function findRawType(string $name): TypeEntity
  {
    foreach ($this->raw()->types as $t) {
      if ($t->name === $name) {
        return $t;
      }
    }

    self::fail("Type {$name} not found in raw schema");
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
