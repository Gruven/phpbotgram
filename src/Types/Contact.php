<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a phone contact.
 *
 * Source: https://core.telegram.org/bots/api#contact
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class Contact extends TelegramObject
{
  public function __construct(
    public readonly string $phoneNumber,
    public readonly string $firstName,
    public readonly ?string $lastName = null,
    public readonly ?int $userId = null,
    public readonly ?string $vcard = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
