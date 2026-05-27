<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Exception;

/**
 * Signals the resolver to evaluate the remaining operations against every
 * element of the current iterable value and accept only when ALL succeed.
 *
 * Direct port of upstream `magic_filter.exceptions.SwitchModeToAll`
 * (`magic_filter/exceptions.py:9-11`). Raised by `GetItemOperation` when
 * the user requests `F[:]` (empty slice) — interpreted as "fan out".
 *
 * Upstream carries the original slice on `.key`; PHP slicing has no
 * equivalent literal, so the carried value is the marker that was supplied
 * to `__invoke` / `offsetGet` (typically the empty-slice sentinel). Stored
 * `mixed` for forward compatibility.
 */
final class SwitchModeToAll extends SwitchMode
{
  public function __construct(public readonly mixed $key) {}
}
