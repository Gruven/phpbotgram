<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotContextController;
use LogicException;

/**
 * @template-covariant TReturn
 */
abstract class TelegramMethod extends BotContextController
{
  public const string ApiMethod = '';

  /**
   * Subclasses override with either:
   *   - a `class-string<TelegramObject>` (e.g. Message::class) for object returns
   *   - a scalar type name ('bool', 'int', 'string') for primitive returns
   *   - 'list:<inner>' for array returns
   *
   * Stays '' on the abstract base — Phase 2 codegen sets it per concrete method.
   */
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
          . 'or mount it via `$method->bindBot($bot)` and call `$method->emit()`.',
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
