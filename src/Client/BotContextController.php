<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;

abstract class BotContextController
{
    public function __construct(public readonly ?Bot $bot = null) {}

    /**
     * Returns a clone of $this with $bot rebound. Used by Serializer to inject
     * the active Bot into deserialized objects (mirrors upstream model_validate
     * context={"bot": bot}). The Serializer recursively rebinds bot on every
     * nested TelegramObject; this method handles the shallow rebind.
     *
     * Uses PHP 8.5's `clone($this, [...])` clone-with syntax (a function-call form
     * that resolves the readonly write inside the declaring scope's protection).
     * The call must be made from within `BotContextController` or a subclass —
     * an external caller cannot use this syntax against a readonly slot.
     */
    public function withBot(?Bot $bot): static
    {
        return clone($this, ['bot' => $bot]);
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
