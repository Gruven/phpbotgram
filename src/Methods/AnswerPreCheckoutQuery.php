<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Once the user has confirmed their payment and shipping details, the Bot API sends the final confirmation in the form of an Update with the field pre_checkout_query. Use this method to respond to such pre-checkout queries. On success, True is returned. Note: The Bot API must receive an answer within 10 seconds after the pre-checkout query was sent.
 *
 * Source: https://core.telegram.org/bots/api#answerprecheckoutquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class AnswerPreCheckoutQuery extends TelegramMethod
{
  public const string ApiMethod = 'answerPreCheckoutQuery';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $preCheckoutQueryId,
    public readonly bool $ok,
    public readonly ?string $errorMessage = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
