<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * The background is a .PNG or .TGV (gzipped subset of SVG with MIME type 'application/x-tgwallpattern') pattern to be combined with the background fill chosen by the user.
 *
 * Source: https://core.telegram.org/bots/api#backgroundtypepattern
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class BackgroundTypePattern extends BackgroundType
{
  public function __construct(
    public readonly string $type,
    public readonly Document $document,
    public readonly BackgroundFill $fill,
    public readonly int $intensity,
    public readonly ?bool $isInverted = null,
    public readonly ?bool $isMoving = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
