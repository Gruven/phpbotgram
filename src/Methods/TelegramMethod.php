<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotContextController;
use LogicException;

/**
 * @template TReturn
 */
abstract class TelegramMethod extends BotContextController
{
  public const string ApiMethod = '';

  /** @var class-string */
  public const string ReturnsType = '';

  /**
   * Emit this method via the bound bot (or the explicitly-passed bot).
   * Mirrors upstream methods/base.py:81-93 (__await__ + emit).
   *
   * @return TReturn
   */
  public function emit(?Bot $bot = null): mixed
  {
    $effective = $bot ?? $this->bot;

    if ($effective === null) {
      throw new LogicException(
        'This method is not mounted to any bot instance. '
          . 'Call it explicitly with bot instance `$bot($method)`, '
          . 'or mount it via `$method->bindBot($bot)` and call `$method->emit()`.'
      );
    }

    return $effective($this);
  }

  /**
   * Returns a clone bound to $bot. Used by hand-authored shortcut methods
   * (Message::answer, etc.) so the chained ->emit() picks up the bot
   * without an explicit argument.
   */
  public function bindBot(?Bot $bot): static
  {
    return $this->withBot($bot);
  }
}
