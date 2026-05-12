<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator\Renderer;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\DefaultsResolver;
use Gruven\PhpBotGram\Generator\HandAuthoredShortcutPlan;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\PhpType;
use Gruven\PhpBotGram\Generator\PhpTypeKind;
use Gruven\PhpBotGram\Generator\ShortcutPlan;
use Gruven\PhpBotGram\Generator\TypeEntity;
use Gruven\PhpBotGram\Generator\TypeResolver;
use Gruven\PhpBotGram\Generator\UnionPlan;
use LogicException;
use Twig\Environment;

/**
 * Renderer for a single Telegram Type class.
 *
 * Consumes a `TypeEntity` plus the supporting plan stages (`TypeResolver`,
 * `DefaultsResolver`, `UnionPlan`s, `ShortcutPlan`s, `HandAuthoredShortcutPlan`s)
 * and emits one PHP source string per type. The pipeline orchestrator (Task 2.12)
 * is responsible for writing the result to disk and running cs-fixer once over
 * the whole emitted tree — this renderer just emits clean, lint-safe Twig
 * output close to the final style.
 *
 * Architectural choice: heavy preprocessing happens in PHP, and the Twig
 * template is intentionally close to literal-text-with-`{{ }}`-holes. That
 * keeps the template easy to scan and makes the renderer's input data
 * explicit, which simplifies debugging when a generated file looks off.
 */
final class TypeRenderer
{
  /**
   * Index of types that are extended by at least one other type via an
   * explicit `bases:` declaration (e.g. `ChatFullInfo extends Chat`).
   *
   * The renderer consults this set when deciding whether to emit `final
   * class` or plain `class` — PHP forbids `extends` on a final class, so
   * any type that is extended must drop the `final` modifier even when no
   * subtype relationship exists in the schema's `subtypes.yml`.
   *
   * @var array<string, true>
   */
  private readonly array $extendedTypes;

  /**
   * @param array<string, UnionPlan> $unionsByParent indexed by parent name
   * @param array<string, list<ShortcutPlan>> $shortcutsByOwner indexed by ownerTypeName
   * @param array<string, HandAuthoredShortcutPlan> $traitsByOwner indexed by ownerTypeName
   * @param array<string, MethodEntity> $methodsByName indexed by method name
   * @param array<string, TypeEntity> $typesByName indexed by type name; used
   *                                               to look up a parent type's
   *                                               annotations when emitting
   *                                               child constructors that
   *                                               forward overlapping
   *                                               properties to the parent.
   */
  public function __construct(
    private readonly Environment $twig,
    private readonly TypeResolver $types,
    private readonly NameMapper $names,
    private readonly DefaultsResolver $defaults,
    private readonly array $unionsByParent,
    private readonly array $shortcutsByOwner,
    private readonly array $traitsByOwner,
    private readonly array $methodsByName,
    private readonly array $typesByName = [],
  ) {
    /** @var array<string, true> $extended */
    $extended = [];

    foreach ($typesByName as $t) {
      // A `bases:` entry on the child registers the parent as "extended" —
      // BUT only when the parent is itself a schema type. The hand-written
      // bases (`MutableTelegramObject`, `TelegramObject`) are intentionally
      // not in $typesByName and stay `final` as a base class always
      // permits extension. We index against $typesByName so a future
      // schema-vendored hand-written base would land in this set too.
      if ($t->bases === null) {
        continue;
      }

      foreach ($t->bases as $baseName) {
        if (isset($typesByName[$baseName])) {
          $extended[$baseName] = true;
        }
      }
    }

    $this->extendedTypes = $extended;
  }

