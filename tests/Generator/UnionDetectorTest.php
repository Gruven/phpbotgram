<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeEntity;
use Gruven\PhpBotGram\Generator\UnionDetector;
use Gruven\PhpBotGram\Generator\UnionMember;
use Gruven\PhpBotGram\Generator\UnionPlan;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\UnionDetector
 * @covers \Gruven\PhpBotGram\Generator\UnionMember
 * @covers \Gruven\PhpBotGram\Generator\UnionPlan
 */
final class UnionDetectorTest extends TestCase
{
  private static ?LoadedSchema $schema = null;

  /** @var null|list<UnionPlan> */
  private static ?array $plans = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 2) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    self::$schema = $loader->load();
    self::$plans = new UnionDetector(self::$schema)->plans();
  }

  public function testPlansCountMatchesDiscriminatorBearingUnionParents(): void
  {
    // Every parent with $subtypes !== null AND $discriminator !== null gets a
    // plan. The two null-discriminator unions in the vendored schema
    // (InputMessageContent, MaybeInaccessibleMessage) are structurally /
    // tag-via-field unions that don't fit the match($payload['x']) shape and
    // are handled elsewhere.
    $expected = 0;
    $schema = $this->schema();

    foreach ($schema->types as $t) {
      if ($t->subtypes !== null && $t->discriminator !== null) {
        ++$expected;
      }
    }

    self::assertSame($expected, \count($this->plans()));
    // Sanity-pin the vendored 10.1 count so a future regression in either the
    // SchemaLoader or the detector surfaces here, not five layers downstream.
    self::assertSame(23, $expected);
  }

  public function testNullDiscriminatorUnionsAreSkipped(): void
  {
    // InputMessageContent (structural) and MaybeInaccessibleMessage (tagged on
    // `date == 0`) both ship with no `discriminator:` in subtypes.yml — they
    // must not appear in the detector's output.
    foreach ($this->plans() as $plan) {
      self::assertNotSame('InputMessageContent', $plan->parentName);
      self::assertNotSame('MaybeInaccessibleMessage', $plan->parentName);
    }
  }

  public function testBackgroundFillPlanMatchesExpectedShape(): void
  {
    $plan = $this->findPlan('BackgroundFill');

    self::assertSame('BackgroundFill', $plan->parentName);
    self::assertSame('type', $plan->discriminator);
    self::assertCount(3, $plan->members);

    self::assertSame('BackgroundFillSolid', $plan->members[0]->childClassName);
    self::assertSame('solid', $plan->members[0]->wireValue);

    self::assertSame('BackgroundFillGradient', $plan->members[1]->childClassName);
    self::assertSame('gradient', $plan->members[1]->wireValue);

    self::assertSame('BackgroundFillFreeformGradient', $plan->members[2]->childClassName);
    self::assertSame('freeform_gradient', $plan->members[2]->wireValue);
  }

  public function testMessageOriginPlanMatchesExpectedShape(): void
  {
    $plan = $this->findPlan('MessageOrigin');

    self::assertSame('type', $plan->discriminator);
    self::assertSame(
      [
        ['MessageOriginUser', 'user'],
        ['MessageOriginHiddenUser', 'hidden_user'],
        ['MessageOriginChat', 'chat'],
        ['MessageOriginChannel', 'channel'],
      ],
      $this->memberPairs($plan),
    );
  }

  public function testChatBoostSourcePlanUsesSourceDiscriminator(): void
  {
    $plan = $this->findPlan('ChatBoostSource');

    // ChatBoostSource discriminates on `source`, not `type`.
    self::assertSame('source', $plan->discriminator);
    self::assertSame(
      [
        ['ChatBoostSourcePremium', 'premium'],
        ['ChatBoostSourceGiftCode', 'gift_code'],
        ['ChatBoostSourceGiveaway', 'giveaway'],
      ],
      $this->memberPairs($plan),
    );
  }

  public function testReactionTypePlanMatchesExpectedShape(): void
  {
    $plan = $this->findPlan('ReactionType');

    self::assertSame('type', $plan->discriminator);
    self::assertSame(
      [
        ['ReactionTypeEmoji', 'emoji'],
        ['ReactionTypeCustomEmoji', 'custom_emoji'],
        ['ReactionTypePaid', 'paid'],
      ],
      $this->memberPairs($plan),
    );
  }

  public function testMenuButtonPlanCoversTheMustBeBranch(): void
  {
    // MenuButton's children all use the `must be X` phrasing (no quotes) —
    // this exercises the second regex branch.
    $plan = $this->findPlan('MenuButton');

    self::assertSame('type', $plan->discriminator);
    self::assertSame(
      [
        ['MenuButtonCommands', 'commands'],
        ['MenuButtonWebApp', 'web_app'],
        ['MenuButtonDefault', 'default'],
      ],
      $this->memberPairs($plan),
    );
  }

  public function testChatMemberPlanUsesStatusDiscriminator(): void
  {
    // ChatMember discriminates on `status`, mapping the ban literal `kicked`
    // (not `banned`) into ChatMemberBanned — important regression-pin.
    $plan = $this->findPlan('ChatMember');

    self::assertSame('status', $plan->discriminator);
    self::assertSame(
      [
        ['ChatMemberOwner', 'creator'],
        ['ChatMemberAdministrator', 'administrator'],
        ['ChatMemberMember', 'member'],
        ['ChatMemberRestricted', 'restricted'],
        ['ChatMemberLeft', 'left'],
        ['ChatMemberBanned', 'kicked'],
      ],
      $this->memberPairs($plan),
    );
  }

  public function testInlineQueryResultPlanIsSchemaOrderPreserved(): void
  {
    // 20-child union — verify ordering matches the parent's subtypes list
    // verbatim (the renderer relies on this for stable `members()` output).
    $plan = $this->findPlan('InlineQueryResult');
    $parent = $this->findType('InlineQueryResult');

    self::assertNotNull($parent->subtypes);
    self::assertCount(\count($parent->subtypes), $plan->members);

    foreach ($parent->subtypes as $index => $subtypeName) {
      self::assertSame(
        $subtypeName,
        $plan->members[$index]->childClassName,
        "Member at index {$index} should preserve schema subtype order",
      );
    }
  }

  public function testEveryDiscoveredPlanPreservesParentSubtypesOrder(): void
  {
    // Generalisation of the InlineQueryResult-specific check: the detector
    // must NEVER re-order members relative to TypeEntity::$subtypes.
    foreach ($this->plans() as $plan) {
      $parent = $this->findType($plan->parentName);

      self::assertNotNull($parent->subtypes);

      $memberNames = array_map(
        static fn(UnionMember $m): string => $m->childClassName,
        $plan->members,
      );
      self::assertSame(
        $parent->subtypes,
        $memberNames,
        "Plan for {$plan->parentName} reordered subtypes",
      );
    }
  }

  public function testEveryDiscoveredPlanHasNonEmptyWireValues(): void
  {
    // Wire-value strings must never be empty — the regex must always consume
    // at least one character. If a child slips through with `''` it would
    // collide with the `default => throw` arm at runtime.
    foreach ($this->plans() as $plan) {
      foreach ($plan->members as $member) {
        self::assertNotSame(
          '',
          $member->wireValue,
          "Empty wire value for {$plan->parentName}::{$member->childClassName}",
        );
      }
    }
  }

  public function testNonUnionTypesAreNotPresentInAnyPlan(): void
  {
    // Plain leaf types (User, Message, Chat) have no $subtypes — they must
    // not show up as children of any plan. Sanity guard against a future
    // refactor that accidentally inverts the iteration.
    $allChildren = [];

    foreach ($this->plans() as $plan) {
      foreach ($plan->members as $m) {
        $allChildren[$m->childClassName] = true;
      }
    }

    foreach (['Message', 'User', 'Chat', 'Update'] as $name) {
      self::assertArrayNotHasKey($name, $allChildren, "{$name} should not appear in any union plan");
    }

    // And these specific types must also not appear as parent names.
    foreach ($this->plans() as $plan) {
      self::assertNotContains($plan->parentName, ['Message', 'User', 'Chat', 'Update']);
    }
  }

  public function testFailsClosedWhenChildDescriptionLacksTheLiteralPattern(): void
  {
    // Synthetic parent + child where the discriminator annotation description
    // doesn't match any of the supported literal-extraction patterns. The
    // detector must throw rather than silently emit an empty wire value.
    $child = new TypeEntity(
      name: 'SyntheticUnionChild',
      description: '',
      annotations: [
        new AnnotationEntity(
          name: 'kind',
          description: 'Some unrelated description with no literal anchor',
          type: 'String',
          required: true,
        ),
      ],
      subtypeOf: 'SyntheticUnion',
    );

    $parent = new TypeEntity(
      name: 'SyntheticUnion',
      description: '',
      annotations: [],
      subtypes: ['SyntheticUnionChild'],
      discriminator: 'kind',
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$parent, $child],
      methods: [],
      enums: [],
    );

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('SyntheticUnionChild');
    $this->expectExceptionMessage('SyntheticUnion');
    new UnionDetector($schema)->plans();
  }

  public function testFailsClosedWhenChildIsMissingTheDiscriminatorAnnotation(): void
  {
    // Discriminator field declared by the parent, but the child has no such
    // annotation at all. Different failure path from the regex one, same
    // diagnostic requirement: name both the child and the parent.
    $child = new TypeEntity(
      name: 'SyntheticChildWithoutDisc',
      description: '',
      annotations: [
        new AnnotationEntity(
          name: 'unrelated',
          description: 'something else, always \'value\'',
          type: 'String',
          required: true,
        ),
      ],
      subtypeOf: 'SyntheticUnion',
    );

    $parent = new TypeEntity(
      name: 'SyntheticUnion',
      description: '',
      annotations: [],
      subtypes: ['SyntheticChildWithoutDisc'],
      discriminator: 'kind',
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$parent, $child],
      methods: [],
      enums: [],
    );

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('SyntheticChildWithoutDisc');
    $this->expectExceptionMessage('SyntheticUnion');
    new UnionDetector($schema)->plans();
  }

  public function testSyntheticHappyPathExtractsBothLiteralForms(): void
  {
    // Two-child synthetic union where one child uses the `always 'X'` form
    // and the other uses the `must be X` form. Confirms both regex branches
    // are reachable from a single plans() call.
    $childA = new TypeEntity(
      name: 'SynthChildA',
      description: '',
      annotations: [
        new AnnotationEntity(
          name: 'type',
          description: "Type of the synth, always 'alpha'",
          type: 'String',
          required: true,
        ),
      ],
      subtypeOf: 'SynthUnion',
    );

    $childB = new TypeEntity(
      name: 'SynthChildB',
      description: '',
      annotations: [
        new AnnotationEntity(
          name: 'type',
          description: 'Type of the synth, must be beta_token',
          type: 'String',
          required: true,
        ),
      ],
      subtypeOf: 'SynthUnion',
    );

    $parent = new TypeEntity(
      name: 'SynthUnion',
      description: '',
      annotations: [],
      subtypes: ['SynthChildA', 'SynthChildB'],
      discriminator: 'type',
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$parent, $childA, $childB],
      methods: [],
      enums: [],
    );

    $plans = new UnionDetector($schema)->plans();

    self::assertCount(1, $plans);
    self::assertSame('SynthUnion', $plans[0]->parentName);
    self::assertSame('type', $plans[0]->discriminator);
    self::assertSame(
      [['SynthChildA', 'alpha'], ['SynthChildB', 'beta_token']],
      $this->memberPairs($plans[0]),
    );
  }

  private function schema(): LoadedSchema
  {
    $schema = self::$schema;

    if ($schema === null) {
      self::fail('Schema not loaded — setUpBeforeClass did not run');
    }

    return $schema;
  }

  /**
   * @return list<UnionPlan>
   */
  private function plans(): array
  {
    $plans = self::$plans;

    if ($plans === null) {
      self::fail('Plans not built — setUpBeforeClass did not run');
    }

    return $plans;
  }

  private function findPlan(string $parentName): UnionPlan
  {
    foreach ($this->plans() as $p) {
      if ($p->parentName === $parentName) {
        return $p;
      }
    }

    self::fail("UnionPlan for {$parentName} not found");
  }

  private function findType(string $name): TypeEntity
  {
    foreach ($this->schema()->types as $t) {
      if ($t->name === $name) {
        return $t;
      }
    }

    self::fail("Type {$name} not found in schema");
  }

  /**
   * @return list<array{0: string, 1: string}>
   */
  private function memberPairs(UnionPlan $plan): array
  {
    /** @var list<array{0: string, 1: string}> $out */
    $out = [];

    foreach ($plan->members as $m) {
      $out[] = [$m->childClassName, $m->wireValue];
    }

    return $out;
  }
}
