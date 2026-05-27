<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

/**
 * Empty-extension alias for {@see Filter} mirroring upstream's
 * `aiogram.filters.base.BaseFilter` import path (`aiogram/filters/base.py:9`).
 *
 * User code that idiomatically ports `from aiogram.filters import BaseFilter`
 * and writes `class MyFilter(BaseFilter)` can do the same here with
 * `class MyFilter extends BaseFilter` — both `Filter` and `BaseFilter` are
 * interchangeable as the abstract supertype, because `BaseFilter extends
 * Filter` and adds nothing.
 *
 * # Why a real subclass rather than `class_alias`
 *
 * A `class_alias(Filter::class, BaseFilter::class)` call would only run when
 * its containing file is loaded by the autoloader. PSR-4 won't load a file
 * that doesn't declare the expected symbol, so a file with ONLY a
 * `class_alias` body never executes and `BaseFilter` stays unresolved.
 *
 * Declaring `BaseFilter` as a real (empty) `abstract class` extending `Filter`
 * sidesteps that limitation entirely: PSR-4 loads the file because the class
 * name matches, and the resulting symbol passes `is_subclass_of(BaseFilter,
 * Filter)` for free. Subclasses written against either supertype work
 * identically under the dispatcher's `Filter $filter` type narrowing.
 */
abstract class BaseFilter extends Filter {}