  /**
   * Emit a single Type class source.
   */
  public function render(TypeEntity $type): string
  {
    $isUnionParent = $type->subtypes !== null;
    $isExtended = isset($this->extendedTypes[$type->name]);
    $parentClass = $this->resolveParentClass($type);
    $imports = $this->collectImports($type, $parentClass);

    // Compute the set of wire-name annotations the parent (if a schema type)
    // already declares. Overlapping properties on the child must not be
    // re-promoted to readonly — PHP rejects redeclaration of a readonly
    // ctor-promoted property, and PHPStan reports `property.parentPropertyFinalByPhpDoc`
    // even when the redeclaration is syntactically legal. The child's
    // constructor instead accepts these params as plain locals and forwards
    // them through `parent::__construct(name: $name, ...)`.
    $parentAnnotationNames = $this->parentAnnotationWireNames($type);

    $properties = $this->buildProperties($type, $imports, $parentAnnotationNames);
    $parentForwardArgs = $this->buildParentForwardArgs($properties);
    $wireNames = $this->buildWireNames($properties);

    $shortcutMethods = $this->buildShortcutMethods($type, $imports);
    $trait = $this->traitsByOwner[$type->name] ?? null;

    if ($trait !== null) {
      // Trait FQCN is imported, used by short name in the class body.
      $imports[$trait->traitFqcn] = true;
    }

    $sortedImports = $this->sortImports($imports);

    /** @var list<array{phpdocType: string, phpName: string}> $phpdocParams */
    $phpdocParams = [];

    foreach ($properties as $p) {
      if ($p['phpdocType'] !== null) {
        $phpdocParams[] = ['phpdocType' => $p['phpdocType'], 'phpName' => $p['phpName']];
      }
    }

    // A type that's extended by another (e.g. Chat is extended by
    // ChatFullInfo) must drop `final` so PHP allows the subclass.
    $classKeyword = match (true) {
      $isUnionParent => 'abstract class',
      $isExtended => 'class',
      default => 'final class',
    };

    return $this->twig->render('type.php.twig', [
      'class_name' => $type->name,
      'namespace' => 'Gruven\\PhpBotGram\\Types',
      'imports' => $sortedImports,
      'class_keyword' => $classKeyword,
      'parent_class' => $parentClass,
      'description_lines' => $this->splitDescription($type->description),
      'source_anchor' => strtolower($type->name),
      'wire_names' => $wireNames,
      'properties' => $properties,
      'phpdoc_params' => $phpdocParams,
      'shortcut_methods' => $shortcutMethods,
      'trait_short_name' => $trait?->traitShortName,
      'parent_forward_args' => $parentForwardArgs,
    ]);
  }

  /**
   * Look up the wire-names of every annotation declared by `$type`'s parent
   * schema type, if any.
   *
   * Returns an empty set when:
   *   - the type has no `bases:` declaration,
   *   - the first base isn't itself a schema type (e.g. `TelegramObject`,
   *     `MutableTelegramObject` — both hand-written),
   *   - the type IS a union child (the union parent has no annotations of
   *     its own; the child's constructor is independent).
   *
   * @return array<string, true>
   */
  private function parentAnnotationWireNames(TypeEntity $type): array
  {
    if ($type->subtypeOf !== null) {
      // Union children inherit from an abstract parent that has no
      // promoted props of its own.
      return [];
    }

    if ($type->bases === null || $type->bases === []) {
      return [];
    }

    $baseName = $type->bases[0];
    $parent = $this->typesByName[$baseName] ?? null;

    if ($parent === null) {
      return [];
    }

    /** @var array<string, true> $names */
    $names = [];

    foreach ($parent->annotations as $a) {
      $names[$a->name] = true;
    }

    return $names;
  }

  /**
   * Build the named-argument list forwarded to `parent::__construct(...)`.
   *
   * Walks the child's promoted properties looking for entries flagged as
   * `inheritedFromParent` — those values must reach the parent constructor
   * for the parent's own promoted-readonly init to fire. Each entry maps to
   * `name: $name` in PHP source.
   *
   * @param list<array{
   *   phpName: string,
   *   wireName: string,
   *   phpType: string,
   *   phpdocType: ?string,
   *   default: ?string,
   *   description: string,
   *   rendererRenamed?: bool,
   *   inheritedFromParent?: bool,
   * }> $properties
   *
   * @return list<array{name: string, expr: string}>
   */
  private function buildParentForwardArgs(array $properties): array
  {
    /** @var list<array{name: string, expr: string}> $args */
    $args = [];

    foreach ($properties as $p) {
      if (!($p['inheritedFromParent'] ?? false)) {
        continue;
      }

      $args[] = [
        'name' => $p['phpName'],
        'expr' => '$' . $p['phpName'],
      ];
    }

    return $args;
  }

  /**
   * Split a multi-line description into trimmed lines suitable for emission
   * inside a `/**` docblock. Empty lines are preserved as empty strings so
   * the docblock renders blank docblock-asterisk lines verbatim.
   *
   * @return list<string>
   */
  private function splitDescription(string $description): array
  {
    if ($description === '') {
      return [];
    }

    /** @var list<string> $lines */
    $lines = [];

    foreach (preg_split("/\r?\n/", $description) ?: [] as $line) {
      $lines[] = rtrim($line);
    }

    return $lines;
  }

