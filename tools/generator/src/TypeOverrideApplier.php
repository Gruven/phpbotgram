<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Stage 4 of the codegen pipeline.
 *
 * Consumes the per-type `replace.yml` patches that `SchemaLoader` has already
 * attached to `TypeEntity::$bases` / `AnnotationEntity::$parsedType` and applies
 * the few cross-entity propagations the loader cannot perform in isolation.
 *
 * Today there is one such propagation:
 *
 *   When a union **parent** declares `bases:` in its `replace.yml`, every
 *   subtype that did not declare its own `bases:` inherits the parent's
 *   bases. The propagating parents in the vendored schema are
 *   `InlineQueryResult`, `InputMedia`, `InputMessageContent`, `MenuButton`,
 *   and `PassportElementError` — all lifting their children to
 *   `MutableTelegramObject`. A child that explicitly overrides `bases:` keeps
 *   its own list (no overwrite); none of the vendored schema children actually
 *   do this today, but the rule is enforced for forward-compatibility.
 *
 * Note: `AnnotationEntity::$parsedType` is already populated by `SchemaLoader`
 * — this applier never touches annotations. Methods and enums also pass
 * through verbatim.
 *
 * The applier never mutates the input: `TypeEntity` and `LoadedSchema` are
 * `final readonly`, so any change is realised as a rebuilt `TypeEntity`
 * inserted into a fresh `LoadedSchema`. Calling `apply()` twice on the same
 * input (or re-feeding the output) is a no-op (idempotent).
 */
final class TypeOverrideApplier
{
  public function __construct(private readonly LoadedSchema $schema) {}

  public function apply(): LoadedSchema
  {
    /** @var array<string, TypeEntity> $byName */
    $byName = [];

    foreach ($this->schema->types as $t) {
      $byName[$t->name] = $t;
    }

    // Build the parent->children base-propagation index in a single pass so we
    // don't repeatedly scan $byName. Only parents with non-null bases AND
    // a non-empty subtypes list contribute work.
    /** @var array<string, list<string>> $propagatedBases */
    $propagatedBases = [];

    foreach ($this->schema->types as $t) {
      if ($t->subtypes === null || $t->bases === null) {
        continue;
      }

      foreach ($t->subtypes as $childName) {
        $propagatedBases[$childName] = $t->bases;
      }
    }

    /** @var list<TypeEntity> $rebuilt */
    $rebuilt = [];

    foreach ($this->schema->types as $t) {
      $rebuilt[] = $this->maybePropagate($t, $propagatedBases);
    }

    return new LoadedSchema(
      apiVersion: $this->schema->apiVersion,
      releaseDate: $this->schema->releaseDate,
      types: $rebuilt,
      methods: $this->schema->methods,
      enums: $this->schema->enums,
    );
  }

  /**
   * @param array<string, list<string>> $propagatedBases
   */
  private function maybePropagate(TypeEntity $t, array $propagatedBases): TypeEntity
  {
    // Child already declares its own bases — explicit wins, no overwrite.
    if ($t->bases !== null) {
      return $t;
    }

    // Not a union child, or its parent did not declare bases — nothing to do.
    if (!isset($propagatedBases[$t->name])) {
      return $t;
    }

    return new TypeEntity(
      name: $t->name,
      description: $t->description,
      annotations: $t->annotations,
      bases: $propagatedBases[$t->name],
      aliases: $t->aliases,
      subtypes: $t->subtypes,
      extraUnionItems: $t->extraUnionItems,
      subtypeOf: $t->subtypeOf,
      discriminator: $t->discriminator,
      defaults: $t->defaults,
      additionalUnionMemberships: $t->additionalUnionMemberships,
    );
  }
}
