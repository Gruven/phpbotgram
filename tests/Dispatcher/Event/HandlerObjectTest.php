<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Event;

use Gruven\PhpBotGram\Dispatcher\Event\CallableObject;
use Gruven\PhpBotGram\Dispatcher\Event\FilterObject;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class HandlerObjectTest extends TestCase
{
  public function testHandlerObjectIsACallableObject(): void
  {
    $handler = new HandlerObject(static fn(): bool => true);

    self::assertInstanceOf(CallableObject::class, $handler);
  }

  public function testNoFiltersAlwaysPassesAndReturnsKwargsUnchanged(): void
  {
    // `if not self.filters: return True, kwargs` — upstream's first branch.
    // An empty filter list is the default for handlers registered without
    // any predicate; they always trigger.
    $handler = new HandlerObject(static fn(): bool => true);

    [$passed, $kwargs] = $handler->check([], ['x' => 1]);

    self::assertTrue($passed);
    self::assertSame(['x' => 1], $kwargs);
  }

  public function testAllFiltersPassMergesAssociativeArrayReturnsIntoKwargs(): void
  {
    // The "kwargs cascade" upstream uses to thread match-data (regex groups,
    // command args, FSM payload) into the handler. Filter 1 votes via plain
    // `true`; filter 2 injects `extra => 42` for downstream consumption.
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static fn(): bool => true),
      new FilterObject(static fn(): array => ['extra' => 42]),
    ]);

    [$passed, $kwargs] = $handler->check([], ['x' => 1]);

    self::assertTrue($passed);
    self::assertSame(['x' => 1, 'extra' => 42], $kwargs);
  }

  public function testOneFilterReturningFalseRejectsAndShortCircuitsLaterFilters(): void
  {
    // Verify both the rejection semantics (`if not check: return False`)
    // and the short-circuit (filter 3 must not run). The kwargs from
    // filter 1's accept-with-data return are still observable in the result.
    $thirdRan = false;
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static fn(): array => ['a' => 1]),
      new FilterObject(static fn(): bool => false),
      new FilterObject(static function () use (&$thirdRan): bool {
        $thirdRan = true;

        return true;
      }),
    ]);

    [$passed, $kwargs] = $handler->check([], []);

    self::assertFalse($passed);
    self::assertSame(['a' => 1], $kwargs);
    self::assertFalse($thirdRan, 'Filter pipeline must short-circuit on first rejection.');
  }

  public function testFilterReturningNullIsRejection(): void
  {
    // Python `if not check` treats `None` as falsy. Match upstream: a filter
    // that returns null votes "reject" — most often this is the signature of
    // a regex match with no groups or an explicit "no match" sentinel.
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static fn(): ?bool => null),
    ]);

    [$passed, $kwargs] = $handler->check([], ['x' => 1]);

    self::assertFalse($passed);
    self::assertSame(['x' => 1], $kwargs);
  }

  public function testFilterReturningEmptyArrayIsRejection(): void
  {
    // Match Python semantics exactly: `if not {}` is true → upstream rejects
    // an empty dict. PHP `!$result` evaluates `[]` as falsy, so an empty
    // array result votes "reject" — same outcome with no special-casing.
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static fn(): array => []),
    ]);

    [$passed, $kwargs] = $handler->check([], ['x' => 1]);

    self::assertFalse($passed);
    self::assertSame(['x' => 1], $kwargs);
  }

  public function testFilterReturningTruthyNonArrayIsAcceptWithNoMerge(): void
  {
    // A non-bool, non-array truthy result (object, int, non-empty string)
    // counts as accept with no kwargs to inject. Matches upstream's
    // `if not check` (truthy → continue) + `if isinstance(check, dict)`
    // (no dict → no merge) two-branch structure.
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static fn(): int => 1),
      new FilterObject(static fn(): string => 'matched'),
    ]);

    [$passed, $kwargs] = $handler->check([], ['x' => 1]);

    self::assertTrue($passed);
    self::assertSame(['x' => 1], $kwargs);
  }

  public function testFilterKwargsCascadeIntoLaterFilters(): void
  {
    // The headline behaviour: filter 1 injects `user_id`, filter 2's
    // signature declares `?int $user_id` and observes the value injected
    // by its predecessor. This is how aiogram threads CommandObject /
    // RegexpCommandsFilter match data into downstream consumers.
    $observedUserId = null;
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static fn(): array => ['user_id' => 5]),
      new FilterObject(static function (?int $user_id) use (&$observedUserId): bool {
        $observedUserId = $user_id;

        return true;
      }),
    ]);

    [$passed, $kwargs] = $handler->check([], []);

    self::assertTrue($passed);
    self::assertSame(['user_id' => 5], $kwargs);
    self::assertSame(5, $observedUserId);
  }

  public function testFiltersReceiveOriginalPositionalArgs(): void
  {
    // `check(*args, **kwargs)` upstream — positional args (the `event`
    // payload in production) are forwarded to every filter unchanged.
    $received = [];
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static function (string $event) use (&$received): bool {
        $received[] = $event;

        return true;
      }),
      new FilterObject(static function (string $event) use (&$received): bool {
        $received[] = $event;

        return true;
      }),
    ]);

    $handler->check(['payload'], []);

    self::assertSame(['payload', 'payload'], $received);
  }

  public function testFlagsAreStoredAndExposedReadonly(): void
  {
    // Phase 3 Task 3.7 (Flags subsystem) will populate this; for now we just
    // store and expose. Verify both default-empty and explicit-value paths.
    $emptyFlags = new HandlerObject(static fn(): bool => true);
    self::assertSame([], $emptyFlags->flags);

    $handler = new HandlerObject(
      static fn(): bool => true,
      [],
      ['admin_only' => true, 'rate_limit' => 5],
    );
    self::assertSame(['admin_only' => true, 'rate_limit' => 5], $handler->flags);
  }

  public function testFiltersListIsExposedReadonly(): void
  {
    // Routers iterate over `$handler->filters` for introspection / debug
    // output; verify the list survives round-trip and is the list-shape we
    // promised in the docblock (zero-indexed, FilterObject instances).
    $filterA = new FilterObject(static fn(): bool => true);
    $filterB = new FilterObject(static fn(): bool => true);
    $handler = new HandlerObject(static fn(): bool => true, [$filterA, $filterB]);

    self::assertSame([$filterA, $filterB], $handler->filters);
  }

  public function testCheckReturnsTupleShapeArray(): void
  {
    // The return type is documented as `array{0: bool, 1: array<string, mixed>}`.
    // Verify the literal shape so destructuring callers can rely on it.
    $handler = new HandlerObject(static fn(): bool => true);

    $result = $handler->check();

    self::assertCount(2, $result);
    self::assertArrayHasKey(0, $result);
    self::assertArrayHasKey(1, $result);
    self::assertIsBool($result[0]);
    self::assertIsArray($result[1]);
  }

  public function testKwargsMergeOverridesEarlierKeysLastWins(): void
  {
    // PHP `[...$a, ...$b]` last-wins matches Python `dict.update()`. A later
    // filter can override an earlier filter's injection — useful when
    // refining match data through a chain of progressively-specific filters.
    $handler = new HandlerObject(static fn(): bool => true, [
      new FilterObject(static fn(): array => ['mode' => 'initial', 'shared' => 1]),
      new FilterObject(static fn(): array => ['mode' => 'overridden', 'extra' => 2]),
    ]);

    [$passed, $kwargs] = $handler->check([], ['shared' => 0]);

    self::assertTrue($passed);
    self::assertSame(
      ['shared' => 1, 'mode' => 'overridden', 'extra' => 2],
      $kwargs,
    );
  }
}