  /**
   * Resolve the unqualified parent class name to emit after `extends`.
   *
   * The default is `TelegramObject`. `replace.yml` `bases:` overrides may swap
   * in `MutableTelegramObject` (the keyboard/menu builders) or a sibling type
   * such as `Chat` (the `ChatFullInfo` extension).
   */
  private function resolveParentClass(TypeEntity $type): string
  {
    // Union children ALWAYS extend their tagged-union parent class, even
    // when `bases:` has been propagated onto them by `TypeOverrideApplier`.
    // The propagation exists so the union PARENT itself extends the lifted
    // base (`MutableTelegramObject` for the `InlineQueryResult`/`InputMedia`/
    // …families); children stay one rung lower so `<Parent>Union::resolve()`
    // returns a value typed as the union parent. The full chain
    // `Child → Parent → LiftedBase → TelegramObject` is preserved naturally.
    if ($type->subtypeOf !== null) {
      return $type->subtypeOf;
    }

    $bases = $type->bases;

    if ($bases === null || $bases === []) {
      return 'TelegramObject';
    }

    // Only the first base is consumed for `extends`. Multi-base types would
    // need PHP-side traits/interfaces, which the vendored schema never asks
    // for — but if a future schema ships them we surface the inconsistency
    // early instead of silently picking the first.
    if (\count($bases) > 1) {
      throw new LogicException(
        "Type {$type->name} declares multiple bases (" . implode(', ', $bases) . '); only single inheritance is supported',
      );
    }

    return $bases[0];
  }

  /**
   * Build the initial `use` import map for the file.
   *
   * Returns a set keyed by FQCN. The set always includes
   * `Gruven\PhpBotGram\Bot` (for the trailing constructor `?Bot $bot`
   * parameter). Parent classes live in the same `Types\` namespace and need
   * no import statement; downstream stages (annotation lowering, shortcut
   * method emission) mutate the map to add the remaining imports.
   *
   * @return array<string, true>
   */
  private function collectImports(TypeEntity $type, string $parentClass): array
  {
    unset($type, $parentClass);

    /** @var array<string, true> $imports */
    $imports = [];

    // Always import Bot for the trailing constructor parameter. Other
    // imports are added as the renderer walks annotations, shortcut method
    // bodies, and the optional hand-authored Shortcuts trait.
    $imports['Gruven\\PhpBotGram\\Bot'] = true;

    return $imports;
  }

