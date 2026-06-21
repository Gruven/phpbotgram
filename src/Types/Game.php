<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a game. Use BotFather to create and edit games, their short names will act as unique identifiers.
 *
 * Source: https://core.telegram.org/bots/api#game
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Game extends TelegramObject
{
  /**
   * @param list<PhotoSize> $photo
   * @param null|list<MessageEntity> $textEntities
   */
  public function __construct(
    public readonly string $title,
    public readonly string $description,
    public readonly array $photo,
    public readonly ?string $text = null,
    public readonly ?array $textEntities = null,
    public readonly ?Animation $animation = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
