<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes documents or other Telegram Passport elements shared with the bot by the user.
 *
 * Source: https://core.telegram.org/bots/api#encryptedpassportelement
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class EncryptedPassportElement extends TelegramObject
{
  /**
   * @param list<PassportFile> $files
   * @param list<PassportFile> $translation
   */
  public function __construct(
    public readonly string $type,
    public readonly string $hash,
    public readonly ?string $data = null,
    public readonly ?string $phoneNumber = null,
    public readonly ?string $email = null,
    public readonly ?array $files = null,
    public readonly ?PassportFile $frontSide = null,
    public readonly ?PassportFile $reverseSide = null,
    public readonly ?PassportFile $selfie = null,
    public readonly ?array $translation = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
