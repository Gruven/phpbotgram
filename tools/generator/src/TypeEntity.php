<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * One concrete or abstract Telegram type as resolved from
 * `.butcher/schema/schema.json` plus per-type patch files
 * (`replace.yml`, `aliases.yml`, `default.yml`, `subtypes.yml`).
 *
 * - `$bases` is non-null only when `replace.yml` ships a top-level `bases:` list
 *   (mutable lifts, custom-parent overrides). Otherwise the downstream emitter
 *   falls back to the default `TelegramObject` parent.
 * - `$aliases` carries the **raw** parsed YAML from `aliases.yml`; lowering to
 *   PHP is the responsibility of Task 2.7 (ShortcutDetector).
 * - `$subtypes`/`$discriminator` are populated for union **parents** (types
 *   that ship a `subtypes.yml`); subtype names are parsed from the parent's
 *   description bullet-list.
 * - `$extraUnionItems` carries scalar/list alternatives from
 *   `subtypes.yml.extra_items` for union parents whose declared union is not
 *   made only of schema child types. Bot API 10.1 uses this for `RichText`,
 *   whose wire shape is `String | Array of RichText | RichText*`.
 * - `$subtypeOf` is populated for union **children** with the canonical
 *   parent name (the PHP-level `extends` parent). For multi-parent children
 *   (e.g. `InputMediaPhoto` belongs to `InputMedia`, `InputPollMedia`, AND
 *   `InputPollOptionMedia`), the canonical parent is picked by name-prefix
 *   rule; the others land in `$additionalUnionMemberships`.
 * - `$additionalUnionMemberships` carries the OTHER union-parent names a
 *   multi-parent child belongs to (empty list for single-parent children).
 *   The renderer emits `implements <X>Interface, …` for each — PHP's single
 *   inheritance precludes lifting them to `extends`, so each non-canonical
 *   union membership is declared via a marker interface emitted alongside
 *   the abstract parent class.
 * - `$defaults` is the raw `wire_field_name => bot_default_field_name` mapping
 *   from `.butcher/types/<X>/default.yml` (or `[]` if absent). Mirrors the
 *   shape of `MethodEntity::$defaults`. Used by `TypeRenderer::resolveDefault`
 *   to emit `new BotDefault(...)` on a type's constructor parameter (the
 *   canonical case: `LinkPreviewOptions::$isDisabled` defaults to
 *   `new BotDefault('link_preview_is_disabled')` so a bot-level default
 *   can override the upstream null).
 *
 * @phpstan-type Aliases array<string, mixed>
 */
final readonly class TypeEntity
{
  /**
   * @param list<AnnotationEntity> $annotations
   * @param null|list<string> $bases
   * @param Aliases $aliases
   * @param null|list<string> $subtypes
   * @param list<string> $extraUnionItems
   * @param array<string, string> $defaults
   * @param list<string> $additionalUnionMemberships
   */
  public function __construct(
    public string $name,
    public string $description,
    public array $annotations,
    public ?array $bases = null,
    public array $aliases = [],
    public ?array $subtypes = null,
    public array $extraUnionItems = [],
    public ?string $subtypeOf = null,
    public ?string $discriminator = null,
    public array $defaults = [],
    public array $additionalUnionMemberships = [],
  ) {}
}
