<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Stage 7 of the codegen pipeline.
 *
 * Walks every `MethodEntity` in `LoadedSchema` and computes the
 * constructor-default expression the renderer must emit per parameter.
 *
 * Resolution rules (per `default.yml` semantics — see SchemaLoader):
 *
 *   1. If the annotation's wire name appears as a key in
 *      `MethodEntity::$defaults`, emit a `new BotDefault('<rhs>')`
 *      expression where `<rhs>` is the YAML value. The RHS is the
 *      BotDefault sentinel name (which is also the snake_case field
 *      name on `DefaultBotProperties`). The common shape is identity
 *      (`parse_mode: parse_mode`), but the schema also ships renames
 *      (`disable_web_page_preview: link_preview_is_disabled`,
 *      `link_preview_options: link_preview`,
 *      `explanation_parse_mode: parse_mode`, etc.) — the resolver
 *      faithfully threads either through.
 *
 *   2. Otherwise, if the annotation is `required: false`, emit a `null`
 *      expression (with `isBotDefault: false`). The renderer turns this
 *      into a plain `= null` clause and the parameter type is widened
 *      to nullable by the downstream parameter-renderer.
 *
 *   3. Required-true annotations that are NOT in `default.yml` are
 *      omitted from the result set entirely — they have no `=` clause
 *      in the generated PHP and must be supplied by the caller.
 *
 * The aggregate `defaults()` list intentionally contains BOTH kinds of
 * entries (BotDefault and null). The renderer needs a uniform "this
 * parameter has a default" signal to decide whether to emit an `=`
 * clause, and `$isBotDefault` cheaply discriminates the two emit paths
 * (the BotDefault branch also requires importing the class).
 *
 * Per-method annotation order is preserved so the renderer can iterate
 * a method's parameters and look up the matching `ParameterDefault`
 * without a re-sort step. `forMethod()` returns an associative map
 * keyed by wire name for O(1) lookup at render time.
 */
final class DefaultsResolver
{
  /**
   * @var list<ParameterDefault>
   */
  private array $defaults;

  /**
   * @var array<string, array<string, ParameterDefault>>
   */
  private array $byMethod;

  public function __construct(LoadedSchema $schema)
  {
    /** @var list<ParameterDefault> $defaults */
    $defaults = [];

    /** @var array<string, array<string, ParameterDefault>> $byMethod */
    $byMethod = [];

    foreach ($schema->methods as $method) {
      /** @var array<string, ParameterDefault> $perMethod */
      $perMethod = [];

      foreach ($method->annotations as $annotation) {
        $pd = $this->buildDefault($method, $annotation);

        if ($pd === null) {
          continue;
        }

        $defaults[] = $pd;
        $perMethod[$pd->wireParamName] = $pd;
      }

      if ($perMethod !== []) {
        $byMethod[$method->name] = $perMethod;
      }
    }

    $this->defaults = $defaults;
    $this->byMethod = $byMethod;
  }

  /**
   * Every default the generator must emit, in deterministic order:
   *   - outer order matches `LoadedSchema::$methods` (schema declaration order),
   *   - inner order matches each method's annotation order.
   *
   * @return list<ParameterDefault>
   */
  public function defaults(): array
  {
    return $this->defaults;
  }

  /**
   * Per-method lookup keyed by wire (snake_case) parameter name.
   *
   * Returns `[]` for unknown method names and for methods whose annotations
   * are all required (no defaults to emit). The renderer treats both
   * indistinguishably — there is nothing to emit either way — so this stage
   * does not throw on unknown lookups.
   *
   * @return array<string, ParameterDefault>
   */
  public function forMethod(string $methodName): array
  {
    return $this->byMethod[$methodName] ?? [];
  }

  private function buildDefault(MethodEntity $method, AnnotationEntity $annotation): ?ParameterDefault
  {
    if (\array_key_exists($annotation->name, $method->defaults)) {
      $sentinel = $method->defaults[$annotation->name];

      return new ParameterDefault(
        methodName: $method->name,
        wireParamName: $annotation->name,
        expression: $this->renderBotDefaultExpression($sentinel),
        isBotDefault: true,
      );
    }

    if (!$annotation->required) {
      return new ParameterDefault(
        methodName: $method->name,
        wireParamName: $annotation->name,
        expression: 'null',
        isBotDefault: false,
      );
    }

    return null;
  }

  /**
   * Renders a BotDefault constructor expression as the renderer-ready string,
   * single-quoting the sentinel name and escaping embedded apostrophes and
   * backslashes so the emitted PHP literal is always syntactically valid.
   *
   * No sentinel name in the vendored schema contains either character,
   * but the defensive escape costs nothing and forecloses a future surprise
   * where a schema patch introduces a quote-bearing name.
   */
  private function renderBotDefaultExpression(string $sentinel): string
  {
    $escaped = strtr($sentinel, [
      '\\' => '\\\\',
      "'" => "\\'",
    ]);

    return "new BotDefault('{$escaped}')";
  }
}
