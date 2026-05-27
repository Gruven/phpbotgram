<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The paid media isn't available before the payment.
 *
 * Source: https://core.telegram.org/bots/api#paidmediapreview
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class PaidMediaPreview extends PaidMedia
{
  public function __construct(
    public readonly string $type = 'preview',
    public readonly ?int $width = null,
    public readonly ?int $height = null,
    public readonly ?int $duration = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
