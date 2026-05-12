<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;

abstract class BotContextController
{
  public function __construct(public readonly ?Bot $bot = null) {}

  /**
   * Returns a clone of $this with $bot rebound. The base implementation only
   * rebinds the controller's own `$bot` slot — it does not walk nested
   * TelegramObject properties.
   *
   * Deep rebinding is per-class by necessity: PHP 8.5's `clone($this, [...])`
   * scope check requires writes to a readonly property happen from inside the
   * declaring class. So a single base override cannot rewrite a subclass's
   * readonly `Chat $chat` slot — the subclass itself has to do that.
   *
   * The Phase 2 codegen emits a `withBot` override on every generated
   * TelegramObject that carries nested TelegramObject fields. Each override
   * calls `parent::withBot($bot)` to get the base clone and then layers its
   * own per-property rebinds via clone-with inside the subclass scope.
   *
   * Until Phase 2 lands, callers who build a deep graph by hand and need a
   * rebound copy should round-trip through `Serializer::dump`/`::load` with
   * `$bot` in context — `Serializer::load` does walk recursively and applies
   * per-leaf `withBot`, mirroring upstream pydantic
   * `model_validate(context={"bot": bot})`.
   */
  public function withBot(?Bot $bot): static
  {
    return clone ($this, ['bot' => $bot]);
  }

  /**
   * Alias of withBot() for grep-translating aiogram code that uses obj.as_(bot).
   * IMPORTANT: behaves DIFFERENTLY from upstream — upstream mutates self._bot
   * in place and returns self. The PHP port can't mutate readonly, so this
   * returns a clone. Callers must reassign: $msg = $msg->as_($bot).
   */
  public function as_(?Bot $bot): static
  {
    return $this->withBot($bot);
  }
}
