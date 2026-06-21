<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the content of a message to be sent as a result of an inline query. Telegram clients currently support the following types:
 *  - InputTextMessageContent
 *  - InputRichMessageContent
 *  - InputLocationMessageContent
 *  - InputVenueMessageContent
 *  - InputContactMessageContent
 *  - InputInvoiceMessageContent
 *
 * Source: https://core.telegram.org/bots/api#inputmessagecontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class InputMessageContent extends MutableTelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
