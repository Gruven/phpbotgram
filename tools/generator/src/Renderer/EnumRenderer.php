<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator\Renderer;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\EnumEntity;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\TypeEntity;
use LogicException;
use Twig\Environment;

/**
 * Renderer for a single Telegram enum class.
 *
 * Consumes one `EnumEntity` and emits a PHP 8.1+ backed enum (`string` by
 * default, `int` when `$type` overrides). Cases are derived from the
 * vendored enum-YAML's `static:`, `parse:`, `multi_parse:`, and `extract:`
 * shapes — each contributes a deduplicated wave of cases, in declaration
 * order, with `static:` first.
 *
 * Case-name derivation:
 *   - `static: {KEY: value}` → `Pascal(strtolower(KEY))` (e.g. `SENDER`
 *     → `Sender`, `MARKDOWN_V2` → `MarkdownV2`).
 *   - `parse`/`multi_parse`: case name comes from `Pascal(captured-string)`
 *     after splitting on `_` (e.g. `all_private_chats` → `AllPrivateChats`).
 *   - `extract`: walks the parent type's annotation NAMES (snake_case
 *     wire names), excludes any listed in `extract.exclude`, and emits one
 *     case per remaining annotation. Names go through NameMapper's
 *     snake-to-camel conversion and then ucfirst for PascalCase; values
 *     keep the snake_case form (matching aiogram's `UpdateType` semantics).
 *
 * Backing-type lookup:
 *   - `$type` may be `int`, `str`, or `string`. Anything else throws.
 *   - `int`-backed enums emit values verbatim (hex literals stay hex).
 *   - String-backed enums quote values with single quotes; embedded
 *     apostrophes/backslashes are escape-doubled.
 *
 * Failure mode: if the enum's parse/extract phases yield zero cases AND
 * `static:` is empty, the renderer throws a `LogicException`. Silently
 * emitting an empty backed-enum class produces a PHP-illegal source
 * (`enum X: string {}` with no cases is allowed by PHP, but offers no
 * caller value and is almost certainly a schema regression).
 */
final class EnumRenderer
{
  /**
   * @var array<string, TypeEntity>
   */
  private readonly array $typesByName;

  /**
   * @var array<string, MethodEntity>
   */
  private readonly array $methodsByName;

  public function __construct(
    private readonly Environment $twig,
    private readonly NameMapper $names,
    LoadedSchema $schema,
  ) {
    /** @var array<string, TypeEntity> $typesByName */
    $typesByName = [];

    foreach ($schema->types as $t) {
      $typesByName[$t->name] = $t;
    }

    /** @var array<string, MethodEntity> $methodsByName */
    $methodsByName = [];

    foreach ($schema->methods as $m) {
      $methodsByName[$m->name] = $m;
    }

    $this->typesByName = $typesByName;
    $this->methodsByName = $methodsByName;
  }

  /**
   * Emit one PHP enum class source.
   */
  public function render(EnumEntity $enum): string
  {
    $backingType = $this->resolveBackingType($enum);
    $cases = $this->buildCases($enum, $backingType);

    if ($cases === []) {
      throw new LogicException(
        "Enum {$enum->name}: no cases derived from parse/multi_parse/extract/static; check the YAML against the schema.",
      );
    }

    return $this->twig->render('enum.php.twig', [
      'class_name' => $enum->name,
      'namespace' => 'Gruven\\PhpBotGram\\Enums',
      'backing_type' => $backingType,
      'description_lines' => $this->splitDescription($enum->description),
      'cases' => $cases,
    ]);
  }

  /**
   * Resolve the PHP backing type for the enum.
   *
   * Defaults to `string`. Honours `$type` overrides for the int-valued enums
   * (`TopicIconColor` today, more in the future). Unknown overrides throw to
   * avoid silently emitting nonsensical backed-enum declarations.
   */
  private function resolveBackingType(EnumEntity $enum): string
  {
    if ($enum->type === null || $enum->type === '') {
      return 'string';
    }

    return match ($enum->type) {
      'int' => 'int',
      'str', 'string' => 'string',
      default => throw new LogicException(
        "Enum {$enum->name}: unsupported backing type '{$enum->type}' (expected int|str|string).",
      ),
    };
  }

