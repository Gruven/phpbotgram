<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\PassportElementError;

/**
 * Informs a user that some of the Telegram Passport elements they provided contains errors. The user will not be able to re-submit their Passport to you until the errors are fixed (the contents of the field for which you returned the error must change). Returns True on success.
 * Use this if the data submitted by the user doesn't satisfy the standards your service requires for any reason. For example, if a birthday date seems invalid, a submitted document is blurry, a scan shows evidence of tampering, etc. Supply some details in the error message to make sure the user knows how to correct the issues.
 *
 * Source: https://core.telegram.org/bots/api#setpassportdataerrors
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetPassportDataErrors extends TelegramMethod
{
  public const string ApiMethod = 'setPassportDataErrors';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly int $userId,
    /** @var list<PassportElementError> */
    public readonly array $errors,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
