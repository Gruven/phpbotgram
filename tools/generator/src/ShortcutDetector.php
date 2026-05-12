<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use LogicException;

/**
 * Stage 6 of the codegen pipeline.
 *
 * Walks `LoadedSchema::$types` and lowers each type's raw `aliases.yml`
 * (carried verbatim on `TypeEntity::$aliases` by `SchemaLoader`) into a
 * structured per-method `ShortcutPlan` the renderer (Task 2.10) emits as
 * instance methods on the owning Type — e.g. `Message::answer($text)`,
 * `Message::reply($text)`, `User::getProfilePhotos()`,
 * `CallbackQuery::answer($text)`.
 *
 * Grammar (per spec § "ShortcutDetector"):
 *
 *   <alias_name>:                   # snake_case PHP method name root
 *     method: <wireMethodName>      # required — TelegramMethod to instantiate
 *     fill:                         # optional — auto-fill ctor args via paths
 *       <param>: self.<path>        #   e.g. 'chat_id: self.chat.id'
 *     ignore: [<param>, ...]        # optional — params hidden from signature
 *     args:                         # optional — explicit per-param overrides
 *       <param>: { type: <PhpType> }
 *     description: <text>           # optional override
 *
 * Symfony YAML resolves anchors / merge-keys (`<<: *base`) eagerly at parse
 * time, so the raw arrays this stage sees have anchors flattened already —
 * no anchor-expansion logic is required here.
 *
 * Skip conditions:
 *   - `TypeEntity::$aliases === []` (no aliases.yml on disk) — skip the type.
 *   - An alias body without a top-level `method:` key — skip the alias.
 *     The vendored 10.0 schema has none of these, but the spec reserves the
 *     `condition:`/`ternary:` shapes for future filter-style aliases that
 *     don't lower to a TelegramMethod call; this stage ignores them rather
 *     than throwing so the schema can evolve forward without a breaking
 *     change here.
 *
 * Fail-closed conditions:
 *   - An alias references an unknown method name. Silently dropping such an
 *     alias would emit `new SomeMissingMethod(...)` in generated source; we
 *     surface the inconsistency at codegen time instead.
 */
final class ShortcutDetector
{
  /**
   * @var array<string, true>
   */
  private array $methodNames;

  public function __construct(
    private readonly LoadedSchema $schema,
    private readonly NameMapper $names,
  ) {
    $this->methodNames = [];

    foreach ($schema->methods as $m) {
      $this->methodNames[$m->name] = true;
    }
  }

  /**
   * @return list<ShortcutPlan>
   */
  public function plans(): array
  {
    /** @var list<ShortcutPlan> $plans */
    $plans = [];

    foreach ($this->schema->types as $type) {
      if ($type->aliases === []) {
        continue;
      }

      foreach ($type->aliases as $aliasName => $body) {
        if (!is_string($aliasName) || !is_array($body)) {
          // Defensive — Symfony YAML returns string keys for our shape; this
          // branch only fires if a future schema ships a non-conforming alias
          // body, in which case skipping is the safe default.
          continue;
        }

        /** @var array<string, mixed> $body */
        $plan = $this->buildPlan($type->name, $aliasName, $body);

        if ($plan !== null) {
          $plans[] = $plan;
        }
      }
    }

    return $plans;
  }

