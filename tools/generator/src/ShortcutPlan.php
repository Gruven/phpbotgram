<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Per-alias shortcut plan emitted by `ShortcutDetector`.
 *
 * Captures the structured per-method shortcut grammar lowered from a type's
 * `aliases.yml`: the owner type the shortcut hangs off (`Message`, `User`,
 * `CallbackQuery`, …), the wire alias name (snake_case, e.g.
 * `get_profile_photos`), the camelCased PHP method name the renderer emits
 * (e.g. `getProfilePhotos`), and the target TelegramMethod that gets
 * instantiated under the hood (e.g. `getUserProfilePhotos`).
 *
 * The `$fill` map carries the auto-supplied constructor arguments expressed as
 * unresolved navigation paths (`self.id`, `self.chat.id`, `self.from_user.id`,
 * `self.as_reply_parameters()`). Lowering these to PHP property accesses or
 * method calls is the renderer's job (Task 2.10) — this stage preserves the
 * expressions verbatim so the renderer can keep the wire grammar close to the
 * upstream butcher source for diffing.
 *
 * `$ignore` lists wire param names hidden from the alias signature (Telegram's
 * `Message.reply` shortcut hides `reply_to_message_id` because the alias
 * auto-supplies `reply_parameters` instead).
 *
 * `$argOverrides` is currently unused by any vendored fixture (Telegram 10.0
 * never overrides an alias param's type) — it ships as a forward-compat slot
 * matching the documented grammar, kept as a typed empty list to avoid a
 * later signature change.
 */
final readonly class ShortcutPlan
{
  /**
   * @param array<string, string> $fill param => expression like 'self.id' or 'self.chat.id'
   * @param list<string> $ignore wire param names hidden from the alias signature
   * @param array<string, array{type?: string}> $argOverrides per-param overrides; rare
   */
  public function __construct(
    public string $ownerTypeName,
    public string $aliasName,
    public string $phpMethodName,
    public string $methodEntityName,
    public array $fill,
    public array $ignore,
    public ?string $description = null,
    public array $argOverrides = [],
  ) {}
}
