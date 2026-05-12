<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents poll type
 *
 * Source: https://core.telegram.org/bots/api#poll
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum PollType: string
{
  case Regular = 'regular';
  case Quiz = 'quiz';
}
