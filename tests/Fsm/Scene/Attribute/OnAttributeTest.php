<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Fsm\Scene\Attribute;

use Attribute;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Fsm\After;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnAttribute;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnCallbackQuery;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnChannelPost;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnChatJoinRequest;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnChatMember;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnChosenInlineResult;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnEditedChannelPost;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnEditedMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnInlineQuery;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMessage;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnMyChatMember;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnPoll;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnPollAnswer;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnPreCheckoutQuery;
use Gruven\PhpBotGram\Fsm\Scene\Attribute\OnShippingQuery;
use Gruven\PhpBotGram\Fsm\SceneAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Upstream `tests/test_fsm/test_scene.py::TestOnMarker` cases deliberately
 * not ported here:
 *
 * - `TestOnMarker::test_marker_name` parametrize rows — API divergence: Python
 *   uses `on.<event>` runtime `ObserverMarker` instances; PHP uses static PHP
 *   attribute classes `#[OnMessage]`, `#[OnCallbackQuery]`, etc. The equivalent
 *   test here verifies the `eventName` property on each attribute class.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 */
final class OnAttributeTest extends TestCase
{
  // ------------------------------------------------------------------ //
  // Event-name checks for each of the 14 attribute classes
  // ------------------------------------------------------------------ //

  /**
   * @return array<string, array{class-string<OnAttribute>, string}>
   */
  public static function eventNameProvider(): array
  {
    return [
      'OnMessage'             => [OnMessage::class, 'message'],
      'OnEditedMessage'       => [OnEditedMessage::class, 'edited_message'],
      'OnChannelPost'         => [OnChannelPost::class, 'channel_post'],
      'OnEditedChannelPost'   => [OnEditedChannelPost::class, 'edited_channel_post'],
      'OnInlineQuery'         => [OnInlineQuery::class, 'inline_query'],
      'OnChosenInlineResult'  => [OnChosenInlineResult::class, 'chosen_inline_result'],
      'OnCallbackQuery'       => [OnCallbackQuery::class, 'callback_query'],
      'OnShippingQuery'       => [OnShippingQuery::class, 'shipping_query'],
      'OnPreCheckoutQuery'    => [OnPreCheckoutQuery::class, 'pre_checkout_query'],
      'OnPoll'                => [OnPoll::class, 'poll'],
      'OnPollAnswer'          => [OnPollAnswer::class, 'poll_answer'],
      'OnMyChatMember'        => [OnMyChatMember::class, 'my_chat_member'],
      'OnChatMember'          => [OnChatMember::class, 'chat_member'],
      'OnChatJoinRequest'     => [OnChatJoinRequest::class, 'chat_join_request'],
    ];
  }

  /**
   * Each concrete `#[On*]` attribute stores the correct event name.
   *
   * @param class-string<OnAttribute> $class
   */
  #[DataProvider('eventNameProvider')]
  public function testEventName(string $class, string $expectedEvent): void
  {
    $attr = new $class();

    self::assertSame($expectedEvent, $attr->event);
  }

  /**
   * Each attribute extends `OnAttribute`.
   *
   * @param class-string<OnAttribute> $class
   */
  #[DataProvider('eventNameProvider')]
  public function testExtendsOnAttribute(string $class, string $_event): void
  {
    self::assertTrue(is_subclass_of($class, OnAttribute::class));
  }

  // ------------------------------------------------------------------ //
  // Attribute flags
  // ------------------------------------------------------------------ //

  /**
   * Each concrete attribute class carries both `TARGET_METHOD` and
   * `IS_REPEATABLE` flags.
   *
   * @param class-string<OnAttribute> $class
   */
  #[DataProvider('eventNameProvider')]
  public function testAttributeFlags(string $class, string $_event): void
  {
    $ref = new ReflectionClass($class);
    $attrs = $ref->getAttributes(Attribute::class);

    self::assertCount(1, $attrs, "{$class} must have exactly one #[Attribute] meta-annotation");

    /** @var Attribute $inst */
    $inst = $attrs[0]->newInstance();

    $expected = Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE;

    self::assertSame($expected, $inst->flags, "{$class} must have TARGET_METHOD | IS_REPEATABLE");
  }

  // ------------------------------------------------------------------ //
  // Constructor parameters
  // ------------------------------------------------------------------ //

  /**
   * Default construction (no args) sets `action = null`, `after = null`,
   * and `filters = []`.
   */
  public function testDefaultConstruction(): void
  {
    $attr = new OnMessage();

    self::assertNull($attr->action);
    self::assertNull($attr->after);
    self::assertSame([], $attr->filters);
  }

  /**
   * Named `action` arg is stored correctly.
   */
  public function testActionArgIsStored(): void
  {
    $attr = new OnMessage(action: SceneAction::Enter);

    self::assertSame(SceneAction::Enter, $attr->action);
  }

  /**
   * Named `after` arg is stored correctly.
   */
  public function testAfterArgIsStored(): void
  {
    $after = After::back();
    $attr = new OnCallbackQuery(after: $after);

    self::assertSame($after, $attr->after);
  }

  /**
   * Variadic filters are stored as an indexed array.
   */
  public function testVariadicFiltersAreStored(): void
  {
    $filter1 = $this->createMockFilter();
    $filter2 = $this->createMockFilter();

    // Positional args: action=null, after=null, then variadic filters.
    $attr = new OnMessage(null, null, $filter1, $filter2);

    self::assertCount(2, $attr->filters);
    self::assertSame($filter1, $attr->filters[0]);
    self::assertSame($filter2, $attr->filters[1]);
  }

  // ------------------------------------------------------------------ //
  // IS_REPEATABLE: stacking multiple attributes on a method
  // ------------------------------------------------------------------ //

  /**
   * Multiple `#[On*]` attributes stacked on one method are all readable via
   * reflection (validates `IS_REPEATABLE`).
   */
  public function testRepeatableAttributesOnMethod(): void
  {
    $fixture = new class {
      #[OnCallbackQuery]
      #[OnMessage]
      #[OnMessage(action: SceneAction::Enter)]
      public function handler(): void {}
    };

    $ref = new ReflectionClass($fixture);
    $method = $ref->getMethod('handler');

    $messageAttrs = $method->getAttributes(OnMessage::class);
    $cbAttrs = $method->getAttributes(OnCallbackQuery::class);

    self::assertCount(2, $messageAttrs, 'Two #[OnMessage] must be readable');
    self::assertCount(1, $cbAttrs, 'One #[OnCallbackQuery] must be readable');
  }

  // ------------------------------------------------------------------ //
  // Helpers
  // ------------------------------------------------------------------ //

  private function createMockFilter(): Filter
  {
    return new class extends Filter {
      public function __invoke(object $event, mixed ...$kwargs): array|bool
      {
        return true;
      }
    };
  }
}
