<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

/**
 * Non-readonly parent for the small set of schema types whose `replace.yml`
 * carries `bases: [MutableTelegramObject]` — currently 16 entities, primarily
 * keyboard/menu/input-media builders that need post-construction mutation.
 * Hand-authored builder classes (`Utils\Keyboard\InlineKeyboardBuilder`, etc.)
 * also extend it directly. NOT abstract — matches upstream `aiogram/types/base.py:38-41`.
 * See spec § "Mutable type variant" and § "TypeOverrideApplier".
 */
class MutableTelegramObject extends TelegramObject
{
}
