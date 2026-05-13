<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Exception;

use RuntimeException;

/**
 * Base for every exception thrown from the magic-filter runtime.
 * Mirrors upstream `magic_filter.exceptions.MagicFilterException`
 * (`magic_filter/exceptions.py:1-2`).
 *
 * Extends `RuntimeException` so consumers can either catch the family
 * via this class or fall through to PHP's standard exception hierarchy.
 */
class MagicFilterException extends RuntimeException {}
