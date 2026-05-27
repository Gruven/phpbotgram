<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\OwnedGifts;

/**
 * Returns the gifts owned by a chat. Returns OwnedGifts on success.
 *
 * Source: https://core.telegram.org/bots/api#getchatgifts
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<OwnedGifts>
 */
final class GetChatGifts extends TelegramMethod
{
  public const string ApiMethod = 'getChatGifts';
  public const string ReturnsType = OwnedGifts::class;

  public function __construct(
    public readonly int|string $chatId,
    public readonly ?bool $excludeUnsaved = null,
    public readonly ?bool $excludeSaved = null,
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
