<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Exception;

/**
 * User-facing error: a caller passed mutually-exclusive options to a
 * MagicFilter builder method (for example `regexp(mode=…, search=…)` —
 * both at once is meaningless).
 *
 * Mirrors upstream `magic_filter.exceptions.ParamsConflict`
 * (`magic_filter/exceptions.py:22-23`).
 */
final class ParamsConflict extends MagicFilterException {}