  /**
   * Walk the annotations and produce the per-property descriptor the template
   * iterates. Mutates `$imports` to register every class-typed annotation's
   * FQCN.
   *
   * Each descriptor carries:
   *   - phpName: camelCase property identifier
   *   - wireName: original snake_case wire name
   *   - phpType: textual type for the PHP-level declaration (e.g. `int`,
   *     `?DateTime`, `string`, `array`)
   *   - phpdocType: PHPDoc-grade type for lists / unions
   *     (e.g. `list<MessageEntity>`); null when no PHPDoc widening is needed
   *   - default: per-parameter default expression (string), null when omitted
   *   - required: whether the field is schema-required
   *   - discriminatorValue: literal value for union-child discriminator
   *     fields (drives the `'solid'`-style default on `BackgroundFillSolid`)
   *
   * @param array<string, true> $imports
   * @param array<string, true> $parentAnnotationNames Wire-name set of the
   *                                                   parent schema type's
   *                                                   own annotations. Used
   *                                                   to flag overlapping
   *                                                   child properties so
   *                                                   they skip readonly
   *                                                   promotion (the parent
   *                                                   owns the promoted
   *                                                   slot).
   *
   * @return list<array{
   *   phpName: string,
   *   wireName: string,
   *   phpType: string,
   *   phpdocType: ?string,
   *   default: ?string,
   *   description: string,
   *   rendererRenamed: bool,
   *   inheritedFromParent: bool,
   * }>
   */
  private function buildProperties(TypeEntity $type, array &$imports, array $parentAnnotationNames = []): array
  {
    /** @var list<array{
     *   phpName: string,
     *   wireName: string,
     *   phpType: string,
     *   phpdocType: ?string,
     *   default: ?string,
     *   description: string,
     *   rendererRenamed: bool,
     *   inheritedFromParent: bool,
     * }> $out */
    $out = [];

    // Union parents have no annotation surface — they're a base class only.
    if ($type->subtypes !== null) {
      return $out;
    }

    // Pre-compute the discriminator wire-value for union children so the
    // `type`-style param gets a literal default.
    $discriminatorValue = null;
    $discriminatorWireField = null;

    if ($type->subtypeOf !== null) {
      $parentUnion = $this->unionsByParent[$type->subtypeOf] ?? null;

      if ($parentUnion !== null) {
        $discriminatorWireField = $parentUnion->discriminator;

        foreach ($parentUnion->members as $m) {
          if ($m->childClassName === $type->name) {
            $discriminatorValue = $m->wireValue;

            break;
          }
        }
      }
    }

    /** @var list<array{
     *   phpName: string,
     *   wireName: string,
     *   phpType: string,
     *   phpdocType: ?string,
     *   default: ?string,
     *   description: string,
     *   rendererRenamed: bool,
     *   inheritedFromParent: bool,
     *   originalOrder: int,
     *   sortRank: int,
     * }> $staged */
    $staged = [];

    foreach ($type->annotations as $i => $a) {
      $resolved = $this->types->resolve($a);
      $this->collectImportsForType($resolved, $imports);

      $phpName = $this->names->property($a->name);
      $rendererRenamed = false;

      // `bot` is reserved by the BotContextController parent constructor and
      // cannot be redeclared as a type property. Rename to `botUser` for the
      // two vendored offenders (`ManagedBotCreated.bot`, `ManagedBotUpdated.bot`).
      // The serializer reads the wire name from the WireNames const, so the
      // PHP-side rename is transparent on the wire.
      if ($phpName === 'bot') {
        $phpName = 'botUser';
        $rendererRenamed = true;
      }

      $phpType = $resolved->phpType;
      $phpdocType = $this->phpdocFor($resolved);
      // Reduce the PHP-level declaration to a runtime-typecheckable form
      // (`array` for lists, scalars/classnames left as-is).
      $declType = $this->declTypeFor($resolved);

      $default = $this->resolveDefault($a, $resolved, $discriminatorWireField, $discriminatorValue);

      // Nullable widening: an optional param defaulting to null becomes ?T
      // for single types, or T|null for already-union types (PHP forbids
      // `?A|B` syntax; the renderer always emits the union form when
      // multiple alternatives are involved).
      if ($default === 'null') {
        $declType = $this->widenNullable($declType);
      }

      // Sort rank for the constructor signature:
      //   0 — required-no-default (e.g. `int $messageId`)
      //   1 — required-with-default (e.g. discriminator `string $type = 'solid'`)
      //   2 — optional-with-default `= null` (e.g. `?int $messageThreadId = null`)
      //
      // Mirrors `MethodRenderer::buildParameters`. The reorder is mandatory
      // because cs-fixer's `no_unreachable_default_argument_value` rule strips
      // `= null` defaults from any optional param that precedes a required one
      // in raw schema order — making the resulting class impossible to
      // instantiate via named-only arguments.
      if ($default === null) {
        $sortRank = 0;
      } elseif ($default === 'null') {
        $sortRank = 2;
      } else {
        $sortRank = 1;
      }

      $staged[] = [
        'phpName' => $phpName,
        'wireName' => $a->name,
        'phpType' => $declType,
        'phpdocType' => $phpdocType,
        'default' => $default,
        'description' => $a->description,
        'rendererRenamed' => $rendererRenamed,
        'inheritedFromParent' => isset($parentAnnotationNames[$a->name]),
        'originalOrder' => $i,
        'sortRank' => $sortRank,
      ];
    }

    // Stable sort by (sortRank, originalOrder) — keeps schema order within
    // each rank bucket so the regenerated diff remains stable when the
    // vendored schema reshuffles bullet positions of same-rank fields.
    usort($staged, static function (array $a, array $b): int {
      if ($a['sortRank'] !== $b['sortRank']) {
        return $a['sortRank'] <=> $b['sortRank'];
      }

      return $a['originalOrder'] <=> $b['originalOrder'];
    });

    foreach ($staged as $p) {
      $out[] = [
        'phpName' => $p['phpName'],
        'wireName' => $p['wireName'],
        'phpType' => $p['phpType'],
        'phpdocType' => $p['phpdocType'],
        'default' => $p['default'],
        'description' => $p['description'],
        'rendererRenamed' => $p['rendererRenamed'],
        'inheritedFromParent' => $p['inheritedFromParent'],
      ];
    }

    return $out;
  }

  /**
   * Resolve the literal default expression for a property parameter.
   *
   * Order of precedence:
   *   1. Union-discriminator field on a union child: pin to the literal wire
   *      value (e.g. `'solid'` for `BackgroundFillSolid::$type`).
   *   2. Required `True`-literal annotation (rare on types): emit `true`.
   *   3. Optional (`required: false`) annotation: `null`.
   *   4. Required, non-discriminator: no default (the constructor signature
   *      omits the `=` clause).
   *
   * DefaultsResolver is consulted only for method parameters, not type
   * properties — that's why this routine ignores it.
   */
  private function resolveDefault(
    AnnotationEntity $a,
    PhpType $resolved,
    ?string $discriminatorWireField,
    ?string $discriminatorValue,
  ): ?string {
    if ($discriminatorWireField !== null && $a->name === $discriminatorWireField && $discriminatorValue !== null) {
      // Quote-escape the literal so a future schema with apostrophes in a
      // discriminator (none today) doesn't emit broken PHP.
      $escaped = strtr($discriminatorValue, ['\\' => '\\\\', "'" => "\\'"]);

      return "'{$escaped}'";
    }

    if ($a->required) {
      if ($resolved->isTrueLiteral) {
        return 'true';
      }

      return null;
    }

    return 'null';
  }

