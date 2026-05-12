<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the content of an invoice message to be sent as the result of an inline query.
 *
 * Source: https://core.telegram.org/bots/api#inputinvoicemessagecontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputInvoiceMessageContent extends InputMessageContent
{
  /**
   * @param list<LabeledPrice> $prices
   * @param list<int> $suggestedTipAmounts
   */
  public function __construct(
    public readonly string $title,
    public readonly string $description,
    public readonly string $payload,
    public readonly ?string $providerToken,
    public readonly string $currency,
    public readonly array $prices,
    public readonly ?int $maxTipAmount = null,
    public readonly ?array $suggestedTipAmounts = null,
    public readonly ?string $providerData = null,
    public readonly ?string $photoUrl = null,
    public readonly ?int $photoSize = null,
    public readonly ?int $photoWidth = null,
    public readonly ?int $photoHeight = null,
    public readonly ?bool $needName = null,
    public readonly ?bool $needPhoneNumber = null,
    public readonly ?bool $needEmail = null,
    public readonly ?bool $needShippingAddress = null,
    public readonly ?bool $sendPhoneNumberToProvider = null,
    public readonly ?bool $sendEmailToProvider = null,
    public readonly ?bool $isFlexible = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
