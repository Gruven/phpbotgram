<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Gruven\PhpBotGram\Bot;
use ReflectionObject;
use ReflectionProperty;

abstract class BotContextController
{
  public function __construct(public readonly ?Bot $bot = null) {}

  /**
   * Returns a clone of $this with $bot rebound recursively. Walks every public
   * property; nested `BotContextController` instances are rebound via their
   * own `withBot`, arrays/lists of them are walked element-wise. Plain values
   * (scalars, DateTime, enums, InputFile etc.) pass through untouched.
   *
   * Mirrors upstream pydantic `model_validate(context={"bot": bot})` (aiogram
   * `ContextController.as_`/`model_dump_json`+`model_validate`). PHP 8.5's
   * `clone($this, [...])` clone-with syntax permits the base method to rewrite
   * `public readonly` slots declared anywhere in the inheritance chain — the
   * scope check is on the *caller* and `public readonly` is publicly writable
   * via clone-with from any caller.
   */
  public function withBot(?Bot $bot): static
  {
    $overrides = ['bot' => $bot];

    foreach ((new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
      if ($prop->isStatic() || $prop->getName() === 'bot') {
        continue;
      }

      $name = $prop->getName();
      $value = $prop->getValue($this);

      if ($value instanceof self) {
        $overrides[$name] = $value->withBot($bot);

        continue;
      }

      if (is_array($value)) {
        $rebound = [];
        $touched = false;

        foreach ($value as $k => $item) {
          if ($item instanceof self) {
            $rebound[$k] = $item->withBot($bot);
            $touched = true;
          } else {
            $rebound[$k] = $item;
          }
        }

        if ($touched) {
          $overrides[$name] = $rebound;
        }
      }
    }

    return clone ($this, $overrides);
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
