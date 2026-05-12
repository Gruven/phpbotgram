<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Shortcuts;

use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\ReplyParameters;

/**
 * Hand-authored shortcut helpers for `InaccessibleMessage`.
 *
 * Loaded by `HandAuthoredShortcutsIntegrator` (Phase 2 codegen stage 8) and
 * stitched into the regenerated `InaccessibleMessage` class via a
 * `use InaccessibleMessageShortcuts;` directive. Provides the same
 * `asReplyParameters()` helper as `MessageShortcuts` — both sides of the
 * `MaybeInaccessibleMessage` union share the contract so the schema's
 * `reply_*` aliases compile to the same `self.as_reply_parameters()`
 * call regardless of which subtype the caller holds.
 *
 * @property int $messageId promoted property on the using class
 * @property Chat $chat promoted property on the using class
 */
trait InaccessibleMessageShortcuts
{
  /**
   * Build a `ReplyParameters` referencing this inaccessible-message stub.
   *
   * For a deleted/inaccessible message the runtime API still accepts a
   * `reply_parameters` payload — the resulting send will fail server-side
   * if the underlying message is truly unreachable, but the construction
   * path stays uniform across `Message` and `InaccessibleMessage`.
   */
  public function asReplyParameters(): ReplyParameters
  {
    return new ReplyParameters(
      messageId: $this->messageId,
      chatId: $this->chat->id,
    );
  }
}
