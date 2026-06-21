<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A custom emoji.
 *
 * Source: https://core.telegram.org/bots/api#richtextcustomemoji
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextCustomEmoji extends RichText
{
  public function __construct(
    public readonly string $customEmojiId,
    public readonly string $alternativeText,
    public readonly string $type = 'custom_emoji',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
