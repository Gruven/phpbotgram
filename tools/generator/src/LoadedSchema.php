<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Immutable result of `SchemaLoader::load()` — the input to every other stage
 * of the codegen pipeline.
 *
 * Lists preserve schema order (the order children appear in
 * `.butcher/schema/schema.json#items[].children[]`). Enums preserve directory
 * iteration order with a stable alpha sort applied by the loader so the
 * generator output is reproducible.
 */
final readonly class LoadedSchema
{
  /**
   * @param list<TypeEntity> $types
   * @param list<MethodEntity> $methods
   * @param list<EnumEntity> $enums
   */
  public function __construct(
    public string $apiVersion,
    public string $releaseDate,
    public array $types,
    public array $methods,
    public array $enums,
  ) {}
}
