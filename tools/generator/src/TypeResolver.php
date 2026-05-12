<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use RuntimeException;

/**
 * Stage 2 of the codegen pipeline.
 *
 * Maps Telegram wire-type strings (the raw `type` field on every annotation)
 * and `replace.yml`-driven `parsed_type` overrides into structured `PhpType`
 * value objects the renderer can emit directly.
 *
 * Wire-type grammar (per Telegram Bot API docs):
 *   - Scalars:        `Integer`, `String`, `Boolean`, `Float`, `Float number`,
 *                     `True` (the `False` constant exists in the published
 *                     spec but is not used by any annotation in the vendored
 *                     schema, so we do not special-case it).
 *   - Composites:     `Array of X`, `Array of Array of X`,
 *                     `X or Y` (2+ members),
 *                     `Array of A, B, C and D` (inline-union inside a list —
 *                     used exactly once, by sendMediaGroup.media).
 *   - Type names:     `Message`, `Chat`, `User`, … — resolved against
 *                     `LoadedSchema::$types` (defaults to `Types\` namespace)
 *                     or `LoadedSchema::$enums` (`Enums\` namespace).
 *
 * `AnnotationEntity::$parsedType` (from per-entity `replace.yml`) wins over
 * the wire string when present. The supported shapes are:
 *   - `{type: std, name: <pyName>}` where pyName is one of `DateTime`,
 *     `datetime.datetime`, `datetime.timedelta`, `int`, `str`, `float`.
 *   - `{type: entity, references: {category: types|enums, name: <Name>}}`.
 *   - `{type: enum, name: <Name>}` — explicit enum reference (forward-compat;
 *     not present in the current vendored schema but cheap to support).
 *   - `{type: union, items: [...]}` — recursive union.
 *
 * @phpstan-type ParsedType array<string, mixed>
 */
final class TypeResolver
{
  /**
   * Set of known enum names — used to route an unqualified class-name token
   * to the `Enums\` namespace. Types are not indexed because every non-enum
   * fallback already lands in `Types\`, so the lookup would be a no-op.
   *
   * @var array<string, true>
   */
  private array $enumNames;

  public function __construct(LoadedSchema $schema)
  {
    $this->enumNames = [];

    foreach ($schema->enums as $e) {
      $this->enumNames[$e->name] = true;
    }
  }

  /**
   * Resolve an annotation, honouring its `parsed_type` override when present.
   */
  public function resolve(AnnotationEntity $annotation): PhpType
  {
    if ($annotation->parsedType !== null) {
      return $this->resolveParsed($annotation->parsedType);
    }

    return $this->resolveWire($annotation->type);
  }

  /**
   * Resolve a raw wire-type string. Also used for method return-types and
   * recursion from `resolve()`.
   */
  public function resolveWire(string $wireType): PhpType
  {
    $w = trim($wireType);

    // Strip the `Array of ` prefix(es), recurse on the remainder. We honour
    // arbitrary nesting (`Array of Array of …`) by recursing on the substring.
    if (str_starts_with($w, 'Array of ')) {
      $inner = $this->resolveWire(substr($w, \strlen('Array of ')));

      return new PhpType(
        kind: PhpTypeKind::ListOf,
        phpType: 'list<' . $inner->phpType . '>',
        importFqcn: $inner->importFqcn,
        innerType: $inner,
      );
    }

    // Union: `A or B [or C [or …]]`.
    if (str_contains($w, ' or ')) {
      return $this->buildUnion(explode(' or ', $w));
    }

    // Inline-union inside a list (only seen in sendMediaGroup.media):
    //   "A, B, C and D" -> union [A, B, C, D]
    if (str_contains($w, ' and ')) {
      return $this->buildUnion($this->splitInlineUnion($w));
    }

    return $this->resolveAtom($w);
  }

  /**
   * Resolve a non-composite token (no `Array of`, no `or`, no `and`).
   */
  private function resolveAtom(string $name): PhpType
  {
    return match ($name) {
      'Integer' => new PhpType(PhpTypeKind::Scalar, 'int'),
      'String' => new PhpType(PhpTypeKind::Scalar, 'string'),
      'Boolean' => new PhpType(PhpTypeKind::Scalar, 'bool'),
      'Float', 'Float number' => new PhpType(PhpTypeKind::Scalar, 'float'),
      'True' => new PhpType(PhpTypeKind::Scalar, 'bool', isTrueLiteral: true),
      default => $this->buildClassName($name),
    };
  }

  private function buildClassName(string $name): PhpType
  {
    $fqcn = isset($this->enumNames[$name])
      ? 'Gruven\\PhpBotGram\\Enums\\' . $name
      : 'Gruven\\PhpBotGram\\Types\\' . $name;

    return new PhpType(
      kind: PhpTypeKind::ClassName,
      phpType: $name,
      importFqcn: $fqcn,
    );
  }

