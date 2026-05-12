<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * One enum loaded from `.butcher/enums/<Name>.yml`.
 *
 * The vendored enum files use a handful of shapes:
 *   - `parse:`         single-source extraction (entity + attribute + regexp,
 *                      sometimes plus `category` and `format`)
 *   - `multi_parse:`   sweep multiple sibling entities for the discriminator
 *                      regex (the discriminated-union ancestors)
 *   - `extract:`       lift attribute names off another entity (e.g.
 *                      `UpdateType` pulls field names off `Update`)
 *   - `static:`        hardcoded values, optionally combined with parse/extract
 *   - `type:`          override the PHP scalar type (e.g. `int` for
 *                      `TopicIconColor`)
 *
 * The loader normalises each of these into a typed property so downstream
 * passes don't need to peek into a raw dict; absent keys become `null`/`[]`.
 *
 * @phpstan-type ParseShape array<string, mixed>
 * @phpstan-type MultiParseShape array{attribute: string, regexp: string, entities: list<string>}
 * @phpstan-type ExtractShape array{entity: string, exclude?: list<string>}
 */
final readonly class EnumEntity
{
  /**
   * @param null|ParseShape $parse
   * @param null|MultiParseShape $multiParse
   * @param null|ExtractShape $extract
   * @param array<string, scalar> $static
   */
  public function __construct(
    public string $name,
    public string $description,
    public ?array $parse = null,
    public ?array $multiParse = null,
    public ?array $extract = null,
    public array $static = [],
    public ?string $type = null,
  ) {}
}
