<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes data required for decrypting and authenticating EncryptedPassportElement. See the Telegram Passport Documentation for a complete description of the data decryption and authentication processes.
 *
 * Source: https://core.telegram.org/bots/api#encryptedcredentials
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class EncryptedCredentials extends TelegramObject
{
  public function __construct(
    public readonly string $data,
    public readonly string $hash,
    public readonly string $secret,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
