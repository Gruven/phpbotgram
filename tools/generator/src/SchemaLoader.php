<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Stage 1 of the codegen pipeline.
 *
 * Reads `.butcher/schema/schema.json` and the per-entity patch files
 * (`replace.yml`, `aliases.yml`, `default.yml`, `subtypes.yml`) and returns a
 * fully populated, immutable `LoadedSchema` tree.
 *
 * This stage does **no** type resolution, name-mapping, or PHP emission —
 * those are downstream concerns. The loader's job is to (a) parse the
 * input files into typed value objects, (b) apply the few patches that
 * affect structural shape (bases overrides, parsed_type overrides,
 * required flags, union parent/child relationships), and (c) attach the
 * remaining raw YAML (aliases) for later passes to consume.
 *
 * @phpstan-type SchemaApi array{version: string, release_date: string}
 * @phpstan-type SchemaAnnotation array{name: string, type: string, description?: string, html_description?: string, rst_description?: string, required?: bool}
 * @phpstan-type SchemaChild array{name: string, anchor?: string, category: string, description?: string, html_description?: string, rst_description?: string, annotations?: list<SchemaAnnotation>}
 * @phpstan-type SchemaItem array{title?: string, anchor?: string, children?: list<SchemaChild>}
 * @phpstan-type SchemaRoot array{api: SchemaApi, items: list<SchemaItem>}
 */
final class SchemaLoader
{
  private readonly string $schemaDir;

  public function __construct(string $schemaDir = '.butcher')
  {
    $this->schemaDir = rtrim($schemaDir, '/');
  }

  public function load(): LoadedSchema
  {
    $schemaPath = $this->schemaDir . '/schema/schema.json';

    if (!is_file($schemaPath)) {
      throw new RuntimeException("Schema file not found: {$schemaPath}");
    }

    $raw = file_get_contents($schemaPath);

    if ($raw === false) {
      throw new RuntimeException("Failed to read schema file: {$schemaPath}");
    }

    /** @var SchemaRoot $schema */
    $schema = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);

    [$types, $methods] = $this->loadTypesAndMethods($schema);
    $enums = $this->loadEnums();

