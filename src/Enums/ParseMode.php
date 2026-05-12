<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * Formatting options
 *
 * Source: https://core.telegram.org/bots/api#formatting-options
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ParseMode: string
{
  case MarkdownV2 = 'MarkdownV2';
  case Markdown = 'Markdown';
  case Html = 'HTML';
}
