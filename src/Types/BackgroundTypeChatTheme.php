<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The background is taken directly from a built-in chat theme.
 *
 * Source: https://core.telegram.org/bots/api#backgroundtypechattheme
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundTypeChatTheme extends BackgroundType
{
  public function __construct(
    public readonly string $themeName,
    public readonly string $type = 'chat_theme',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
