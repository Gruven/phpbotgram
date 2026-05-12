<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

/**
 * Thrown when `Token::validate` rejects an input. Mirrors aiogram's
 * `aiogram.utils.token.TokenValidationError`. Catch this specifically
 * to distinguish credential-format failures from other PhpBotGramException.
 */
final class TokenValidationException extends PhpBotGramException {}