  /**
   * Lower the enum's value sources into the case-descriptor list the
   * template iterates. The combined order is `static:` first, then
   * `parse`/`multi_parse`, then `extract`, with later sources contributing
   * only cases whose PHP case-name is not already taken.
   *
   * @return list<array{name: string, value: string}>
   */
  private function buildCases(EnumEntity $enum, string $backingType): array
  {
    /** @var array<string, string> $byName */
    $byName = [];

    foreach ($this->buildStaticCases($enum, $backingType) as [$name, $value]) {
      if (!isset($byName[$name])) {
        $byName[$name] = $value;
      }
    }

    foreach ($this->buildParsedCases($enum, $backingType) as [$name, $value]) {
      if (!isset($byName[$name])) {
        $byName[$name] = $value;
      }
    }

    foreach ($this->buildExtractedCases($enum, $backingType) as [$name, $value]) {
      if (!isset($byName[$name])) {
        $byName[$name] = $value;
      }
    }

    /** @var list<array{name: string, value: string}> $out */
    $out = [];

    foreach ($byName as $name => $value) {
      $out[] = ['name' => $name, 'value' => $value];
    }

    return $out;
  }

  /**
   * `static:` cases — map upper-snake keys to PascalCase identifiers, render
   * values verbatim (int) or single-quoted (string).
   *
   * @return list<array{0: string, 1: string}>
   */
  private function buildStaticCases(EnumEntity $enum, string $backingType): array
  {
    /** @var list<array{0: string, 1: string}> $out */
    $out = [];

    foreach ($enum->static as $key => $raw) {
      $name = $this->pascalCase((string)$key);
      $out[] = [$name, $this->encodeValue((string)$raw, $backingType)];
    }

    return $out;
  }

  /**
   * `parse:` (single source) + `multi_parse:` (many sources) — run the
   * configured regex over the matched annotation's description (plain / rst
   * / html, per `format:`) and PascalCase each captured wire literal into a
   * case name.
   *
   * @return list<array{0: string, 1: string}>
   */
  private function buildParsedCases(EnumEntity $enum, string $backingType): array
  {
    /** @var list<array{0: string, 1: string}> $out */
    $out = [];

    // `parse` form first: one entity, one regex.
    if ($enum->parse !== null) {
      $entity = $this->stringOrThrow($enum->parse, 'entity', $enum->name);
      $regex = $this->stringOrThrow($enum->parse, 'regexp', $enum->name);
      $attribute = $enum->parse['attribute'] ?? null;
      $category = $enum->parse['category'] ?? null;
      $format = $enum->parse['format'] ?? null;

      $description = $this->lookupDescription(
        $enum->name,
        $entity,
        is_string($attribute) ? $attribute : null,
        is_string($category) ? $category : null,
        is_string($format) ? $format : null,
      );

      foreach ($this->runRegex($regex, $description) as $captured) {
        $out[] = [$this->pascalCase($captured), $this->encodeValue($captured, $backingType)];
      }
    }

    // `multi_parse` form: sweep every named entity for the same regex.
    if ($enum->multiParse !== null) {
      $attribute = $enum->multiParse['attribute'];
      $regex = $enum->multiParse['regexp'];
      // `format` lives in the raw YAML but the SchemaLoader's
      // normaliseMultiParse strips it; we re-read from the live YAML to
      // recover the selector. The reload is cheap (1 file × 4 enums).
      $format = $this->multiParseFormat($enum->name);

      foreach ($enum->multiParse['entities'] as $entityName) {
        $description = $this->lookupDescription(
          $enum->name,
          $entityName,
          $attribute,
          'types',
          $format,
        );

        foreach ($this->runRegex($regex, $description) as $captured) {
          $out[] = [$this->pascalCase($captured), $this->encodeValue($captured, $backingType)];
        }
      }
    }

    return $out;
  }

