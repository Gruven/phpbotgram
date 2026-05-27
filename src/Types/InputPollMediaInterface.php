<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

/**
 * Marker interface for the {@see InputPollMedia} union.
 *
 * Implemented by every member of the InputPollMedia union — directly
 * (the canonical abstract parent class declares `implements InputPollMediaInterface`)
 * or via the additional-union-membership channel for multi-parent members
 * (e.g. an `InputMediaPhoto` belongs to three unions but PHP can only
 * `extends` one; the other two memberships are declared with
 * `implements <X>Interface`). Use this type when typing a property or
 * parameter that must admit every member of the union, including those
 * whose PHP `extends` chain points elsewhere.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
interface InputPollMediaInterface {}
