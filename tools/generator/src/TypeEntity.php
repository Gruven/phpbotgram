<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * One concrete or abstract Telegram type as resolved from
 * `.butcher/schema/schema.json` plus per-type patch files
 * (`replace.yml`, `aliases.yml`, `subtypes.yml`).
 *
 * - `$bases` is non-null only when `replace.yml` ships a top-level `bases:` list
 *   (mutable lifts, custom-parent overrides). Otherwise the downstream emitter
 *   falls back to the default `TelegramObject` parent.
 * - `$aliases` carries the **raw** parsed YAML from `aliases.yml`; lowering to
 *   PHP is the responsibility of Task 2.7 (ShortcutDetector).
 * - `$subtypes`/`$discriminator` are populated for union **parents** (types
 *   that ship a `subtypes.yml`); subtype names are parsed from the parent's
 *   description bullet-list.
 * - `$subtypeOf` is populated for union **children** with the parent name.
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
   */
  public function __construct(
    public string $name,
    public string $description,
    public array $annotations,
    public ?array $bases = null,
    public array $aliases = [],
    public ?array $subtypes = null,
    public ?string $subtypeOf = null,
    public ?string $discriminator = null,
  ) {}
}
