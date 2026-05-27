<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;

/**
 * Top-level `F` constant — a fresh root `MagicFilter` users import to
 * build fluent predicate chains.
 *
 * Usage:
 *
 *     use const Gruven\PhpBotGram\F;
 *
 *     $filter = (F->message->text->equals('hello'))->asFilter();
 *
 * Direct PHP equivalent of `from aiogram import F` in upstream Python.
 * Available because PHP 8.5 added object-initializer support to
 * namespace-level `const` declarations — see the RFC "New in initializers"
 * (PHP 8.1) extended to top-level `const` in PHP 8.5.
 *
 * The constant is loaded eagerly via the `composer.json` `autoload.files`
 * entry — PSR-4 only autoloads class symbols, so a standalone `const`
 * file must be force-loaded by Composer. Each call site sees the same
 * `MagicFilter` instance because `const` is process-wide, but every
 * chain operation (`F->message`, `F->text`, …) clones into a new
 * `MagicFilter`, so the shared root is never mutated.
 */
const F = new MagicFilter();
