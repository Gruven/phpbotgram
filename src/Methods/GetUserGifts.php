<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\OwnedGifts;

/**
 * Returns the gifts owned and hosted by a user. Returns OwnedGifts on success.
 *
 * Source: https://core.telegram.org/bots/api#getusergifts
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<OwnedGifts>
 */
final class GetUserGifts extends TelegramMethod
{
  public const string ApiMethod = 'getUserGifts';
  public const string ReturnsType = OwnedGifts::class;

  public function __construct(
    public readonly int $userId,
    public readonly ?bool $excludeUnlimited = null,
    public readonly ?bool $excludeLimitedUpgradable = null,
    public readonly ?bool $excludeLimitedNonUpgradable = null,
    public readonly ?bool $excludeFromBlockchain = null,
    public readonly ?bool $excludeUnique = null,
    public readonly ?bool $sortByPrice = null,
    public readonly ?string $offset = null,
    public readonly ?int $limit = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
