<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BotDescription;

/**
 * Use this method to get the current bot description for the given user language. Returns BotDescription on success.
 *
 * Source: https://core.telegram.org/bots/api#getmydescription
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<BotDescription>
 */
final class GetMyDescription extends TelegramMethod
{
  public const string ApiMethod = 'getMyDescription';
  public const string ReturnsType = BotDescription::class;

  public function __construct(
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
