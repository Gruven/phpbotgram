<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BotShortDescription;

/**
 * Use this method to get the current bot short description for the given user language. Returns BotShortDescription on success.
 *
 * Source: https://core.telegram.org/bots/api#getmyshortdescription
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<BotShortDescription>
 */
final class GetMyShortDescription extends TelegramMethod
{
  public const string ApiMethod = 'getMyShortDescription';
  public const string ReturnsType = BotShortDescription::class;

  public function __construct(
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