  /**
   * @param list<string> $rawMembers
   */
  private function buildUnion(array $rawMembers): PhpType
  {
    /** @var array<string, PhpType> $byPhpType */
    $byPhpType = [];

    foreach ($rawMembers as $raw) {
      $resolved = $this->resolveWire(trim($raw));
      $byPhpType[$resolved->phpType] = $resolved;
    }

    if (\count($byPhpType) === 1) {
      // Dedup'd down to a single member — collapse the wrapping union.
      return array_values($byPhpType)[0];
    }

    ksort($byPhpType);

    /** @var list<PhpType> $members */
    $members = array_values($byPhpType);

    $phpType = implode('|', array_map(static fn(PhpType $m): string => $m->phpType, $members));

    return new PhpType(
      kind: PhpTypeKind::Union,
      phpType: $phpType,
      unionMembers: $members,
    );
  }

  /**
   * Splits the rare `A, B, C and D` form into its component names.
   *
   * @return list<string>
   */
  private function splitInlineUnion(string $w): array
  {
    // Replace " and " with ", " so a single comma-split yields every token.
    $normalised = str_replace(' and ', ', ', $w);

    /** @var list<string> $parts */
    $parts = [];

    foreach (explode(',', $normalised) as $p) {
      $trimmed = trim($p);

      if ($trimmed !== '') {
        $parts[] = $trimmed;
      }
    }

    return $parts;
  }

  /**
   * @param ParsedType $parsed
   */
  private function resolveParsed(array $parsed): PhpType
  {
    $type = $parsed['type'] ?? null;

    if (!is_string($type)) {
      throw new RuntimeException('parsed_type is missing a string `type` discriminator');
    }

    return match ($type) {
      'std' => $this->resolveParsedStd($parsed),
      'entity' => $this->resolveParsedEntity($parsed),
      'enum' => $this->resolveParsedEnum($parsed),
      'union' => $this->resolveParsedUnion($parsed),
      default => throw new RuntimeException("Unknown parsed_type kind: {$type}"),
    };
  }

  /**
   * @param ParsedType $parsed
   */
  private function resolveParsedStd(array $parsed): PhpType
  {
    $name = $parsed['name'] ?? null;

    if (!is_string($name)) {
      throw new RuntimeException('parsed_type std is missing a string `name`');
    }

    return match ($name) {
      'DateTime', 'datetime.datetime' => new PhpType(
        kind: PhpTypeKind::ClassName,
        phpType: 'DateTime',
        importFqcn: 'Gruven\\PhpBotGram\\Types\\Custom\\DateTime',
      ),
      'datetime.timedelta' => new PhpType(
        kind: PhpTypeKind::ClassName,
        phpType: 'DateInterval',
        importFqcn: 'DateInterval',
      ),
      'int' => new PhpType(PhpTypeKind::Scalar, 'int'),
      'str' => new PhpType(PhpTypeKind::Scalar, 'string'),
      'float' => new PhpType(PhpTypeKind::Scalar, 'float'),
      'bool' => new PhpType(PhpTypeKind::Scalar, 'bool'),
      default => throw new RuntimeException("Unknown parsed_type std name: {$name}"),
    };
  }

  /**
   * @param ParsedType $parsed
   */
  private function resolveParsedEntity(array $parsed): PhpType
  {
    if (!isset($parsed['references']) || !is_array($parsed['references'])) {
      throw new RuntimeException('parsed_type entity is missing a `references` block');
    }

    /** @var array<string, mixed> $refs */
    $refs = $parsed['references'];
    $category = $refs['category'] ?? null;
    $name = $refs['name'] ?? null;

    if (!is_string($category) || !is_string($name)) {
      throw new RuntimeException('parsed_type entity references must carry string `category` and `name`');
    }

    $namespace = $category === 'enums'
      ? 'Gruven\\PhpBotGram\\Enums\\'
      : 'Gruven\\PhpBotGram\\Types\\';

    return new PhpType(
      kind: PhpTypeKind::ClassName,
      phpType: $name,
      importFqcn: $namespace . $name,
    );
  }

  /**
   * @param ParsedType $parsed
   */
  private function resolveParsedEnum(array $parsed): PhpType
  {
    $name = $parsed['name'] ?? null;

    if (!is_string($name)) {
      throw new RuntimeException('parsed_type enum is missing a string `name`');
    }

    return new PhpType(
      kind: PhpTypeKind::ClassName,
      phpType: $name,
      importFqcn: 'Gruven\\PhpBotGram\\Enums\\' . $name,
    );
  }

  /**
   * @param ParsedType $parsed
   */
  private function resolveParsedUnion(array $parsed): PhpType
  {
    if (!isset($parsed['items']) || !is_array($parsed['items'])) {
      throw new RuntimeException('parsed_type union is missing an `items` list');
    }

    /** @var array<string, PhpType> $byPhpType */
    $byPhpType = [];

    foreach ($parsed['items'] as $item) {
      if (!is_array($item)) {
        throw new RuntimeException('parsed_type union items must be arrays');
      }

      /** @var ParsedType $item */
      $resolved = $this->resolveParsed($item);
      $byPhpType[$resolved->phpType] = $resolved;
    }

    if (\count($byPhpType) === 1) {
      return array_values($byPhpType)[0];
    }

    ksort($byPhpType);

    /** @var list<PhpType> $members */
    $members = array_values($byPhpType);

    $phpType = implode('|', array_map(static fn(PhpType $m): string => $m->phpType, $members));

    return new PhpType(
      kind: PhpTypeKind::Union,
      phpType: $phpType,
      unionMembers: $members,
    );
  }
}
