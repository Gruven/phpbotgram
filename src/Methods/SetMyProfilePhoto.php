<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputProfilePhoto;

/**
 * Changes the profile photo of the bot. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setmyprofilephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetMyProfilePhoto extends TelegramMethod
{
  public const string ApiMethod = 'setMyProfilePhoto';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly InputProfilePhoto $photo,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
