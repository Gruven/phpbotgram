<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

use Gruven\PhpBotGram\Client\BotDefault;
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
  public readonly DefaultBotProperties $defaultProperties;

  public function __construct(
    public readonly string $token,
    ?BaseSession $session = null,
    ?DefaultBotProperties $defaultProperties = null,
  ) {
    Token::validate($token);
    $this->session = $session ?? new AmphpSession();
    $this->defaultProperties = $defaultProperties ?? new DefaultBotProperties();
  }

  public function getDefaultProperties(): DefaultBotProperties
  {
    return $this->defaultProperties;
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

  /**
   * Hand-coded for Phase 1 smoke test; replaced in Phase 2 with the full 176-method facade.
   *
   * Phase 1 caveat: against a real AmphpSession this currently returns whatever
   * `BaseSession::buildResponse` produces — which is `null` until Phase 2
   * codegen wires `Serializer::load` into the success path. The `: Message`
   * return type holds for `MockedSession` (which bypasses checkResponse) and
   * for Phase 2 onwards. Production callers MUST go through a mocked session
   * until Phase 2 codegen lands.
   */
  public function sendMessage(int|string $chatId, string $text, null|BotDefault|string $parseMode = new BotDefault('parse_mode'), ?int $timeout = null): Message
  {
    /** @var Message */
    return $this(new SendMessage(chatId: $chatId, text: $text, parseMode: $parseMode), $timeout);
  }
}
