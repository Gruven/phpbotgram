<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an inline keyboard button that copies specified text to the clipboard.
 *
 * Source: https://core.telegram.org/bots/api#copytextbutton
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class CopyTextButton extends TelegramObject
{
  public function __construct(
    public readonly string $text,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