  /**
   * Compute the PHPDoc-grade type for an annotation, when the PHP-level
   * type declaration is lossy.
   *
   * Returns null when the PHP declaration is already a faithful render
   * (scalars, single class names, plain unions that PHP can declare
   * natively). Returns a PHPDoc type string when we widened to `array`
   * for a list or want to preserve narrower union shapes.
   */
  private function phpdocFor(PhpType $type): ?string
  {
    if ($type->kind === PhpTypeKind::ListOf) {
      return $type->phpType;
    }

    // Unions of lists need PHPDoc widening too (rare): if any member is a
    // list, the PHP declaration must collapse to `array` for the list
    // members.
    if ($type->kind === PhpTypeKind::Union) {
      foreach ($type->unionMembers as $m) {
        if ($m->kind === PhpTypeKind::ListOf) {
          return $type->phpType;
        }
      }
    }

    return null;
  }

  /**
   * Compute the PHP-level type declaration for a property.
   *
   * Scalars, class names, and plain unions render verbatim from the
   * resolver. Lists render as `array` (the element type is preserved in
   * the PHPDoc above the constructor); unions containing a list collapse
   * to `array` similarly to keep the declaration runtime-checkable.
   */
  private function declTypeFor(PhpType $type): string
  {
    if ($type->kind === PhpTypeKind::ListOf) {
      return 'array';
    }

    if ($type->kind === PhpTypeKind::Union) {
      $hasList = false;

      foreach ($type->unionMembers as $m) {
        if ($m->kind === PhpTypeKind::ListOf) {
          $hasList = true;

          break;
        }
      }

      if ($hasList) {
        return 'array';
      }
    }

    return $type->phpType;
  }

  /**
   * Add all imports referenced by a resolved PhpType into the import map.
   *
   * @param array<string, true> $imports
   */
  private function collectImportsForType(PhpType $type, array &$imports): void
  {
    if ($type->importFqcn !== null) {
      $imports[$type->importFqcn] = true;
    }

    if ($type->innerType !== null) {
      $this->collectImportsForType($type->innerType, $imports);
    }

    foreach ($type->unionMembers as $m) {
      $this->collectImportsForType($m, $imports);
    }
  }

  /**
   * Compute the `WireNames` constant entries (sorted by snake-case key, which
   * is the wire name) — but only emit them when at least one rename applies.
   *
   * @param list<array{phpName: string, wireName: string, rendererRenamed: bool, inheritedFromParent?: bool}> $properties
   *
   * @return list<array{phpName: string, wireName: string}>
   */
  private function buildWireNames(array $properties): array
  {
    /** @var list<array{phpName: string, wireName: string}> $renames */
    $renames = [];

    foreach ($properties as $p) {
      $rendererRenamed = $p['rendererRenamed'];

      // Only emit a WireNames entry when the runtime serializer's plain
      // `camelToSnake(phpName) === wireName` would yield the wrong wire
      // key. For most properties (`firstName` <-> `first_name`) the
      // inverse is exact and the const is redundant. The explicit-rename
      // cases — `fromUser` (NameMapper RENAMES table) and `botUser`
      // (renderer-side collision escape) — break the round-trip and DO
      // need an entry; the runtime serializer doesn't consult NameMapper.
      $inverse = $this->plainCamelToSnake($p['phpName']);

      if ($rendererRenamed || $inverse !== $p['wireName']) {
        $renames[] = ['phpName' => $p['phpName'], 'wireName' => $p['wireName']];
      }
    }

    // Sort by snake_case wire name (alphabetical) for deterministic output.
    usort($renames, static fn(array $a, array $b): int => strcmp($a['wireName'], $b['wireName']));

    return $renames;
  }

  /**
   * Lower per-type aliases.yml plans into the shortcut-method descriptor list
   * the template iterates.
   *
   * Each entry carries the PHP method signature and the lowered call
   * expression to the target TelegramMethod (with `self.<path>` already
   * resolved to `$this-><path>` and `bot: $this->bot` appended).
   *
   * @param array<string, true> $imports
   *
   * @return list<array{
   *   name: string,
   *   parameters: list<array{phpName: string, phpType: string, phpdocType: ?string, default: ?string, description: string}>,
   *   returnType: string,
   *   methodClass: string,
   *   callArgs: list<array{name: string, expr: string}>,
   * }>
   */
  private function buildShortcutMethods(TypeEntity $type, array &$imports): array
  {
    $plans = $this->shortcutsByOwner[$type->name] ?? [];

    /** @var list<array{
     *   name: string,
     *   parameters: list<array{phpName: string, phpType: string, phpdocType: ?string, default: ?string, description: string}>,
     *   returnType: string,
     *   methodClass: string,
     *   callArgs: list<array{name: string, expr: string}>,
     * }> $out */
    $out = [];

    foreach ($plans as $plan) {
      $out[] = $this->buildShortcut($type, $plan, $imports);
    }

    return $out;
  }

