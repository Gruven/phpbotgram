<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes a rich message to be sent. Exactly one of the fields html or markdown must be used.
 *
 * Source: https://core.telegram.org/bots/api#inputrichmessage
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputRichMessage extends TelegramObject
{
  public function __construct(
    public readonly ?string $html = null,
    public readonly ?string $markdown = null,
    public readonly ?bool $isRtl = null,
    public readonly ?bool $skipEntityDetection = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