  /**
   * `extract:` walks the parent type's annotation NAMES (snake_case wire
   * names) and emits one case per remaining name after applying the optional
   * `exclude:` filter.
   *
   * @return list<array{0: string, 1: string}>
   */
  private function buildExtractedCases(EnumEntity $enum, string $backingType): array
  {
    if ($enum->extract === null) {
      return [];
    }

    $entityName = $enum->extract['entity'];
    $exclude = $enum->extract['exclude'] ?? [];

    /** @var array<string, true> $excludeSet */
    $excludeSet = [];

    foreach ($exclude as $e) {
      $excludeSet[$e] = true;
    }

    $type = $this->typesByName[$entityName] ?? null;

    if ($type === null) {
      throw new LogicException(
        "Enum {$enum->name}: extract entity '{$entityName}' not found in schema types.",
      );
    }

    /** @var list<array{0: string, 1: string}> $out */
    $out = [];

    foreach ($type->annotations as $a) {
      if (isset($excludeSet[$a->name])) {
        continue;
      }

      // Case name = PascalCase of the annotation's wire name (snake_case).
      // Route through NameMapper so any future snake→identifier policy
      // tweaks (escape lists, rename overrides) land in one place. The
      // value preserves the snake_case wire form — the representation
      // every consumer of the enum compares against.
      $caseName = ucfirst($this->names->methodFromSnake($a->name));
      $out[] = [$caseName, $this->encodeValue($a->name, $backingType)];
    }

    return $out;
  }

  /**
   * Locate the annotation matching the given attribute on the named entity
   * and return the description text in the requested format.
   *
   * The `category` selector routes to types (default) or methods. The
   * `format` selector picks among plain / rst / html descriptions. Unknown
   * formats fall back to plain.
   */
  private function lookupDescription(
    string $enumName,
    string $entityName,
    ?string $attribute,
    ?string $category,
    ?string $format,
  ): string {
    $annotations = $this->annotationsFor($enumName, $entityName, $category);

    if ($attribute === null) {
      // No specific attribute — concatenate all descriptions so the regex
      // can scan the whole entity. Defensive; no enum uses this branch.
      $bag = [];

      foreach ($annotations as $a) {
        $bag[] = $this->pickDescription($a, $format);
      }

      return implode("\n", $bag);
    }

    foreach ($annotations as $a) {
      if ($a->name === $attribute) {
        return $this->pickDescription($a, $format);
      }
    }

    throw new LogicException(
      "Enum {$enumName}: entity '{$entityName}' has no annotation '{$attribute}'.",
    );
  }

  /**
   * @return list<AnnotationEntity>
   */
  private function annotationsFor(string $enumName, string $entityName, ?string $category): array
  {
    if ($category === 'methods') {
      $m = $this->methodsByName[$entityName] ?? null;

      if ($m === null) {
        throw new LogicException(
          "Enum {$enumName}: method entity '{$entityName}' not found in schema.",
        );
      }

      return $m->annotations;
    }

    $t = $this->typesByName[$entityName] ?? null;

    if ($t === null) {
      // Some methods masquerade as type entities in the parse config (e.g.
      // ChatActions.yml's entity:sendChatAction without category:methods).
      // Try methods as a fallback before failing.
      $m = $this->methodsByName[$entityName] ?? null;

      if ($m !== null) {
        return $m->annotations;
      }

      throw new LogicException(
        "Enum {$enumName}: entity '{$entityName}' not found in schema (category={$category} fallback).",
      );
    }

    return $t->annotations;
  }

  private function pickDescription(AnnotationEntity $a, ?string $format): string
  {
    return match ($format) {
      'rst' => $a->rstDescription !== '' ? $a->rstDescription : $a->description,
      'html' => $a->htmlDescription !== '' ? $a->htmlDescription : $a->description,
      default => $a->description,
    };
  }

  /**
   * Re-read the YAML to pull the `multi_parse.format` selector that
   * SchemaLoader's normalisation strips. Returns null when the source has
   * no selector.
   */
  private function multiParseFormat(string $enumName): ?string
  {
    // We can't introspect the schema loader's path here — but the standard
    // layout is `.butcher/enums/<filename>.yml` where filename matches a
    // human-friendly enum name (often != $name due to the four known
    // mismatches; see SchemaLoader). Instead of hard-binding to a YAML
    // path, walk every enum file in the schema directory once.
    static $formatsByName = null;

    if ($formatsByName === null) {
      $formatsByName = $this->loadMultiParseFormats();
    }

    return $formatsByName[$enumName] ?? null;
  }

