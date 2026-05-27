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
   * own `withBot`, arrays (including nested arrays of arbitrary depth — e.g.
   * `list<list<KeyboardButton>>`) are walked element-wise. Plain values
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
        [$rebound, $touched] = self::rebindArray($value, $bot);

        if ($touched) {
          $overrides[$name] = $rebound;
        }
      }
    }

    return clone ($this, $overrides);
  }

  /**
   * Walks an array recursively, rebinding every `BotContextController` leaf to $bot.
   * Returns the (possibly) new array and a flag indicating whether any leaf was rebound,
   * so the caller can skip the override when the array contains no controllers.
   *
   * @param array<array-key, mixed> $value
   *
   * @return array{0: array<array-key, mixed>, 1: bool}
   */
  private static function rebindArray(array $value, ?Bot $bot): array
  {
    $rebound = [];
    $touched = false;

    foreach ($value as $k => $item) {
      if ($item instanceof self) {
        $rebound[$k] = $item->withBot($bot);
        $touched = true;
      } elseif (is_array($item)) {
        [$inner, $innerTouched] = self::rebindArray($item, $bot);
        $rebound[$k] = $inner;
        $touched = $touched || $innerTouched;
      } else {
        $rebound[$k] = $item;
      }
    }

    return [$rebound, $touched];
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
