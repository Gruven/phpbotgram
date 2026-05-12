<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

use Gruven\PhpBotGram\Client\BotShortcuts;
use Gruven\PhpBotGram\Client\BotShortcutsContract;
use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Client\Session\AmphpSession;
use Gruven\PhpBotGram\Client\Session\BaseSession;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Utils\Token;

/**
 * Bot facade. Phase 1 contains only hand-coded sendMessage for the smoke test;
 * Phase 2 regenerates this file with all 176 API methods from the schema.
 */
class Bot implements BotShortcutsContract
{
  use BotShortcuts;

  public readonly BaseSession $session;

  public function __construct(
    public readonly string $token,
    ?BaseSession $session = null,
    public readonly ?DefaultBotProperties $defaultProperties = null,
  ) {
    Token::validate($token);
    $this->session = $session ?? new AmphpSession();
  }

  public function getDefaultProperties(): DefaultBotProperties
  {
    return $this->defaultProperties ?? new DefaultBotProperties();
  }

  /**
   * Polymorphic entry point: $bot($method) dispatches the method through the
   * session's middleware chain. Mirrors aiogram's `Bot.__call__` → `session(bot, method)`.
   * Dispatcher middleware is layered separately on top of this (Phase 3).
   *
   * @template TReturn
   *
   * @param TelegramMethod<TReturn> $method
   *
   * @return TReturn
   */
  public function __invoke(TelegramMethod $method, ?int $timeout = null): mixed
  {
    return ($this->session)($this, $method, $timeout);
  }

  // Hand-coded for Phase 1 smoke test; replaced in Phase 2 with the full 176-method facade.
  public function sendMessage(int|string $chatId, string $text, ?string $parseMode = null, ?int $timeout = null): Message
  {
    /** @var Message */
    return $this(new SendMessage(chatId: $chatId, text: $text, parseMode: $parseMode), $timeout);
  }
}
