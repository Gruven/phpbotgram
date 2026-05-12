<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a menu button, which launches a Web App.
 *
 * Source: https://core.telegram.org/bots/api#menubuttonwebapp
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MenuButtonWebApp extends MenuButton
{
  public function __construct(
    public readonly string $type,
    public readonly string $text,
    public readonly WebAppInfo $webApp,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
