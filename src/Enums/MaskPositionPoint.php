<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * The part of the face relative to which the mask should be placed.
 *
 * Source: https://core.telegram.org/bots/api#maskposition
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum MaskPositionPoint: string
{
  case Forehead = 'forehead';
  case Eyes = 'eyes';
  case Mouth = 'mouth';
  case Chin = 'chin';
}
