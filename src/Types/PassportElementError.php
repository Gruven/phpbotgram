<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents an error in the Telegram Passport element which was submitted that should be resolved by the user. It should be one of:
 *  - PassportElementErrorDataField
 *  - PassportElementErrorFrontSide
 *  - PassportElementErrorReverseSide
 *  - PassportElementErrorSelfie
 *  - PassportElementErrorFile
 *  - PassportElementErrorFiles
 *  - PassportElementErrorTranslationFile
 *  - PassportElementErrorTranslationFiles
 *  - PassportElementErrorUnspecified
 *
 * Source: https://core.telegram.org/bots/api#passportelementerror
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class PassportElementError extends MutableTelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
