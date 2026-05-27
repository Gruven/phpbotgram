<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\ShippingOption;

/**
 * If you sent an invoice requesting a shipping address and the parameter is_flexible was specified, the Bot API will send an Update with a shipping_query field to the bot. Use this method to reply to shipping queries. On success, True is returned.
 *
 * Source: https://core.telegram.org/bots/api#answershippingquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class AnswerShippingQuery extends TelegramMethod
{
  public const string ApiMethod = 'answerShippingQuery';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $shippingQueryId,
    public readonly bool $ok,
    /** @var list<ShippingOption> */
    public readonly ?array $shippingOptions = null,
    public readonly ?string $errorMessage = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
