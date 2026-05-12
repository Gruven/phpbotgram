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
    public readonly ?string $data,
    public readonly ?string $phoneNumber,
    public readonly ?string $email,
    public readonly ?array $files,
    public readonly ?PassportFile $frontSide,
    public readonly ?PassportFile $reverseSide,
    public readonly ?PassportFile $selfie,
    public readonly ?array $translation,
    public readonly string $hash,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
