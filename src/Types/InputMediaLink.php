<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents an HTTP link to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputmedialink
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputMediaLink extends InputPollOptionMedia
{
  public function __construct(
    public readonly string $url,
    public readonly string $type = 'link',
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