  /**
   * @param array<string, true> $imports
   *
   * @return array{
   *   name: string,
   *   parameters: list<array{phpName: string, phpType: string, phpdocType: ?string, default: ?string, description: string}>,
   *   returnType: string,
   *   methodClass: string,
   *   callArgs: list<array{name: string, expr: string}>,
   * }
   */
  private function buildShortcut(TypeEntity $type, ShortcutPlan $plan, array &$imports): array
  {
    $method = $this->methodsByName[$plan->methodEntityName] ?? null;

    if ($method === null) {
      throw new LogicException(
        "Shortcut {$type->name}.{$plan->aliasName} references unknown method '{$plan->methodEntityName}'",
      );
    }

    // The target TelegramMethod class lives in the Methods namespace and
    // must be imported by the generated file so the `new <X>(...)`
    // expression compiles.
    $methodClassFqcn = 'Gruven\\PhpBotGram\\Methods\\' . ucfirst($plan->methodEntityName);
    $imports[$methodClassFqcn] = true;

    /** @var list<array{phpName: string, phpType: string, phpdocType: ?string, default: ?string, description: string}> $parameters */
    $parameters = [];

    /** @var list<array{name: string, expr: string}> $callArgs */
    $callArgs = [];

    // Defaults map for this method (keyed by wire param name).
    $defaults = $this->defaults->forMethod($plan->methodEntityName);

    $fill = $plan->fill;
    $ignore = array_fill_keys($plan->ignore, true);

    // Index the owner's own annotations so we can detect when a fill
    // expression references an optional (nullable) property on `self`. The
    // schema's `aliases.yml` includes a Python `assert` in the `code:` block
    // for these cases (e.g. `Message.answer_guest_query` asserts that
    // `self.guest_query_id is not None`); the PHP equivalent is a runtime
    // `?? throw` so the generated source survives PHPStan's null-check.
    /** @var array<string, bool> $ownerAnnotationsRequired wireName => required */
    $ownerAnnotationsRequired = [];

    foreach ($type->annotations as $oa) {
      $ownerAnnotationsRequired[$oa->name] = $oa->required;
    }

    foreach ($method->annotations as $a) {
      $wire = $a->name;

      if (isset($ignore[$wire])) {
        // The alias hides this parameter from its signature AND from the
        // forwarded call. Telegram's `Message.reply` does this for
        // `reply_to_message_id`, since the alias auto-supplies a
        // `reply_parameters` object instead.
        continue;
      }

      if (isset($fill[$wire])) {
        // Auto-filled — no signature entry, but the call expression
        // forwards the lowered path.
        $expr = $this->lowerSelfPath($fill[$wire]);

        // Null-guard a single-segment `self.<x>` reference when `x` is
        // nullable on the owner but the target method requires a non-null
        // value. The aiogram source pairs each such fill with a Python
        // `assert` in the alias's `code:` block; the PHP equivalent fails
        // loudly at runtime via the same exception class.
        if ($a->required && $this->fillExpressionIsNullable($fill[$wire], $ownerAnnotationsRequired)) {
          $expr = $expr . " ?? throw new \\LogicException('Shortcut "
            . $type->name . '::' . $plan->phpMethodName
            . " requires \\'" . $wire . "\\' to be set on this " . $type->name . ".')";
        }

        $callArgs[] = [
          'name' => $this->names->property($wire),
          'expr' => $expr,
        ];

        continue;
      }

      // Regular pass-through: keep the parameter in the alias signature and
      // forward by name.
      $resolved = $this->types->resolve($a);
      $this->collectImportsForType($resolved, $imports);

      $phpName = $this->names->property($wire);
      $declType = $this->declTypeFor($resolved);
      $phpdocType = $this->phpdocFor($resolved);

      $default = null;

      if (isset($defaults[$wire])) {
        $default = $defaults[$wire]->expression;

        if ($defaults[$wire]->isBotDefault) {
          $imports['Gruven\\PhpBotGram\\Client\\BotDefault'] = true;
        }
      } elseif (!$a->required) {
        $default = 'null';
      }

      // Required `true` literal default (rare).
      if ($a->required && $resolved->isTrueLiteral) {
        $default = 'true';
      }

      // Nullable widening for `= null` defaults.
      if ($default === 'null') {
        $declType = $this->widenNullable($declType);

        if ($phpdocType !== null) {
          // Mirror the runtime declaration's nullable widening in the
          // PHPDoc so PHPStan sees the param admits `null`.
          $phpdocType = $this->widenPhpdocNullable($phpdocType);
        }
      }

      // BotDefault widening: a `= new BotDefault(...)` default needs the
      // parameter type to admit BotDefault on top of its declared form.
      if ($default !== null && str_starts_with($default, 'new BotDefault(')) {
        $declType = $this->widenForBotDefault($declType);
      }

      $parameters[] = [
        'phpName' => $phpName,
        'phpType' => $declType,
        'phpdocType' => $phpdocType,
        'default' => $default,
        'description' => $a->description,
      ];

      $callArgs[] = [
        'name' => $phpName,
        'expr' => '$' . $phpName,
      ];
    }

    // Reorder the alias signature so required-without-default params come
    // first, followed by params with defaults — PHP would otherwise emit a
    // deprecation when an optional precedes a required one. The forwarded
    // `callArgs` keep schema order so the call still maps cleanly onto the
    // target method's named-argument signature.
    usort($parameters, static function (array $a, array $b): int {
      $aRequired = $a['default'] === null ? 0 : 1;
      $bRequired = $b['default'] === null ? 0 : 1;

      return $aRequired <=> $bRequired;
    });

    // Always thread through `bot: $this->bot` so the resulting method is
    // bound to the same Bot the source object was loaded with.
    $callArgs[] = [
      'name' => 'bot',
      'expr' => '$this->bot',
    ];

    // The return type of a shortcut is the TelegramMethod subclass itself
    // (e.g. `Message::answer()` returns `SendMessage`, not `Message`). The
    // user dispatches via `$bot(...)`, which invokes the session middleware
    // and decodes the response according to the method's `ReturnsType`.
    // Mirroring aiogram's `bot.send_message(...)` returning a `SendMessage`
    // builder instance rather than the eventual `Message` response.
    $methodClass = ucfirst($plan->methodEntityName);
    $returnType = $methodClass;

    return [
      'name' => $plan->phpMethodName,
      'parameters' => $parameters,
      'returnType' => $returnType,
      'methodClass' => $methodClass,
      'callArgs' => $callArgs,
    ];
  }

