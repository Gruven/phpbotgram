<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;

abstract class BotContextController
{
  public function __construct(public readonly ?Bot $bot = null) {}

  /**
   * Returns a clone of $this with $bot rebound. **Shallow by contract** —
   * does not walk nested TelegramObject properties. Deep rebinding is the
   * Serializer's responsibility during deserialization: Serializer::load
   * recurses into every nested TelegramObject and calls withBot on each
   * leaf, mirroring upstream pydantic `model_validate context={"bot": bot}`.
   *
   * Callers who already hold a fully-formed object graph (e.g. constructed
   * by hand in user code) and want to attach a bot to all of it should pass
   * the graph through Serializer::dump+load with $bot in context, or rebind
   * only the leaves they actually dispatch from.
   *
   * Uses PHP 8.5's `clone($this, [...])` clone-with syntax (a function-call form
   * that resolves the readonly write inside the declaring scope's protection).
   * The call must be made from within `BotContextController` or a subclass —
   * an external caller cannot use this syntax against a readonly slot.
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
