<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Sticker;

/**
 * Use this method to get custom emoji stickers, which can be used as a forum topic icon by any user. Requires no parameters. Returns an Array of Sticker objects.
 *
 * Source: https://core.telegram.org/bots/api#getforumtopiciconstickers
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<Sticker>>
 */
final class GetForumTopicIconStickers extends TelegramMethod
{
  public const string ApiMethod = 'getForumTopicIconStickers';
  public const string ReturnsType = 'list:Sticker';

  public function __construct(?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
