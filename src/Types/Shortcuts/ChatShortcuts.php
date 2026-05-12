<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Shortcuts;

/**
 * Hand-authored shortcut helpers for `Chat`.
 *
 * Loaded by `HandAuthoredShortcutsIntegrator` (Phase 2 codegen stage 8)
 * and stitched into the regenerated `Chat` class via a
 * `use ChatShortcuts;` directive.
 *
 * Mirrors aiogram's `Chat.full_name` accessor. Private (1:1 user) chats
 * carry `firstName` / `lastName` like a `User`; everything else
 * (`group`, `supergroup`, `channel`, `direct_messages`) uses the chat
 * `title`. Returns the empty string when neither path yields a value.
 *
 * @property string $type promoted property on the using class
 * @property null|string $title promoted property on the using class
 * @property null|string $firstName promoted property on the using class
 * @property null|string $lastName promoted property on the using class
 */
trait ChatShortcuts
{
  public function fullName(): string
  {
    if ($this->type === 'private') {
      $first = $this->firstName ?? '';

      if ($this->lastName !== null && $this->lastName !== '') {
        return $first === '' ? $this->lastName : $first . ' ' . $this->lastName;
      }

      return $first;
    }

    return $this->title ?? '';
  }
}
