<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Exception;

/**
 * Signals the resolver to evaluate the remaining operations against every
 * element of the current iterable value and accept when ANY succeeds.
 *
 * Direct port of upstream `magic_filter.exceptions.SwitchModeToAny`
 * (`magic_filter/exceptions.py:14-15`). Raised by `GetItemOperation` when
 * the user requests `F[...]` (Ellipsis) — interpreted as "fan out, OR".
 */
final class SwitchModeToAny extends SwitchMode {}
