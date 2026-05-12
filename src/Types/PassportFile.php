<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * This object represents a file uploaded to Telegram Passport. Currently all Telegram Passport files are in JPEG format when decrypted and don't exceed 10MB.
 *
 * Source: https://core.telegram.org/bots/api#passportfile
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PassportFile extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly int $fileSize,
    public readonly DateTime $fileDate,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
