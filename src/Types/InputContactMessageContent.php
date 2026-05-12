<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the content of a contact message to be sent as the result of an inline query.
 *
 * Source: https://core.telegram.org/bots/api#inputcontactmessagecontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputContactMessageContent extends InputMessageContent
{
  public function __construct(
    public readonly string $phoneNumber,
    public readonly string $firstName,
    public readonly ?string $lastName = null,
    public readonly ?string $vcard = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
