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
  ) {}
}
