<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\ChatAction;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Types\Message;

/**
 * Dispatcher-side middleware that wraps every Message handler with a
 * {@see ChatActionSender} by default.
 *
 * Mirrors the second class in upstream
 * `aiogram/utils/chat_action.py` (`ChatActionMiddleware`).
 *
 * **Default-ON behaviour (upstream parity):** when mounted, the middleware
 * sends `typing` for every Message handler automatically. To opt out for a
 * specific handler, register it with `flags: ['chat_action' => false]`.
 *
 * The `chat_action` flag value controls the behaviour:
 *
 * - Absent (`null`) → use `'typing'` (default action)
 * - `false` → explicit opt-out; sender is NOT started
 * - A non-empty string → that string is used as the action value
 *   (e.g. `'upload_photo'`)
 * - An array with keys `action`, `interval`, `initial_sleep` → overrides
 *   the defaults (all keys optional; same shape as upstream's dict form)
 *
 * Example registration:
 *
 * ```php
 * // Default: typing is sent automatically for every Message handler.
 * $router->message->register($handler);
 *
 * // Custom action:
 * $router->message->register($handler, flags: ['chat_action' => 'upload_photo']);
 *
 * // Explicit opt-out:
 * $router->message->register($handler, flags: ['chat_action' => false]);
 * ```
 *
 * The middleware resolves the `bot` from `$data['bot']` and the chat context
 * from the event itself (must be a {@see Message}). For non-Message events or
 * absent chat context, the middleware falls through without starting a sender.
 */
final class ChatActionMiddleware extends BaseMiddleware
{
  public const string FLAG_NAME = 'chat_action';
  public const string DEFAULT_ACTION = 'typing';

  /**
   * @param float $interval Seconds between repeated `sendChatAction` calls.
   *                        Defaults to `5.0` (matches Telegram's 5 s expiry window).
   *                        Reduced values are useful in tests.
   * @param float $initialSleep Seconds to wait before the first send. Defaults to `0.0`.
   */
  public function __construct(
    private readonly float $interval = 5.0,
    private readonly float $initialSleep = 0.0,
  ) {}

  public function __invoke(Closure $handler, object $event, array $data): mixed
  {
    // Non-Message events are always passed through unchanged.
    if (!$event instanceof Message) {
      return $handler($event, $data);
    }

    // Resolve the flag from the handler metadata.
    $flag = $this->resolveChatActionFlag($data);

    // Explicit opt-out: flag === false.
    if ($flag === false) {
      return $handler($event, $data);
    }

    $bot = $data['bot'] ?? null;

    if (!$bot instanceof Bot) {
      return $handler($event, $data);
    }

    $chatId = $event->chat->id;
    $messageThreadId = ($event->isTopicMessage === true) ? $event->messageThreadId : null;

    // Resolve action, interval and initialSleep from the flag value.
    $action = self::DEFAULT_ACTION;
    $interval = $this->interval;
    $initialSleep = $this->initialSleep;

    if (is_string($flag) && $flag !== '') {
      $action = $flag;
    } elseif (is_array($flag)) {
      $action = isset($flag['action']) && is_string($flag['action']) ? $flag['action'] : $action;
      $interval = isset($flag['interval']) && is_numeric($flag['interval']) ? (float)$flag['interval'] : $interval;
      $initialSleep = isset($flag['initial_sleep']) && is_numeric($flag['initial_sleep']) ? (float)$flag['initial_sleep'] : $initialSleep;
    }

    $sender = new ChatActionSender(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: $action,
      interval: $interval,
      initialSleep: $initialSleep,
    );

    return $sender->scope(static fn(): mixed => $handler($event, $data));
  }

  /**
   * Returns the raw `chat_action` flag value from the handler metadata.
   *
   * - Returns `null` when no `HandlerObject` is present in `$data` (e.g.
   *   standalone unit tests without dispatcher wiring), OR when the flag key
   *   is not set — both cases trigger the default-ON `typing` path.
   * - Returns `false` when the flag is explicitly set to `false` (opt-out).
   * - Returns the raw value (string, array, true) otherwise.
   *
   * The handler is injected as `$data['handler']` by the dispatcher; if
   * the key is absent, null is returned (default-ON).
   *
   * @param array<string, mixed> $data
   *
   * @return null|array<string, mixed>|bool|string Flag value, or null if not set.
   */
  private function resolveChatActionFlag(array $data): null|array|bool|string
  {
    $handler = $data['handler'] ?? null;

    if (!$handler instanceof HandlerObject) {
      // No handler metadata available — behave as default-ON.
      return null;
    }

    if (!array_key_exists(self::FLAG_NAME, $handler->flags)) {
      // Flag not set — default-ON.
      return null;
    }

    $value = $handler->flags[self::FLAG_NAME];

    if ($value === false) {
      return false;
    }

    if ($value === true) {
      return true;
    }

    if (is_string($value)) {
      return $value;
    }

    if (is_array($value)) {
      /** @var array<string, mixed> $value */
      return $value;
    }

    // Any other truthy value (e.g. 1) — treat as default action.
    return null;
  }
}
