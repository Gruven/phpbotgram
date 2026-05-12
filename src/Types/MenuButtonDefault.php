<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes that no specific value for the menu button was set.
 *
 * Source: https://core.telegram.org/bots/api#menubuttondefault
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MenuButtonDefault extends MenuButton
{
  public function __construct(
    public readonly string $type = 'default',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
