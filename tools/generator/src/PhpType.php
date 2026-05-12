<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Structured PHP type descriptor produced by `TypeResolver`.
 *
 * The renderer consumes these directly: `$phpType` is the textual form that
 * lands in PHP source (e.g. `int`, `Message`, `list<Update>`, `int|string`);
 * `$importFqcn` (when present) is added to the file's `use` block. The shape
 * category lives on `$kind` so downstream code can pattern-match without
 * re-parsing the textual form.
 *
 * For `ListOf`, `$importFqcn` mirrors the innermost class member's import so
 * the renderer can collect imports off any nested list without recursing
 * itself; for `Union`, imports live on each member (the union has no single
 * canonical FQCN).
 *
 * `$isTrueLiteral` flags the Telegram-API "must-be-`True`" annotation: the
 * field is typed `bool` but the constructor always emits the literal `true`
 * default. Downstream renderers honour this flag when generating the
 * `__construct` parameter list.
 */
final readonly class PhpType
{
  /**
   * @param list<PhpType> $unionMembers
   */
  public function __construct(
    public PhpTypeKind $kind,
    public string $phpType,
    public ?string $importFqcn = null,
    public ?PhpType $innerType = null,
    public array $unionMembers = [],
    public bool $isTrueLiteral = false,
  ) {}
}
