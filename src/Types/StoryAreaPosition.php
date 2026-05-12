<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Describes the position of a clickable area within a story.
 *
 * Source: https://core.telegram.org/bots/api#storyareaposition
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class StoryAreaPosition extends TelegramObject
{
  public function __construct(
    public readonly float $xPercentage,
    public readonly float $yPercentage,
    public readonly float $widthPercentage,
    public readonly float $heightPercentage,
    public readonly float $rotationAngle,
    public readonly float $cornerRadiusPercentage,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