  /**
   * @return array<string, string>
   */
  private function loadMultiParseFormats(): array
  {
    // Scan `.butcher/enums/` (assumed cwd-relative to project root the
    // pipeline runs from). Returns name->format pairs for the 4 enums that
    // ship a `multi_parse.format`. Failure is non-fatal — when the dir is
    // unreachable (test fixtures with synthetic schemas), every multi_parse
    // enum falls back to plain `description`.
    $candidates = [
      getcwd() . '/.butcher/enums',
      __DIR__ . '/../../../../.butcher/enums',
    ];

    /** @var array<string, string> $out */
    $out = [];

    foreach ($candidates as $dir) {
      if (!is_dir($dir)) {
        continue;
      }

      $files = glob($dir . '/*.yml') ?: [];

      foreach ($files as $f) {
        $raw = file_get_contents($f);

        if ($raw === false) {
          continue;
        }

        if (preg_match('/^name:\s*(\S+)/m', $raw, $nameMatch) !== 1) {
          continue;
        }

        // Crude but adequate: look for `multi_parse:` followed by `format: <token>`
        // before another top-level key. The 4 occurrences match this pattern.
        if (preg_match('/multi_parse:\s*\n\s+format:\s*(\S+)/', $raw, $fmtMatch) === 1) {
          $out[$nameMatch[1]] = $fmtMatch[1];
        }
      }

      if ($out !== []) {
        return $out;
      }
    }

    return $out;
  }

  /**
   * Apply the regex (PHP-flavour, already-escaped wire string) over a
   * description and return every captured first-group match in order.
   *
   * Lone or malformed regexps yield an empty list and the caller surfaces
   * the failure as a `LogicException` once it sees no cases survived.
   *
   * @return list<string>
   */
  private function runRegex(string $regex, string $description): array
  {
    // The wire YAML supplies the regex body without delimiters — wrap in
    // `/.../` and let preg_match_all do the work.
    $pattern = '/' . $regex . '/';

    /** @var array<int, array<int, string>> $matches */
    $matches = [];

    if (@preg_match_all($pattern, $description, $matches) === false) {
      return [];
    }

    return $matches[1] ?? [];
  }

  /**
   * Pull a required string field out of a parse block, throwing when missing.
   *
   * @param array<string, mixed> $parse
   */
  private function stringOrThrow(array $parse, string $key, string $enumName): string
  {
    $v = $parse[$key] ?? null;

    if (!is_string($v) || $v === '') {
      throw new LogicException(
        "Enum {$enumName}: parse.{$key} is missing or not a string.",
      );
    }

    return $v;
  }

  /**
   * Encode a raw wire value into the PHP literal expression appropriate for
   * the chosen backing type.
   *
   * `int` backing: values pass through verbatim so hex literals (`0xFFD67E`)
   * stay hex; decimal stays decimal. `string` backing: single-quote with
   * embedded apostrophes and backslashes escape-doubled.
   */
  private function encodeValue(string $raw, string $backingType): string
  {
    if ($backingType === 'int') {
      return $raw;
    }

    $escaped = strtr($raw, [
      '\\' => '\\\\',
      "'" => "\\'",
    ]);

    return "'{$escaped}'";
  }

  /**
   * Convert a snake_case / UPPER_SNAKE_CASE / kebab-case / spaced token to
   * a PascalCase identifier.
   *
   * Numbers are preserved attached to whichever segment they appear in
   * (`update_id` → `UpdateId`, `markdown_v2` → `MarkdownV2`).
   */
  private function pascalCase(string $input): string
  {
    $lower = strtolower($input);
    $segments = preg_split('/[_\\s-]+/', $lower) ?: [];

    $out = '';

    foreach ($segments as $seg) {
      if ($seg === '') {
        continue;
      }

      $out .= ucfirst($seg);
    }

    if ($out === '') {
      throw new LogicException("Cannot derive case name from empty token (input: '{$input}')");
    }

    return $out;
  }

  /**
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

    // Trim trailing blank lines that come from YAML's `description: |` block
    // scalar trailing-newline behaviour.
    while ($lines !== [] && end($lines) === '') {
      array_pop($lines);
    }

    return $lines;
  }
}