  /**
   * Decide whether a `self.<single>` fill expression resolves to a nullable
   * property on the owner type. Returns false for multi-segment paths,
   * method-call forms, conditional expressions, and literals — those cases
   * already have their own narrowing semantics and never need a top-level
   * null-guard.
   *
   * @param array<string, bool> $ownerAnnotationsRequired wireName => required
   */
  private function fillExpressionIsNullable(string $expr, array $ownerAnnotationsRequired): bool
  {
    $expr = trim($expr);

    if (!str_starts_with($expr, 'self.')) {
      return false;
    }

    $rest = substr($expr, \strlen('self.'));

    if (str_ends_with($rest, '()') || str_contains($rest, '.') || str_contains($rest, ' if ')) {
      return false;
    }

    $wire = $rest;

    if (!isset($ownerAnnotationsRequired[$wire])) {
      return false;
    }

    return !$ownerAnnotationsRequired[$wire];
  }

  /**
   * Lower a `self.<path>` expression into a PHP `$this-><path>` reference.
   *
   * The grammar from `aliases.yml` is a dotted path (`self.id`,
   * `self.chat.id`, `self.from_user.id`). Each segment after `self.` is
   * mapped via NameMapper to its camelCase form. Method-call segments
   * (`self.as_reply_parameters()`) are not yet supported by the schema's
   * lowering grammar — when a fill expression doesn't start with `self.`,
   * we emit it verbatim and rely on the upstream stage having validated it.
   */
  private function lowerSelfPath(string $expr): string
  {
    $expr = trim($expr);

    // Bare Python literals supported by the schema's lowering grammar.
    if ($expr === 'None') {
      return 'null';
    }

    if ($expr === 'True') {
      return 'true';
    }

    if ($expr === 'False') {
      return 'false';
    }

    if (!str_starts_with($expr, 'self.')) {
      // Non-self expressions pass through unchanged. The vendored 10.0
      // schema does ship `self.as_reply_parameters()` (a method call)
      // which the PHP port will reify when it lands a corresponding
      // `Message::asReplyParameters()` helper. Until then we keep the raw
      // expression so the regenerated source diff is visible.
      return $expr;
    }

    $rest = substr($expr, \strlen('self.'));

    // Method-call form (`self.as_reply_parameters()` or trailing parens).
    if (str_ends_with($rest, '()')) {
      $base = substr($rest, 0, -2);
      $segments = explode('.', $base);
      $lowered = array_map(fn(string $s): string => $this->names->methodFromSnake($s), $segments);

      return '$this->' . implode('->', $lowered) . '()';
    }

    // Conditional expressions (`self.message_thread_id if self.is_topic_message else None`)
    // — too complex to lower deterministically here. The renderer hands
    // them through verbatim so the regenerated file flags them at lint
    // time; downstream stages can replace the comparator with a PHP-side
    // ternary when the schema's grammar widens.
    if (str_contains($rest, ' if ') || str_contains($rest, ' else ')) {
      return $this->lowerConditional($expr);
    }

    $segments = explode('.', $rest);
    $lowered = array_map(fn(string $s): string => $this->names->property($s), $segments);

    return '$this->' . implode('->', $lowered);
  }