  /**
   * @param array<string, mixed> $body
   */
  private function buildPlan(string $ownerType, string $aliasName, array $body): ?ShortcutPlan
  {
    // TODO(cycle3): the schema's `aliases.yml` entries also carry a
    // `code:` block — a Python source snippet that asserts owner-property
    // nullability before the auto-fill happens
    // (e.g. `assert self.guest_query_id is not None`). The current
    // renderer side-steps the assertion by null-guarding `self.<x>` fills
    // with a `?? throw new LogicException(...)` when the property is
    // optional (see TypeRenderer::fillExpressionIsNullable). That works
    // for every assert the vendored 10.0 schema ships, but a future
    // schema patch could add `code:` blocks the heuristic doesn't cover
    // (compound conditions, multi-property guards). When that lands,
    // wire a real `code:` lowering pass through here rather than
    // extending the heuristic; the lowered shape is a sequence of
    // `assert X !== null` statements emitted before the `new Method(...)`
    // call.
    $method = $body['method'] ?? null;

    if (!is_string($method)) {
      // Condition-style / filter-style alias — out of scope for Phase 2.
      return null;
    }

    if (!isset($this->methodNames[$method])) {
      throw new LogicException(
        "Alias {$ownerType}.{$aliasName} references unknown method '{$method}'",
      );
    }

    return new ShortcutPlan(
      ownerTypeName: $ownerType,
      aliasName: $aliasName,
      // `methodFromSnake()` lowers the snake_case alias name to a PHP
      // camelCase method identifier without the property()-side reserved
      // keyword guard. PHP 7.0+ allows reserved words as method names, and
      // the schema relies on that (e.g. Chat.do -> sendChatAction).
      phpMethodName: $this->names->methodFromSnake($aliasName),
      methodEntityName: $method,
      fill: $this->extractFill($ownerType, $aliasName, $body),
      ignore: $this->extractIgnore($ownerType, $aliasName, $body),
      description: $this->extractDescription($body),
      argOverrides: $this->extractArgOverrides($ownerType, $aliasName, $body),
    );
  }

  /**
   * @param array<string, mixed> $body
   *
   * @return array<string, string>
   */
  private function extractFill(string $ownerType, string $aliasName, array $body): array
  {
    if (!isset($body['fill'])) {
      return [];
    }

    if (!is_array($body['fill'])) {
      throw new LogicException(
        "Alias {$ownerType}.{$aliasName} has non-array `fill:` block",
      );
    }

    /** @var array<string, string> $out */
    $out = [];

    foreach ($body['fill'] as $param => $expr) {
      if (!is_string($param)) {
        throw new LogicException(
          "Alias {$ownerType}.{$aliasName} `fill:` has non-string key",
        );
      }

      if (!is_string($expr)) {
        throw new LogicException(
          "Alias {$ownerType}.{$aliasName} `fill:` entry '{$param}' is not a string expression",
        );
      }

      $out[$param] = $expr;
    }

    return $out;
  }

  /**
   * @param array<string, mixed> $body
   *
   * @return list<string>
   */
  private function extractIgnore(string $ownerType, string $aliasName, array $body): array
  {
    if (!isset($body['ignore'])) {
      return [];
    }

    if (!is_array($body['ignore'])) {
      throw new LogicException(
        "Alias {$ownerType}.{$aliasName} has non-array `ignore:` block",
      );
    }

    /** @var list<string> $out */
    $out = [];

    foreach ($body['ignore'] as $param) {
      if (!is_string($param)) {
        throw new LogicException(
          "Alias {$ownerType}.{$aliasName} `ignore:` entry must be a string param name",
        );
      }

      $out[] = $param;
    }

    return $out;
  }

  /**
   * @param array<string, mixed> $body
   */
  private function extractDescription(array $body): ?string
  {
    if (!isset($body['description'])) {
      return null;
    }

    return is_string($body['description']) ? $body['description'] : null;
  }

  /**
   * @param array<string, mixed> $body
   *
   * @return array<string, array{type?: string}>
   */
  private function extractArgOverrides(string $ownerType, string $aliasName, array $body): array
  {
    if (!isset($body['args'])) {
      return [];
    }

    if (!is_array($body['args'])) {
      throw new LogicException(
        "Alias {$ownerType}.{$aliasName} has non-array `args:` block",
      );
    }

    /** @var array<string, array{type?: string}> $out */
    $out = [];

    foreach ($body['args'] as $param => $spec) {
      if (!is_string($param) || !is_array($spec)) {
        continue;
      }

      /** @var array{type?: string} $entry */
      $entry = [];

      if (isset($spec['type']) && is_string($spec['type'])) {
        $entry['type'] = $spec['type'];
      }

      $out[$param] = $entry;
    }

    return $out;
  }
}
