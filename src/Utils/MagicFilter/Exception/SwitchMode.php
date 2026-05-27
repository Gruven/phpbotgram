<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Exception;

/**
 * Control-flow exception family used internally by GetItem-style operations
 * to switch the resolver into "all" or "any" mode over an iterable subject.
 *
 * Mirrors upstream `magic_filter.exceptions.SwitchMode` — a marker subclass
 * of `MagicFilterException` whose concrete subclasses (`SwitchModeToAll`,
 * `SwitchModeToAny`) signal the resolver to fan out the remaining operation
 * chain across every element of the current value.
 *
 * This is `internal` flow control, never propagated to user code: the
 * `MagicFilter::resolve` loop catches these and recurses on each item.
 */
class SwitchMode extends MagicFilterException {}
