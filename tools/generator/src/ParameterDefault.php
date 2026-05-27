<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Per-parameter default expression emitted by `DefaultsResolver`.
 *
 * Captures the constructor-default expression the renderer must paste
 * verbatim after the `=` for a given method parameter:
 *
 *   - `new BotDefault('parse_mode')` — when the wire param is listed in the
 *     method's `default.yml` (annotated as a BotDefault sentinel).
 *   - `new BotDefault('link_preview_is_disabled')` — when `default.yml`
 *     renames the wire param to a different BotDefault sentinel
 *     (the rare `disable_web_page_preview -> link_preview_is_disabled` case).
 *   - `null` — when the annotation is `required: false` but not listed in
 *     `default.yml` (plain nullable optional).
 *
 * Required-true annotations that are NOT in `default.yml` do not produce a
 * `ParameterDefault` at all — the renderer emits them without an `=` clause.
 *
 * `$isBotDefault` distinguishes the two emit paths cheaply for the renderer:
 *   - true  -> use the `new BotDefault(...)` expression and import the class.
 *   - false -> emit `= null` and skip the import.
 */
final readonly class ParameterDefault
{
  public function __construct(
    public string $methodName,
    public string $wireParamName,
    public string $expression,
    public bool $isBotDefault,
  ) {}
}