  /**
   * Lower a Pythonic `x if cond else y` expression into a PHP ternary.
   *
   * The vendored schema uses exactly one form:
   *   `self.message_thread_id if self.is_topic_message else None`
   *
   * Strict grammar: we only accept `<lhs> if <cond> else <rhs>`. If the
   * expression deviates we emit a comment placeholder so the regenerated
   * source surfaces the inconsistency at lint time rather than silently
   * miscompiling.
   */
  private function lowerConditional(string $expr): string
  {
    if (preg_match('/^(.+?)\s+if\s+(.+?)\s+else\s+(.+)$/', $expr, $m) !== 1) {
      return "/* unsupported alias expression: {$expr} */ null";
    }

    $lhs = $this->lowerSelfPath(trim($m[1]));
    $cond = $this->lowerSelfPath(trim($m[2]));
    $rhs = $this->lowerSelfPath(trim($m[3]));

    if ($rhs === 'None') {
      $rhs = 'null';
    }

    return "{$cond} ? {$lhs} : {$rhs}";
  }

  /**
   * Plain camel-to-snake translation matching `Client\Serializer::camelToSnake`.
   *
   * Used to decide whether a property's PHP name round-trips to its wire
   * name without help from the WireNames const — i.e. whether the const
   * entry is actually load-bearing at serializer runtime.
   */
  private function plainCamelToSnake(string $camel): string
  {
    $out = preg_replace('/(?<!^)[A-Z]/', '_$0', $camel);

    if ($out === null) {
      return strtolower($camel);
    }

    return strtolower($out);
  }

  /**
   * Widen a PHPDoc-grade type to admit `null`.
   *
   * Unlike the PHP-declaration helper `widenNullable`, this is always
   * additive ('|null' suffix) — PHPDoc tolerates `A|B|null` shapes the PHP
   * declaration cannot express.
   */
  private function widenPhpdocNullable(string $phpdocType): string
  {
    if (str_contains($phpdocType, '|null') || str_starts_with($phpdocType, 'null|')) {
      return $phpdocType;
    }

    return $phpdocType . '|null';
  }

  /**
   * Widen a PHP type declaration to admit null, choosing between the
   * shorthand `?T` and the union form `T|null` based on whether the
   * declaration already contains alternation. PHP rejects `?A|B`.
   */
  private function widenNullable(string $declType): string
  {
    if ($declType === 'null' || $declType === 'mixed') {
      return $declType;
    }

    if (str_starts_with($declType, '?') || str_contains($declType, '|null')) {
      return $declType;
    }

    if (str_contains($declType, '|')) {
      return 'null|' . $declType;
    }

    return '?' . $declType;
  }

  /**
   * Widen a parameter's PHP type declaration so it admits a `BotDefault`
   * sentinel alongside the regular type. Handles plain scalars, classnames,
   * nullable forms, and existing unions.
   */
  private function widenForBotDefault(string $declType): string
  {
    if (str_contains($declType, 'BotDefault')) {
      return $declType;
    }

    // Normalize nullable `?T` to a union form so we can append BotDefault.
    if (str_starts_with($declType, '?')) {
      $base = substr($declType, 1);

      return 'null|BotDefault|' . $base;
    }

    // Union already containing null — splice BotDefault in.
    if (str_contains($declType, '|null') || str_starts_with($declType, 'null|')) {
      return 'BotDefault|' . $declType;
    }

    return 'BotDefault|' . $declType;
  }

  /**
   * Sort the import set alphabetically by FQCN for stable output ordering.
   *
   * @param array<string, true> $imports
   *
   * @return list<string>
   */
  private function sortImports(array $imports): array
  {
    $fqcns = array_keys($imports);
    sort($fqcns, SORT_STRING);

    /** @var list<string> $fqcns */
    return $fqcns;
  }
}
