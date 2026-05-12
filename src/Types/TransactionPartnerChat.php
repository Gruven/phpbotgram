<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a transaction with a chat.
 *
 * Source: https://core.telegram.org/bots/api#transactionpartnerchat
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerChat extends TransactionPartner
{
  public function __construct(
    public readonly Chat $chat,
    public readonly string $type = 'chat',
    public readonly ?Gift $gift = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
