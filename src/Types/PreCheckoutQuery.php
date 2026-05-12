<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\AnswerPreCheckoutQuery;

/**
 * This object contains information about an incoming pre-checkout query.
 *
 * Source: https://core.telegram.org/bots/api#precheckoutquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PreCheckoutQuery extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  public function __construct(
    public readonly string $id,
    public readonly User $fromUser,
    public readonly string $currency,
    public readonly int $totalAmount,
    public readonly string $invoicePayload,
    public readonly ?string $shippingOptionId = null,
    public readonly ?OrderInfo $orderInfo = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
  public function answer(
    bool $ok,
    ?string $errorMessage = null,
  ): AnswerPreCheckoutQuery {
    return new AnswerPreCheckoutQuery(
      preCheckoutQueryId: $this->id,
      ok: $ok,
      errorMessage: $errorMessage,
      bot: $this->bot,
    );
  }
}
