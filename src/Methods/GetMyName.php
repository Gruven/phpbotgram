<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BotName;

/**
 * Use this method to get the current bot name for the given user language. Returns BotName on success.
 *
 * Source: https://core.telegram.org/bots/api#getmyname
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<BotName>
 */
final class GetMyName extends TelegramMethod
{
  public const string ApiMethod = 'getMyName';
  public const string ReturnsType = BotName::class;

  public function __construct(
    public readonly ?string $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
