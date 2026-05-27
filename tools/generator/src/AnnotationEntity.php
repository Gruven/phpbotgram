<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Single field/parameter on a TypeEntity or MethodEntity.
 *
 * Wire-level metadata as it appears in `.butcher/schema/schema.json` plus the
 * `replace.yml`-driven `parsed_type` override (which downstream resolvers use
 * to pick the PHP type when the raw `$type` string is insufficient — e.g.
 * `Integer` should emit as `\DateTimeImmutable` for `Message.date`).
 *
 * The `$htmlDescription` and `$rstDescription` payloads are the alternate
 * representations of `$description` that Telegram's vendored schema ships per
 * annotation. They power the enum renderer's `parse`/`multi_parse` cases that
 * carry a `format: rst` / `format: html` selector — without them, the
 * `\*([a-z_]+)\*` regex used by a handful of enums (e.g. `MenuButtonType`,
 * `InputPaidMediaType`) cannot extract the discriminator wire literals because
 * the bullet-style asterisks only appear in the rst flavour. Empty strings are
 * the legitimate fallback when an annotation's source only carries a single
 * description form.
 */
final readonly class AnnotationEntity
{
  /**
   * @param null|array<string, mixed> $parsedType
   */
  public function __construct(
    public string $name,
    public string $description,
    public string $type,
    public bool $required,
    public ?array $parsedType = null,
    public string $htmlDescription = '',
    public string $rstDescription = '',
  ) {}
}
