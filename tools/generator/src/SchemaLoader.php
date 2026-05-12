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

    [$unionParents, $childToParent] = $this->buildUnionIndex($typeChildren);

    foreach ($typeChildren as $child) {
      $types[] = $this->buildType($child, $unionParents, $childToParent);
    }

    foreach ($methodChildren as $child) {
      $methods[] = $this->buildMethod($child);
    }

    return [$types, $methods];
  }

  /**
   * Builds the union parent/child index in a single pass.
   *
   * @param list<SchemaChild> $typeChildren
   *
   * @return array{
   *   array<string, array{subtypes: list<string>, discriminator: ?string}>,
   *   array<string, string>
   * }
   */
  private function buildUnionIndex(array $typeChildren): array
  {
    /** @var array<string, array{subtypes: list<string>, discriminator: ?string}> $unionParents */
    $unionParents = [];

    /** @var array<string, string> $childToParent */
    $childToParent = [];

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

      $subtypeNames = $this->extractSubtypeNames($child['description'] ?? '');

      $unionParents[$name] = [
        'subtypes' => $subtypeNames,
        'discriminator' => $discriminator,
      ];

      foreach ($subtypeNames as $sub) {
        $childToParent[$sub] = $name;
      }
    }

    return [$unionParents, $childToParent];
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
   * @param array<string, array{subtypes: list<string>, discriminator: ?string}> $unionParents
   * @param array<string, string> $childToParent
   */
  private function buildType(array $child, array $unionParents, array $childToParent): TypeEntity
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

    if (isset($unionParents[$name])) {
      $subtypes = $unionParents[$name]['subtypes'];
      $discriminator = $unionParents[$name]['discriminator'];
    }

    $subtypeOf = $childToParent[$name] ?? null;

    return new TypeEntity(
      name: $name,
      description: $child['description'] ?? '',
      annotations: $annotations,
      bases: $bases,
      aliases: $aliases,
      subtypes: $subtypes,
      subtypeOf: $subtypeOf,
      discriminator: $discriminator,
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
   * Best-effort extraction of the "Returns X" / "X is returned" sentence
   * from a method's description. Downstream `TypeResolver` is responsible
   * for normalising this into a real PHP type; the empty string is a
   * legitimate fallback when no explicit return sentence is present
   * (e.g. `setWebhook`, where the response shape is documented elsewhere).
   */
  private function extractReturnsSentence(string $description): string
  {
    $patterns = [
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+[^.]+(?:on success)?\./',
      '/(?:On success,?\s+)?(?:the\s+sent\s+|the\s+|an?\s+array\s+of\s+)?[A-Z][A-Za-z]+(?:\s+of\s+[A-Z][A-Za-z]+)?\s+(?:is|are)\s+returned/i',
    ];

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $description, $m) === 1) {
        return trim($m[0]);
      }
    }

    return '';
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
