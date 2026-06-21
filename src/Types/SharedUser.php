<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about a user that was shared with the bot using a KeyboardButtonRequestUsers button.
 *
 * Source: https://core.telegram.org/bots/api#shareduser
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class SharedUser extends TelegramObject
{
  /**
   * @param null|list<PhotoSize> $photo
   */
  public function __construct(
    public readonly int $userId,
    public readonly ?string $firstName = null,
    public readonly ?string $lastName = null,
    public readonly ?string $username = null,
    public readonly ?array $photo = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
