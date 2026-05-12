<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Returns the gifts received and owned by a managed business account. Requires the can_view_gifts_and_stars business bot right. Returns OwnedGifts on success.
 *
 * Source: https://core.telegram.org/bots/api#getbusinessaccountgifts
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class GetBusinessAccountGifts extends TelegramMethod
{
  public const string ApiMethod = 'getBusinessAccountGifts';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly ?bool $excludeUnsaved = null,
    public readonly ?bool $excludeSaved = null,
    public readonly ?bool $excludeUnlimited = null,
    public readonly ?bool $excludeLimitedUpgradable = null,
    public readonly ?bool $excludeLimitedNonUpgradable = null,
    public readonly ?bool $excludeUnique = null,
    public readonly ?bool $excludeFromBlockchain = null,
    public readonly ?bool $sortByPrice = null,
    public readonly ?string $offset = null,
    public readonly ?int $limit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
