<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Enums\EncryptedPassportElement;

/**
 * Describes Telegram Passport data shared with the bot by the user.
 *
 * Source: https://core.telegram.org/bots/api#passportdata
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportData extends TelegramObject
{
  /**
   * @param list<EncryptedPassportElement> $data
   */
  public function __construct(
    public readonly array $data,
    public readonly EncryptedCredentials $credentials,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
