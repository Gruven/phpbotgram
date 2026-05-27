<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Methods\TelegramMethod;

/**
 * Test harness: records `silentCallRequest` invocations into a public
 * array instead of forwarding to `$bot($method)`.
 *
 * The webhook fall-through path on the base Dispatcher (Task 3.13) routes
 * any `TelegramMethod` that a handler returned *after* the 55-second
 * deadline has fired through `silentCallRequest`. A real bot call here
 * would issue a network request and, in tests with a `MockedSession`,
 * either consume a queued canned response or hit the "no canned
 * responses left" guard. Both options are noise: the unit under test is
 * "did the dispatcher route through the fall-through?", not "did the
 * bot's session complete the call?".
 *
 * Subclassing `Dispatcher` is intentional. `silentCallRequest` is the
 * one designated mock point — upstream's `unittest.mock.patch` of an
 * `@classmethod` does not translate to PHP, and PHPUnit's prophecy/mock
 * tooling would force the dispatcher into a doubled instance that can't
 * pass an `instanceof Dispatcher` check that internal code may add later.
 *
 * Visibility note: `silentCallRequest` is declared `public` on the base
 * class, which is what allows the override here. The override's signature
 * must match the parent exactly (PHPStan level 9 enforces this) — same
 * parameter names, types, and return type.
 *
 * @internal
 */
final class RecordingDispatcher extends Dispatcher
{
  /**
   * Append-only log of (bot, method) tuples passed to `silentCallRequest`.
   * Tests assert on `count(...)`, `$silentCalls[0][1]::class`, etc.
   *
   * Public so tests can read without a getter. The dispatcher subclass is
   * itself `@internal`, so exposing the field is acceptable.
   *
   * @var list<array{0: Bot, 1: TelegramMethod<mixed>}>
   */
  public array $silentCalls = [];

  /**
   * @param TelegramMethod<mixed> $method
   */
  public function silentCallRequest(Bot $bot, TelegramMethod $method): mixed
  {
    $this->silentCalls[] = [$bot, $method];

    return null;
  }
}
