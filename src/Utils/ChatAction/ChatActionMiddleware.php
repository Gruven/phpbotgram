<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\ChatAction;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Types\Message;

/**
 * Dispatcher-side middleware that automatically wraps flagged handlers with
 * a {@see ChatActionSender}.
 *
 * Mirrors the second class in upstream
 * `aiogram/utils/chat_action.py` (`ChatActionMiddleware`).
 *
 * Any handler registered with `flags: ['chat_action' => true]` (or an
 * explicit action string, e.g. `'chat_action' => 'upload_photo'`) will have
 * a chat-action loop running in the background for the duration of the
 * handler call. Handlers without the flag are passed through unchanged.
 *
 * The `chat_action` flag value controls the action sent:
 *
 * - `true` or `1` → `'typing'` (default)
 * - A non-empty string → that string is used as the action value
 *   (e.g. `'upload_photo'`)
 *
 * Example registration:
 *
 * ```php
 * $router->message->register($handler, flags: ['chat_action' => true]);
 * // or with an explicit action:
 * $router->message->register($handler, flags: ['chat_action' => 'upload_photo']);
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
   */
  public function __construct(
    private readonly float $interval = 5.0,
  ) {}

  public function __invoke(Closure $handler, object $event, array $data): mixed
  {
    $flag = $this->resolveChatActionFlag($data);

    if ($flag === null || !($event instanceof Message)) {
      return $handler($event, $data);
    }

    $chatId = $event->chat->id;
    $messageThreadId = ($event->isTopicMessage === true) ? $event->messageThreadId : null;

    $bot = $data['bot'] ?? null;

    if (!$bot instanceof Bot) {
      return $handler($event, $data);
    }

    $action = is_string($flag) && $flag !== '' ? $flag : self::DEFAULT_ACTION;

    $sender = new ChatActionSender(
      bot: $bot,
      chatId: $chatId,
      messageThreadId: $messageThreadId,
      action: $action,
      interval: $this->interval,
    );

    return $sender->scope(static fn(): mixed => $handler($event, $data));
  }

  /**
   * Returns the `chat_action` flag value if the handler has the flag set,
   * or `null` when the flag is absent or evaluates as false.
   *
   * The handler is injected as `$data['handler']` by the dispatcher; if
   * the key is absent (e.g. standalone unit tests), null is returned.
   *
   * @param array<string, mixed> $data
   *
   * @return null|mixed Flag value, or null if not set.
   */
  private function resolveChatActionFlag(array $data): mixed
  {
    $handler = $data['handler'] ?? null;

    if (!$handler instanceof HandlerObject) {
      return null;
    }

    if (!isset($handler->flags[self::FLAG_NAME])) {
      return null;
    }

    $value = $handler->flags[self::FLAG_NAME];

    // Falsy flags (false, 0, '') are treated as absent.
    if (!$value) {
      return null;
    }

    return $value;
  }
}
