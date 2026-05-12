<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object contains information about a paid media purchase.
 *
 * Source: https://core.telegram.org/bots/api#paidmediapurchased
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMediaPurchased extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  public function __construct(
    public readonly User $fromUser,
    public readonly string $paidMediaPayload,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
