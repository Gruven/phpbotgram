<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\LabeledPrice;

/**
 * Use this method to create a link for an invoice. Returns the created invoice link as String on success.
 *
 * Source: https://core.telegram.org/bots/api#createinvoicelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<string>
 */
final class CreateInvoiceLink extends TelegramMethod
{
  public const string ApiMethod = 'createInvoiceLink';
  public const string ReturnsType = 'string';

  public function __construct(
    public readonly string $title,
    public readonly string $description,
    public readonly string $payload,
    public readonly string $currency,
    /** @var list<LabeledPrice> */
    public readonly array $prices,
    public readonly ?string $businessConnectionId = null,
    public readonly ?string $providerToken = null,
    public readonly ?int $subscriptionPeriod = null,
    public readonly ?int $maxTipAmount = null,
    /** @var list<int> */
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
