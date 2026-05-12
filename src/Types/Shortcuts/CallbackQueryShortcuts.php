<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Shortcuts;

use Gruven\PhpBotGram\Types\InaccessibleMessage;
use Gruven\PhpBotGram\Types\MaybeInaccessibleMessage;
use Gruven\PhpBotGram\Types\Message;

/**
 * Hand-authored shortcut helpers for `CallbackQuery`.
 *
 * Loaded by `HandAuthoredShortcutsIntegrator` (Phase 2 codegen stage 8)
 * and stitched into the regenerated `CallbackQuery` class via a
 * `use CallbackQueryShortcuts;` directive.
 *
 * Mirrors aiogram's `CallbackQuery.message_id` shortcut. The `message`
 * property is a `MaybeInaccessibleMessage` union — either a full
 * `Message` or an `InaccessibleMessage` stub — and the helper unwraps
 * the `messageId` of whichever side is present, or returns `null` when
 * there's no underlying message at all (a callback raised from an inline
 * query carries `inline_message_id` instead).
 *
 * The abstract `MaybeInaccessibleMessage` base doesn't carry `messageId`
 * itself (the lift would require the codegen to materialise the shared
 * field on the parent, which would shadow each subtype's promoted
 * readonly property), so the helper narrows the union with `instanceof`
 * before accessing the property. Both subtypes carry the field
 * unconditionally so the narrowing is total.
 *
 * @property null|MaybeInaccessibleMessage $message promoted property on the using class
 */
trait CallbackQueryShortcuts
{
  public function messageId(): ?int
  {
    return match (true) {
      $this->message instanceof Message => $this->message->messageId,
      $this->message instanceof InaccessibleMessage => $this->message->messageId,
      default => null,
    };
  }
}
