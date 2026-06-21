<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * A bot command.
 *
 * Source: https://core.telegram.org/bots/api#richtextbotcommand
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichTextBotCommand extends RichText
{
  /**
   * @param list<array<array-key,mixed>|RichText|string>|RichText|string $text
   */
  public function __construct(
    public readonly array|RichText|string $text,
    public readonly string $botCommand,
    public readonly string $type = 'bot_command',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
