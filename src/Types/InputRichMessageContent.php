<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents the content of a rich message to be sent as the result of an inline query.
 *
 * Source: https://core.telegram.org/bots/api#inputrichmessagecontent
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputRichMessageContent extends InputMessageContent
{
  public function __construct(
    public readonly InputRichMessage $richMessage,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
