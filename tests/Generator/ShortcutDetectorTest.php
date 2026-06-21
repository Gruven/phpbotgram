<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\ShortcutDetector;
use Gruven\PhpBotGram\Generator\ShortcutPlan;
use Gruven\PhpBotGram\Generator\TypeEntity;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\ShortcutDetector
 * @covers \Gruven\PhpBotGram\Generator\ShortcutPlan
 */
final class ShortcutDetectorTest extends TestCase
{
  private static ?LoadedSchema $schema = null;

  /** @var null|list<ShortcutPlan> */
  private static ?array $plans = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 2) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    self::$schema = $loader->load();
    self::$plans = new ShortcutDetector(self::$schema, new NameMapper())->plans();
  }

  public function testPlansCountMatchesVendoredAliasTotal(): void
  {
    // Hand-counted (and `php -r` verified) total across all aliases.yml
    // fixtures in the vendored 10.1 schema. Every alias in the current
    // schema carries a `method:` key, so the per-alias output is the total.
    self::assertCount(182, $this->plans());
  }

  public function testEveryPlanReferencesAKnownMethod(): void
  {
    $methodNames = [];

    foreach ($this->schema()->methods as $m) {
      $methodNames[$m->name] = true;
    }

    foreach ($this->plans() as $plan) {
      self::assertArrayHasKey(
        $plan->methodEntityName,
        $methodNames,
        "Plan {$plan->ownerTypeName}.{$plan->aliasName} references unknown method {$plan->methodEntityName}",
      );
    }
  }

  public function testUserGetProfilePhotosPlan(): void
  {
    $plan = $this->findPlan('User', 'get_profile_photos');

    self::assertSame('User', $plan->ownerTypeName);
    self::assertSame('get_profile_photos', $plan->aliasName);
    self::assertSame('getProfilePhotos', $plan->phpMethodName);
    self::assertSame('getUserProfilePhotos', $plan->methodEntityName);
    self::assertSame(['user_id' => 'self.id'], $plan->fill);
    self::assertSame([], $plan->ignore);
    self::assertNull($plan->description);
    self::assertSame([], $plan->argOverrides);
  }

  public function testMessageAnswerPlan(): void
  {
    // Message.answer is the canonical "send a message to this chat" shortcut.
    // Its fill ships three keys courtesy of the anchor expansion in
    // .butcher/types/Message/aliases.yml.
    $plan = $this->findPlan('Message', 'answer');

    self::assertSame('sendMessage', $plan->methodEntityName);
    self::assertSame('answer', $plan->phpMethodName);
    self::assertSame(
      [
        'chat_id' => 'self.chat.id',
        'message_thread_id' => 'self.message_thread_id if self.is_topic_message else None',
        'business_connection_id' => 'self.business_connection_id',
      ],
      $plan->fill,
    );
    self::assertSame([], $plan->ignore);
  }

  public function testMessageReplyPlanLowersAnchorMerge(): void
  {
    // Message.reply merges *fill-answer (3 keys) with one extra
    // (reply_parameters: self.as_reply_parameters()) and hides the
    // upstream reply_to_message_id via the ignore list.
    $plan = $this->findPlan('Message', 'reply');

    self::assertSame('sendMessage', $plan->methodEntityName);
    self::assertSame('reply', $plan->phpMethodName);
    self::assertSame(
      [
        'chat_id' => 'self.chat.id',
        'message_thread_id' => 'self.message_thread_id if self.is_topic_message else None',
        'business_connection_id' => 'self.business_connection_id',
        'reply_parameters' => 'self.as_reply_parameters()',
      ],
      $plan->fill,
    );
    self::assertSame(['reply_to_message_id'], $plan->ignore);
  }

  public function testCallbackQueryAnswerPlan(): void
  {
    $plan = $this->findPlan('CallbackQuery', 'answer');

    self::assertSame('answerCallbackQuery', $plan->methodEntityName);
    self::assertSame('answer', $plan->phpMethodName);
    self::assertSame(['callback_query_id' => 'self.id'], $plan->fill);
    self::assertSame([], $plan->ignore);
  }

  public function testChatJoinRequestApprovePlanWithDeeperPath(): void
  {
    // ChatJoinRequest.approve exercises a depth-3 navigation path
    // (`self.from_user.id`) — the detector must carry it through verbatim
    // so the renderer can lower it to `$this->fromUser->id`.
    $plan = $this->findPlan('ChatJoinRequest', 'approve');

    self::assertSame('approveChatJoinRequest', $plan->methodEntityName);
    self::assertSame('approve', $plan->phpMethodName);
    self::assertSame(
      [
        'chat_id' => 'self.chat.id',
        'user_id' => 'self.from_user.id',
      ],
      $plan->fill,
    );
  }

  public function testChatJoinRequestQueryPlansRequireQueryId(): void
  {
    $answer = $this->findPlan('ChatJoinRequest', 'answer_query');
    self::assertSame('answerChatJoinRequestQuery', $answer->methodEntityName);
    self::assertSame('answerQuery', $answer->phpMethodName);
    self::assertSame(['chat_join_request_query_id' => 'self.query_id'], $answer->fill);
    self::assertSame([], $answer->ignore);

    $webapp = $this->findPlan('ChatJoinRequest', 'send_webapp');
    self::assertSame('sendChatJoinRequestWebApp', $webapp->methodEntityName);
    self::assertSame('sendWebapp', $webapp->phpMethodName);
    self::assertSame(['chat_join_request_query_id' => 'self.query_id'], $webapp->fill);
    self::assertSame([], $webapp->ignore);
  }

  public function testMessageDeleteReplyMarkupPlanCarriesNoneSentinel(): void
  {
    // delete_reply_markup overrides reply_markup to the Python `None` literal
    // through `<<: *message-target` + per-key override. The detector keeps it
    // as a raw expression — lowering `None` to PHP `null` is the renderer's
    // job (Task 2.10), not this stage's.
    $plan = $this->findPlan('Message', 'delete_reply_markup');

    self::assertSame('editMessageReplyMarkup', $plan->methodEntityName);
    self::assertSame(
      [
        'chat_id' => 'self.chat.id',
        'message_id' => 'self.message_id',
        'business_connection_id' => 'self.business_connection_id',
        'reply_markup' => 'None',
      ],
      $plan->fill,
    );
  }

  public function testStickerSetPositionInSetPlanCarriesGetterPath(): void
  {
    // Sticker uses `self.file_id` — a plain property path that exercises the
    // single-segment-after-`self.` branch.
    $plan = $this->findPlan('Sticker', 'set_position_in_set');

    self::assertSame('setStickerPositionInSet', $plan->methodEntityName);
    self::assertSame('setPositionInSet', $plan->phpMethodName);
    self::assertSame(['sticker' => 'self.file_id'], $plan->fill);
  }

  public function testPlansArePerOwnerTypeGroupedInSchemaOrder(): void
  {
    // The detector iterates types in schema order; each type's aliases are
    // emitted in declaration order. Verify the resulting block layout: every
    // contiguous run for a given owner is unbroken, mirroring the renderer's
    // expectation that adjacent plans share an owner.
    $seenOwners = [];
    $currentOwner = null;

    foreach ($this->plans() as $plan) {
      if ($plan->ownerTypeName !== $currentOwner) {
        self::assertArrayNotHasKey(
          $plan->ownerTypeName,
          $seenOwners,
          "Owner {$plan->ownerTypeName} re-appears after another owner",
        );
        $seenOwners[$plan->ownerTypeName] = true;
        $currentOwner = $plan->ownerTypeName;
      }
    }
  }

  public function testAliasesWithoutMethodAreSkipped(): void
  {
    // Synthetic alias map: one plain `method:` entry, plus a filter-style
    // alias that carries only a `condition:` (no `method:`). The latter is
    // out of scope for this stage and must not appear in the plan list.
    $owner = new TypeEntity(
      name: 'SynthOwner',
      description: '',
      annotations: [],
      aliases: [
        'do_call' => [
          'method' => 'sendMessage',
          'fill' => ['chat_id' => 'self.id'],
        ],
        'is_outgoing' => [
          // No `method:` — this is a condition shortcut, not a call shortcut.
          'condition' => 'self.from_user.id == self.bot.id',
        ],
      ],
    );

    $method = new MethodEntity(
      name: 'sendMessage',
      description: '',
      annotations: [],
      returns: '',
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$owner],
      methods: [$method],
      enums: [],
    );

    $plans = new ShortcutDetector($schema, new NameMapper())->plans();

    self::assertCount(1, $plans);
    self::assertSame('do_call', $plans[0]->aliasName);
    self::assertSame('doCall', $plans[0]->phpMethodName);
  }

  public function testTypesWithNoAliasesYmlAreSkipped(): void
  {
    // A type carrying $aliases === [] should produce zero plans. Combined with
    // the schema-wide count assertion above, this is a strong invariant.
    $owner = new TypeEntity(
      name: 'EmptyOwner',
      description: '',
      annotations: [],
      aliases: [],
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$owner],
      methods: [],
      enums: [],
    );

    self::assertSame([], new ShortcutDetector($schema, new NameMapper())->plans());
  }

  public function testFailsClosedWhenMethodReferenceIsUnknown(): void
  {
    // The detector must fail loudly when an alias points at a method that
    // doesn't exist in the schema — silently dropping such a plan would
    // produce broken `new XxxMethod(...)` calls in generated source.
    $owner = new TypeEntity(
      name: 'SynthOwner',
      description: '',
      annotations: [],
      aliases: [
        'bad_call' => [
          'method' => 'noSuchMethod',
          'fill' => ['x' => 'self.id'],
        ],
      ],
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$owner],
      methods: [],
      enums: [],
    );

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('SynthOwner');
    $this->expectExceptionMessage('bad_call');
    $this->expectExceptionMessage('noSuchMethod');

    new ShortcutDetector($schema, new NameMapper())->plans();
  }

  public function testIgnoreListPreservesOrder(): void
  {
    // Synthetic alias with an ordered ignore list — must survive verbatim.
    $owner = new TypeEntity(
      name: 'SynthOwner',
      description: '',
      annotations: [],
      aliases: [
        'do_call' => [
          'method' => 'sendMessage',
          'fill' => ['chat_id' => 'self.id'],
          'ignore' => ['b_param', 'a_param', 'c_param'],
        ],
      ],
    );

    $method = new MethodEntity(
      name: 'sendMessage',
      description: '',
      annotations: [],
      returns: '',
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$owner],
      methods: [$method],
      enums: [],
    );

    $plans = new ShortcutDetector($schema, new NameMapper())->plans();
    self::assertCount(1, $plans);
    self::assertSame(['b_param', 'a_param', 'c_param'], $plans[0]->ignore);
  }

  public function testDescriptionOverrideIsCaptured(): void
  {
    $owner = new TypeEntity(
      name: 'SynthOwner',
      description: '',
      annotations: [],
      aliases: [
        'do_call' => [
          'method' => 'sendMessage',
          'fill' => ['chat_id' => 'self.id'],
          'description' => 'Send a message via this synthetic shortcut.',
        ],
      ],
    );

    $method = new MethodEntity(
      name: 'sendMessage',
      description: '',
      annotations: [],
      returns: '',
    );

    $schema = new LoadedSchema(
      apiVersion: '0.0',
      releaseDate: '1970-01-01',
      types: [$owner],
      methods: [$method],
      enums: [],
    );

    $plans = new ShortcutDetector($schema, new NameMapper())->plans();
    self::assertCount(1, $plans);
    self::assertSame('Send a message via this synthetic shortcut.', $plans[0]->description);
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
   * @return list<ShortcutPlan>
   */
  private function plans(): array
  {
    $plans = self::$plans;

    if ($plans === null) {
      self::fail('Plans not built — setUpBeforeClass did not run');
    }

    return $plans;
  }

  private function findPlan(string $ownerType, string $aliasName): ShortcutPlan
  {
    foreach ($this->plans() as $plan) {
      if ($plan->ownerTypeName === $ownerType && $plan->aliasName === $aliasName) {
        return $plan;
      }
    }

    self::fail("ShortcutPlan for {$ownerType}.{$aliasName} not found");
  }
}
