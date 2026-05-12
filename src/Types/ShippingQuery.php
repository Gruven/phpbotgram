<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\AnswerShippingQuery;

/**
 * This object contains information about an incoming shipping query.
 *
 * Source: https://core.telegram.org/bots/api#shippingquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ShippingQuery extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  public function __construct(
    public readonly string $id,
    public readonly User $fromUser,
    public readonly string $invoicePayload,
    public readonly ShippingAddress $shippingAddress,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }

  /**
   * @param null|list<ShippingOption> $shippingOptions
   */
  public function answer(
    bool $ok,
    ?array $shippingOptions = null,
    ?string $errorMessage = null,
  ): AnswerShippingQuery {
    return new AnswerShippingQuery(
      shippingQueryId: $this->id,
      ok: $ok,
      shippingOptions: $shippingOptions,
      errorMessage: $errorMessage,
      bot: $this->bot,
    );
  }
}
