<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a passport element error type.
 *
 * Source: https://core.telegram.org/bots/api#passportelementerror
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum PassportElementErrorType: string
{
  case Data = 'data';
  case FrontSide = 'front_side';
  case ReverseSide = 'reverse_side';
  case Selfie = 'selfie';
  case File = 'file';
  case Files = 'files';
  case TranslationFile = 'translation_file';
  case TranslationFiles = 'translation_files';
  case Unspecified = 'unspecified';
}
