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
   * `ContextController.as_`/`model_dump_json`+`model_validate`).
   *
   * Scope note: PHP 8.5 treats `public readonly` as effectively
   * `public protected(set) readonly` for clone-with — only code running with
   * a scope in the property's declaring class hierarchy (declaring class plus
   * its ancestors and descendants) can use `clone($obj, ['x' => ...])` against
   * it. Because this method lives on `BotContextController` and every
   * TelegramObject/TelegramMethod subclass extends it, the walker's
   * `clone($this, [...])` call legally rewrites subclass-declared readonly
   * slots like `Message::$chat`. External callers cannot use the same syntax
   * — they must funnel through this method.
   *
   * Limitation: arrays of arrays of controllers (`list<list<TelegramObject>>`)
   * are NOT walked recursively into the nested arrays; only the first array
   * dimension is. Telegram's wire format rarely emits such shapes, but if a
   * future generated type needs deep array rebinding it must override this.
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