    return new LoadedSchema(
      apiVersion: $schema['api']['version'],
      releaseDate: $schema['api']['release_date'],
      types: $types,
      methods: $methods,
      enums: $enums,
    );
  }

  /**
   * @param SchemaRoot $schema
   *
   * @return array{list<TypeEntity>, list<MethodEntity>}
   */
  private function loadTypesAndMethods(array $schema): array
  {
    /** @var list<TypeEntity> $types */
    $types = [];

    /** @var list<MethodEntity> $methods */
    $methods = [];

    // First pass: collect every type/method child into category-keyed buffers
    // so we can build the subtype index before instantiating TypeEntity values.
    /** @var list<SchemaChild> $typeChildren */
    $typeChildren = [];

    /** @var list<SchemaChild> $methodChildren */
    $methodChildren = [];

    foreach ($schema['items'] as $item) {
      foreach ($item['children'] ?? [] as $child) {
        if (!isset($child['category'])) {
          continue;
        }

        if ($child['category'] === 'types') {
          $typeChildren[] = $child;
        } elseif ($child['category'] === 'methods') {
          $methodChildren[] = $child;
        }
      }
    }

    [$unionParents, $childToParents] = $this->buildUnionIndex($typeChildren);

    foreach ($typeChildren as $child) {
      $types[] = $this->buildType($child, $unionParents, $childToParents);
    }

    foreach ($methodChildren as $child) {
      $methods[] = $this->buildMethod($child);
    }

    return [$types, $methods];
  }

  /**
   * Builds the union parent/child index in a single pass.
   *
   * Returns:
   *   - `$unionParents`: parent name → {subtypes, discriminator, extraItems}
   *   - `$childToParents`: child name → ordered list of every union parent
   *     that lists this child. Order is `subtypes.yml`-encounter order; the
   *     loader's caller (`buildType`) picks the canonical PHP-level
   *     `extends` parent by name-prefix rule and stores the rest on
   *     `TypeEntity::$additionalUnionMemberships` so the renderer can emit
   *     `implements <X>Interface` for each. PHP's single inheritance is what
   *     forces the multi-parent split — without the additional-memberships
   *     channel, a value typed `?InputPollOptionMedia` would reject
   *     `InputMediaPhoto` because the latter extends `InputMedia`, not
   *     `InputPollOptionMedia`.
   *
   * @param list<SchemaChild> $typeChildren
   *
   * @return array{
   *   array<string, array{subtypes: list<string>, discriminator: ?string, extraItems: list<string>}>,
   *   array<string, list<string>>
   * }
   */
  private function buildUnionIndex(array $typeChildren): array
  {
    /** @var array<string, array{subtypes: list<string>, discriminator: ?string, extraItems: list<string>}> $unionParents */
    $unionParents = [];

    /** @var array<string, list<string>> $childToParents */
    $childToParents = [];

    foreach ($typeChildren as $child) {
      $name = $child['name'];
      $subtypesPath = $this->typeDir($name) . '/subtypes.yml';

      if (!is_file($subtypesPath)) {
        continue;
      }

      /** @var null|array<string, mixed> $subtypesYaml */
      $subtypesYaml = $this->parseYaml($subtypesPath);
      $discriminator = null;

      if (is_array($subtypesYaml) && isset($subtypesYaml['discriminator']) && is_string($subtypesYaml['discriminator'])) {
        $discriminator = $subtypesYaml['discriminator'];
      }

      /** @var list<string> $extraItems */
      $extraItems = [];

      if (is_array($subtypesYaml) && isset($subtypesYaml['extra_items']) && is_array($subtypesYaml['extra_items'])) {
        foreach ($subtypesYaml['extra_items'] as $item) {
          if (!is_string($item)) {
            throw new RuntimeException("{$name} extra_items must contain only strings");
          }

          $extraItems[] = $item;
        }
      }

      $subtypeNames = $this->extractSubtypeNames($child['description'] ?? '');

      $unionParents[$name] = [
        'subtypes' => $subtypeNames,
        'discriminator' => $discriminator,
        'extraItems' => $extraItems,
      ];

      foreach ($subtypeNames as $sub) {
        $childToParents[$sub][] = $name;
      }
    }

    return [$unionParents, $childToParents];
  }

  /**
   * Pick the canonical PHP-level `extends` parent for a child that appears
   * in multiple union parents' subtype lists.
   *
   * Name-prefix-preferred selection: a child like `InputMediaAnimation` that
   * is listed by `InputMedia`, `InputPollMedia`, AND `InputPollOptionMedia`
   * canonically extends the parent whose name is a prefix of the child's
   * (`InputMedia`). When no parent prefixes the child, first-wins keeps the
   * declaration order from `subtypes.yml`. This produces a stable mapping
   * the renderer relies on so every child has exactly one PHP `extends`
   * parent — additional union memberships are surfaced as marker
   * interfaces.
   *
   * @param list<string> $parents
   */
  private function selectCanonicalParent(string $child, array $parents): string
  {
    $canonical = $parents[0];
    $canonicalIsPrefix = str_starts_with($child, $canonical);

    foreach ($parents as $candidate) {
      if ($candidate === $canonical) {
        continue;
      }

      $candidateIsPrefix = str_starts_with($child, $candidate);

      if ($candidateIsPrefix && !$canonicalIsPrefix) {
        $canonical = $candidate;
        $canonicalIsPrefix = true;
      }
    }

    return $canonical;
  }

  /**
   * Pulls the bullet-list of subtype names out of a union-parent's description.
   *
   * Telegram's schema documents unions as a `\n - X` indented bullet list under
   * the parent type's description. We mirror upstream butcher's convention of
   * using this list as the authoritative subtype enumeration.
   *
   * @return list<string>
   */
  private function extractSubtypeNames(string $description): array
  {
    /** @var list<string> $names */
    $names = [];
    preg_match_all('/(?:^|\n) - ([A-Za-z_][A-Za-z0-9_]*)/', $description, $matches);

    foreach ($matches[1] as $name) {
      $names[] = $name;
    }

    return $names;
  }

  /**
   * @param SchemaChild $child
   * @param array<string, array{subtypes: list<string>, discriminator: ?string, extraItems: list<string>}> $unionParents
   * @param array<string, list<string>> $childToParents
   */
  private function buildType(array $child, array $unionParents, array $childToParents): TypeEntity
  {
    $name = $child['name'];
    $replace = $this->loadPatch($this->typeDir($name) . '/replace.yml');
    $annotations = $this->buildAnnotations($child['annotations'] ?? [], $replace);

    $bases = null;

    if (isset($replace['bases']) && is_array($replace['bases'])) {
      /** @var list<string> $extracted */
      $extracted = [];

      foreach ($replace['bases'] as $b) {
        if (is_string($b)) {
          $extracted[] = $b;
        }
      }
      $bases = $extracted;
    }

    /** @var array<string, mixed> $aliases */
    $aliases = $this->loadPatch($this->typeDir($name) . '/aliases.yml');

    $subtypes = null;
    $discriminator = null;

    /** @var list<string> $extraUnionItems */
    $extraUnionItems = [];

    if (isset($unionParents[$name])) {
      $subtypes = $unionParents[$name]['subtypes'];
      $discriminator = $unionParents[$name]['discriminator'];
      $extraUnionItems = $unionParents[$name]['extraItems'];
    }

    $allParents = $childToParents[$name] ?? [];
    $subtypeOf = null;

    /** @var list<string> $additionalUnionMemberships */
    $additionalUnionMemberships = [];

    if ($allParents !== []) {
      $subtypeOf = $this->selectCanonicalParent($name, $allParents);

      foreach ($allParents as $p) {
        if ($p !== $subtypeOf) {
          $additionalUnionMemberships[] = $p;
        }
      }
    }

    /** @var array<string, string> $defaults */
    $defaults = [];

    if (is_file($this->typeDir($name) . '/default.yml')) {
      /** @var array<string, mixed> $rawDefaults */
      $rawDefaults = $this->parseYaml($this->typeDir($name) . '/default.yml') ?? [];

      foreach ($rawDefaults as $wire => $field) {
        if (is_string($wire) && is_string($field)) {
          $defaults[$wire] = $field;
        }
      }
    }

    return new TypeEntity(
      name: $name,
      description: $child['description'] ?? '',
      annotations: $annotations,
      bases: $bases,
      aliases: $aliases,
      subtypes: $subtypes,
      extraUnionItems: $extraUnionItems,
      subtypeOf: $subtypeOf,
      discriminator: $discriminator,
      defaults: $defaults,
      additionalUnionMemberships: $additionalUnionMemberships,
    );
  }

  /**
   * @param SchemaChild $child
   */
  private function buildMethod(array $child): MethodEntity
  {
    $name = $child['name'];
    $methodDir = $this->methodDir($name);
    $replace = $this->loadPatch($methodDir . '/replace.yml');
    $annotations = $this->buildAnnotations($child['annotations'] ?? [], $replace);
    $annotations = $this->applyAnnotationOrder($annotations, $replace, $name);

    /** @var array<string, string> $defaults */
    $defaults = [];

    if (is_file($methodDir . '/default.yml')) {
      /** @var array<string, mixed> $rawDefaults */
      $rawDefaults = $this->parseYaml($methodDir . '/default.yml') ?? [];

      foreach ($rawDefaults as $wire => $field) {
        if (is_string($wire) && is_string($field)) {
          $defaults[$wire] = $field;
        }
      }
    }

    $parsedReturning = null;

    if (
      isset($replace['returning'])
      && is_array($replace['returning'])
      && isset($replace['returning']['parsed_type'])
      && is_array($replace['returning']['parsed_type'])
    ) {
      /** @var array<string, mixed> $parsedReturning */
      $parsedReturning = $replace['returning']['parsed_type'];
    }

    return new MethodEntity(
      name: $name,
      description: $child['description'] ?? '',
      annotations: $annotations,
      returns: $this->extractReturnsSentence($child['description'] ?? ''),
      defaults: $defaults,
      parsedReturning: $parsedReturning,
    );
  }

  /**
   * Best-effort extraction of every candidate "returns" sentence from a
   * method's description. Downstream `MethodRenderer::resolveReturnType`
   * iterates these candidates and tries each against its prose matcher
   * chain until one parses; this lets the loader surface every sentence
   * that *could* describe the return shape rather than committing to a
   * single early-match that may turn out to be wrong.
   *
   * Multiple candidates are joined with a `\n` so downstream consumers
   * see a single string (preserving the legacy `$returns` API shape) but
   * the prose matcher can split on newlines to try each independently.
   *
   * Ranking (best first):
   *   1. Sentences containing an "is/are returned" anchor — these almost
   *      always carry the wire-type token.
   *   2. Sentences starting with "Returns" / "returns" — generic return
   *      phrases ("Returns a StarTransactions object."). The LATER such
   *      sentences in the description tend to be more structurally precise
   *      ("On success, returns a User object.") than the first ones
   *      ("Will return the score of …") which sometimes describe behaviour
   *      rather than the wire type, so within this bucket we reverse to
   *      try later sentences first.
   *   3. Sentences containing a bare "<X> object" pattern — wildcard
   *      fallback for the few methods whose return prose drops the
   *      "Returns" preamble.
   *
   * Returns the empty string when no candidate sentence matches — a
   * legitimate fallback for methods like `setWebhook` whose response shape
   * is documented elsewhere; `MethodRenderer::resolveReturnType` interprets
   * this as "no prose return-type hint, fall back to `bool`" only for
   * methods with no `returning.parsed_type` patch.
   */
  private function extractReturnsSentence(string $description): string
  {
    if ($description === '') {
      return '';
    }

    // Split on terminal punctuation (period, newline) so we can rank each
    // sentence independently. Telegram descriptions sometimes pack the
    // return phrase onto a separate line ("Returns an Array of GameHighScore
    // objects.\nThis method will currently …"), so the split needs to honour
    // newlines as well.
    /** @var list<string> $sentences */
    $sentences = [];

    foreach (preg_split('/(?<=\.)\s+|\r?\n/', $description) ?: [] as $s) {
      $s = trim($s);

      if ($s !== '') {
        $sentences[] = $s;
      }
    }

    /** @var list<string> $tier1 */
    $tier1 = []; // "<X> is/are returned" — strongest signal

    /** @var list<string> $tier2 */
    $tier2 = []; // "Returns …" — generic

    /** @var list<string> $tier3 */
    $tier3 = []; // "<X> object" wildcard

    foreach ($sentences as $s) {
      if (preg_match('/\b(?:is|are)\s+returned\b/', $s) === 1) {
        $tier1[] = $s;
      } elseif (preg_match('/(?:^|\b)(?:On success,\s*)?(?:Returns?|returns?)\b/', $s) === 1) {
        $tier2[] = $s;
      } elseif (preg_match('/[A-Z][A-Za-z0-9_]+\s+object\b/', $s) === 1) {
        $tier3[] = $s;
      }
    }

    // Within tier 2 the LATER sentences are usually the structured one
    // ("On success, returns a StarTransactions object.") while the EARLIER
    // ones can describe behaviour ("Will return the score of …"). Reverse
    // so we try later occurrences first.
    $tier2 = array_reverse($tier2);

    /** @var list<string> $candidates */
    $candidates = array_merge($tier1, $tier2, $tier3);

    if ($candidates === []) {
      return '';
    }

    // Join with newlines so the existing MethodEntity::$returns string API
    // is preserved (downstream consumers see a single string); the prose
    // matcher splits on newlines to try each candidate sentence in turn.
    return implode("\n", $candidates);
  }

  /**
   * @param list<SchemaAnnotation> $annotations
   * @param array<string, mixed> $replace
   *
   * @return list<AnnotationEntity>
   */
  private function buildAnnotations(array $annotations, array $replace): array
  {
    /** @var array<string, array<string, mixed>> $overrides */
    $overrides = [];

    if (isset($replace['annotations']) && is_array($replace['annotations'])) {
      foreach ($replace['annotations'] as $field => $patch) {
        if (is_string($field) && is_array($patch)) {
          $overrides[$field] = $patch;
        }
      }
    }

    /** @var list<AnnotationEntity> $out */
    $out = [];

    foreach ($annotations as $a) {
      $name = $a['name'];
      $patch = $overrides[$name] ?? [];

      $required = $a['required'] ?? false;

      if (isset($patch['required']) && is_bool($patch['required'])) {
        $required = $patch['required'];
      }

      $parsedType = null;

      if (isset($patch['parsed_type']) && is_array($patch['parsed_type'])) {
        /** @var array<string, mixed> $parsedType */
        $parsedType = $patch['parsed_type'];
      }

      $out[] = new AnnotationEntity(
        name: $name,
        description: $a['description'] ?? '',
        type: $a['type'],
        required: $required,
        parsedType: $parsedType,
        htmlDescription: $a['html_description'] ?? '',
        rstDescription: $a['rst_description'] ?? '',
      );
    }

    return $out;
  }

  /**
   * Applies a method-level `replace.yml` annotation order override while
   * keeping any unlisted fields in their original relative order.
   *
   * @param list<AnnotationEntity> $annotations
   * @param array<string, mixed> $replace
   *
   * @return list<AnnotationEntity>
   */
  private function applyAnnotationOrder(array $annotations, array $replace, string $entityName): array
  {
    if (!isset($replace['annotations_order'])) {
      return $annotations;
    }

    if (!is_array($replace['annotations_order'])) {
      throw new RuntimeException("{$entityName} annotations_order must be a list of wire field names");
    }

    /** @var list<string> $order */
    $order = [];

    /** @var array<string, true> $seen */
    $seen = [];

    foreach ($replace['annotations_order'] as $field) {
      if (!is_string($field)) {
        throw new RuntimeException("{$entityName} annotations_order must contain only strings");
      }

      if (isset($seen[$field])) {
        throw new RuntimeException("{$entityName} annotations_order lists '{$field}' more than once");
      }

      $seen[$field] = true;
      $order[] = $field;
    }

    if ($order === []) {
      return $annotations;
    }

    /** @var array<string, AnnotationEntity> $byName */
    $byName = [];

    foreach ($annotations as $annotation) {
      $byName[$annotation->name] = $annotation;
    }

    /** @var list<AnnotationEntity> $out */
    $out = [];

    foreach ($order as $field) {
      if (!isset($byName[$field])) {
        throw new RuntimeException("{$entityName} annotations_order references unknown field '{$field}'");
      }

      $out[] = $byName[$field];
      unset($byName[$field]);
    }

    foreach ($annotations as $annotation) {
      if (isset($byName[$annotation->name])) {
        $out[] = $annotation;
      }
    }

    return $out;
  }

  /**
   * @return list<EnumEntity>
   */
  private function loadEnums(): array
  {
    $enumsDir = $this->schemaDir . '/enums';

    if (!is_dir($enumsDir)) {
      throw new RuntimeException("Enums directory not found: {$enumsDir}");
    }

    $files = scandir($enumsDir);

    if ($files === false) {
      throw new RuntimeException("Failed to scan enums directory: {$enumsDir}");
    }

    /** @var list<string> $ymlFiles */
    $ymlFiles = [];

    foreach ($files as $f) {
      if (str_ends_with($f, '.yml')) {
        $ymlFiles[] = $f;
      }
    }

    sort($ymlFiles);

    /** @var list<EnumEntity> $enums */
    $enums = [];

    foreach ($ymlFiles as $f) {
      $path = $enumsDir . '/' . $f;

      /** @var null|array<string, mixed> $yaml */
      $yaml = $this->parseYaml($path);

      if (!is_array($yaml) || !isset($yaml['name']) || !is_string($yaml['name'])) {
        throw new RuntimeException("Enum {$path} is missing a top-level `name:` key");
      }

      $enums[] = $this->buildEnum($yaml);
    }

    return $enums;
  }

  /**
   * @param array<string, mixed> $yaml
   */
  private function buildEnum(array $yaml): EnumEntity
  {
    $description = '';

    if (isset($yaml['description']) && is_string($yaml['description'])) {
      $description = $yaml['description'];
    }

    $parse = null;

    if (isset($yaml['parse']) && is_array($yaml['parse'])) {
      /** @var array<string, mixed> $parse */
      $parse = $yaml['parse'];
    }

    $multiParse = null;

    if (isset($yaml['multi_parse']) && is_array($yaml['multi_parse'])) {
      $multiParse = $this->normaliseMultiParse($yaml['multi_parse']);
    }

    $extract = null;

    if (isset($yaml['extract']) && is_array($yaml['extract'])) {
      $extract = $this->normaliseExtract($yaml['extract']);
    }

    /** @var array<string, scalar> $static */
    $static = [];

    if (isset($yaml['static']) && is_array($yaml['static'])) {
      foreach ($yaml['static'] as $k => $v) {
        if (is_string($k) && is_scalar($v)) {
          $static[$k] = $v;
        }
      }
    }

    $type = null;

    if (isset($yaml['type']) && is_string($yaml['type'])) {
      $type = $yaml['type'];
    }

    /** @var string $name (guarded by caller: loadEnums() asserts is_string before invoking buildEnum) */
    $name = $yaml['name'];

    return new EnumEntity(
      name: $name,
      description: $description,
      parse: $parse,
      multiParse: $multiParse,
      extract: $extract,
      static: $static,
      type: $type,
    );
  }

  /**
   * @param array<mixed> $raw
   *
   * @return array{attribute: string, regexp: string, entities: list<string>}
   */
  private function normaliseMultiParse(array $raw): array
  {
    $attribute = isset($raw['attribute']) && is_string($raw['attribute']) ? $raw['attribute'] : '';
    $regexp = isset($raw['regexp']) && is_string($raw['regexp']) ? $raw['regexp'] : '';

    /** @var list<string> $entities */
    $entities = [];

    if (isset($raw['entities']) && is_array($raw['entities'])) {
      foreach ($raw['entities'] as $e) {
        if (is_string($e)) {
          $entities[] = $e;
        }
      }
    }

    return [
      'attribute' => $attribute,
      'regexp' => $regexp,
      'entities' => $entities,
    ];
  }

  /**
   * @param array<mixed> $raw
   *
   * @return array{entity: string, exclude?: list<string>}
   */
  private function normaliseExtract(array $raw): array
  {
    $entity = isset($raw['entity']) && is_string($raw['entity']) ? $raw['entity'] : '';

    $out = ['entity' => $entity];

    if (isset($raw['exclude']) && is_array($raw['exclude'])) {
      /** @var list<string> $exclude */
      $exclude = [];

      foreach ($raw['exclude'] as $e) {
        if (is_string($e)) {
          $exclude[] = $e;
        }
      }
      $out['exclude'] = $exclude;
    }

    return $out;
  }

  /**
   * Loads a YAML patch file, returning `[]` when the file is absent.
   *
   * @return array<string, mixed>
   */
  private function loadPatch(string $path): array
  {
    if (!is_file($path)) {
      return [];
    }

    /** @var mixed $parsed */
    $parsed = $this->parseYaml($path);

    if (!is_array($parsed)) {
      return [];
    }

    /** @var array<string, mixed> $coerced */
    $coerced = $parsed;

    return $coerced;
  }

  private function parseYaml(string $path): mixed
  {
    return Yaml::parseFile($path);
  }

  private function typeDir(string $name): string
  {
    return $this->schemaDir . '/types/' . $name;
  }

  private function methodDir(string $name): string
  {
    return $this->schemaDir . '/methods/' . $name;
  }
}
