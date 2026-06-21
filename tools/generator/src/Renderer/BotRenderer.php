<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator\Renderer;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\DefaultsResolver;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\ParameterDefault;
use Gruven\PhpBotGram\Generator\PhpType;
use Gruven\PhpBotGram\Generator\PhpTypeKind;
use Gruven\PhpBotGram\Generator\TypeResolver;
use Gruven\PhpBotGram\Generator\UnionPlan;
use LogicException;
use Throwable;
use Twig\Environment;

/**
 * Renderer for the `Bot` facade.
 *
 * Consumes the entire `LoadedSchema` and emits a single PHP source string
 * containing the Bot class with one wrapper method per Telegram API
 * method. The wrapper preserves the corresponding `<Method>` constructor's
 * parameter list (minus the trailing `?Bot $bot = null`), appends a
 * trailing `?int $timeout = null`, and forwards every parameter by name
 * to `new <Method>(...)` inside `return $this(new <Method>(...), $timeout)`.
 *
 * Return-type lowering mirrors `MethodRenderer::resolveReturnType()`:
 *   - `Foo::class`-style returns surface as `Foo` (with `Foo` imported);
 *   - scalar returns (`'bool'`, `'int'`, `'string'`) surface as the
 *     matching PHP scalar;
 *   - `'list:Foo'` surfaces as `array` typed in PHP, with `@return list<Foo>`
 *     PHPDoc, and `Foo` imported.
 *
 * The hand-coded Phase 1 surface — constructor, `__invoke`,
 * `getDefaultProperties`, the `BotShortcuts` trait inclusion — is
 * preserved verbatim alongside the generated wrappers.
 */
final class BotRenderer
{
  /**
   * Cached schema type/enum names — used by `extractReturnedType` to
   * validate captured prose tokens against the actual schema before the
   * resolver tries to import them. Mirrors `MethodRenderer::$schemaTypeNames`.
   *
   * @var null|array<string, true>
   */
  private ?array $schemaTypeNames = null;

  private ?LoadedSchema $loadedForExtraction = null;

  /**
   * @param array<string, UnionPlan> $unionsByParent indexed by parent name
   */
  public function __construct(
    private readonly Environment $twig,
    private readonly TypeResolver $types,
    private readonly NameMapper $names,
    private readonly DefaultsResolver $defaults,
    private readonly array $unionsByParent = [],
  ) {}

  /**
   * Emit the full Bot facade source.
   */
  public function render(LoadedSchema $schema): string
  {
    // Make the schema reachable from the prose matcher's validator. Each
    // `render()` call rebuilds the cache because the renderer can be reused
    // across distinct schemas in tests (rare, but the invariant is cheap to
    // maintain).
    $this->loadedForExtraction = $schema;
    $this->schemaTypeNames = null;

    /** @var array<string, true> $imports */
    $imports = $this->collectInitialImports();

    /** @var list<array{
     *   name: string,
     *   parameters: list<array{phpName: string, phpType: string, phpdocType: ?string, default: ?string}>,
     *   methodClass: string,
     *   returnType: string,
     *   phpdocReturn: ?string,
     *   description: string,
     *   timeoutParamName: string,
     *   timeoutDocLines: list<string>,
     * }> $wrappers */
    $wrappers = [];

    foreach ($schema->methods as $method) {
      $wrappers[] = $this->buildWrapper($method, $imports);
    }

    $sortedImports = $this->sortImports($imports);

    return $this->twig->render('bot.php.twig', [
      'namespace' => 'Gruven\\PhpBotGram',
      'imports' => $sortedImports,
      'wrappers' => $wrappers,
    ]);
  }

  /**
   * Imports the facade unconditionally needs alongside whatever the
   * wrappers pull in. These mirror the Phase 1 hand-coded surface so the
   * generated file keeps every existing import the constructor + __invoke
   * reference.
   *
   * @return array<string, true>
   */
  private function collectInitialImports(): array
  {
    return [
      'Gruven\\PhpBotGram\\Client\\BotShortcuts' => true,
      'Gruven\\PhpBotGram\\Client\\BotShortcutsContract' => true,
      'Gruven\\PhpBotGram\\Client\\DefaultBotProperties' => true,
      'Gruven\\PhpBotGram\\Client\\Session\\AmphpSession' => true,
      'Gruven\\PhpBotGram\\Client\\Session\\BaseSession' => true,
      'Gruven\\PhpBotGram\\Methods\\TelegramMethod' => true,
      'Gruven\\PhpBotGram\\Utils\\Token' => true,
    ];
  }

  /**
   * Build the descriptor for a single wrapper method.
   *
   * @param array<string, true> $imports
   *
   * @return array{
   *   name: string,
   *   parameters: list<array{phpName: string, phpType: string, phpdocType: ?string, default: ?string}>,
   *   methodClass: string,
   *   returnType: string,
   *   phpdocReturn: ?string,
   *   description: string,
   *   timeoutParamName: string,
   *   timeoutDocLines: list<string>,
   * }
   */
  private function buildWrapper(MethodEntity $method, array &$imports): array
  {
    $methodClass = ucfirst($method->name);
    $imports['Gruven\\PhpBotGram\\Methods\\' . $methodClass] = true;

    $defaultsForMethod = $this->defaults->forMethod($method->name);
    $parameters = $this->buildParameters($method, $defaultsForMethod, $imports);

    [$returnType, $phpdocReturn] = $this->resolveReturnType($method, $imports);

    // Surface the method's "Returns …" prose into the wrapper docblock so
    // IDEs can show it on hover. Defensive trim — some methods have empty
    // returns strings which we'd rather not noise the docblock with.
    $description = trim($method->description);

    // The facade-side trailing `$timeout = null` parameter steers the session
    // dispatch timeout (default: bound to `BaseSession::$timeout`). If the
    // wrapped method already exposes a wire `timeout` parameter — the only
    // such case in the vendored schema is `getUpdates` — we rename to
    // `$apiTimeout` to keep both slots addressable from the same signature.
    $timeoutParamName = 'timeout';

    foreach ($parameters as $p) {
      if ($p['phpName'] === 'timeout') {
        $timeoutParamName = 'apiTimeout';

        break;
      }
    }

    // When the wrapper collision-renames the facade-side timeout to
    // `$apiTimeout` (so both the wire `timeout` parameter AND the HTTP
    // transport timeout remain addressable), the docblock spells out the
    // distinction so IDE hovers don't conflate the two slots. The note
    // sits between the @param block and the @return line in `bot.php.twig`.
    /** @var list<string> $timeoutDocLines */
    $timeoutDocLines = [];

    if ($timeoutParamName === 'apiTimeout') {
      $timeoutDocLines = [
        'Note: $timeout is the long-poll timeout (seconds) carried on the wire to Telegram; $apiTimeout is the HTTP transport timeout for the underlying request.',
      ];
    }

    return [
      'name' => $this->names->method($method->name),
      'parameters' => $parameters,
      'methodClass' => $methodClass,
      'returnType' => $returnType,
      'phpdocReturn' => $phpdocReturn,
      'description' => $description,
      'timeoutParamName' => $timeoutParamName,
      'timeoutDocLines' => $timeoutDocLines,
    ];
  }

  /**
   * Lower every wire annotation of the method into a wrapper-parameter
   * descriptor. Parameter list is the same as the Method constructor's —
   * required params first (no default), then optional. Trailing
   * `?int $timeout = null` is added by the template.
   *
   * @param array<string, ParameterDefault> $defaultsForMethod
   * @param array<string, true> $imports
   *
   * @return list<array{
   *   phpName: string,
   *   phpType: string,
   *   phpdocType: ?string,
   *   default: ?string,
   * }>
   */
  private function buildParameters(
    MethodEntity $method,
    array $defaultsForMethod,
    array &$imports,
  ): array {
    /** @var list<array{
     *   phpName: string,
     *   phpType: string,
     *   phpdocType: ?string,
     *   default: ?string,
     *   originalOrder: int,
     * }> $params */
    $params = [];

    foreach ($method->annotations as $i => $a) {
      $resolved = $this->types->resolve($a);
      $this->collectImportsForType($resolved, $imports);

      $phpName = $this->names->property($a->name);
      $declType = $this->declTypeFor($resolved);
      $phpdocType = $this->phpdocFor($resolved);

      $default = null;

      if (isset($defaultsForMethod[$a->name])) {
        $pd = $defaultsForMethod[$a->name];
        $default = $pd->expression;

        if ($pd->isBotDefault) {
          $imports['Gruven\\PhpBotGram\\Client\\BotDefault'] = true;
        }
      }

      if ($a->required && $resolved->isTrueLiteral && $default === null) {
        $default = 'true';
      }

      if ($default === 'null') {
        $declType = $this->widenNullable($declType);

        if ($phpdocType !== null) {
          // Mirror the runtime declaration's nullable widening in the
          // PHPDoc so PHPStan sees the param admits `null`.
          $phpdocType = $this->widenPhpdocNullable($phpdocType);
        }
      }

      if ($default !== null && str_starts_with($default, 'new BotDefault(')) {
        $declType = $this->widenForBotDefault($declType);

        if (!$a->required) {
          $declType = $this->widenNullable($declType);
        }
      }

      $params[] = [
        'phpName' => $phpName,
        'phpType' => $declType,
        'phpdocType' => $phpdocType,
        'default' => $default,
        'originalOrder' => $i,
      ];
    }

    // Reorder: required-without-default first (schema order preserved
    // within group), then params with defaults. Mirrors MethodRenderer so
    // the wrapper signature aligns one-for-one with the Method constructor.
    usort($params, static function (array $a, array $b): int {
      $aHas = $a['default'] === null ? 0 : 1;
      $bHas = $b['default'] === null ? 0 : 1;

      if ($aHas !== $bHas) {
        return $aHas <=> $bHas;
      }

      return $a['originalOrder'] <=> $b['originalOrder'];
    });

    /** @var list<array{phpName: string, phpType: string, phpdocType: ?string, default: ?string}> $stripped */
    $stripped = array_map(
      static fn(array $p): array => [
        'phpName' => $p['phpName'],
        'phpType' => $p['phpType'],
        'phpdocType' => $p['phpdocType'],
        'default' => $p['default'],
      ],
      $params,
    );

    return $stripped;
  }

  /**
   * Compute the wrapper's return type pair: the PHP-level declaration
   * (`User`, `bool`, `array`, …) and the optional PHPDoc-grade `@return`
   * (`list<Update>` when the PHP-level form collapses to `array`).
   *
   * Mirrors the resolution rules in `MethodRenderer::resolveReturnType()` —
   * keeping both renderers consistent is what lets the runtime serializer
   * dispatch on the Method's `ReturnsType` const without mismatching the
   * wrapper's declared return.
   *
   * @param array<string, true> $imports
   *
   * @return array{0: string, 1: ?string}
   */
  private function resolveReturnType(MethodEntity $method, array &$imports): array
  {
    if ($method->parsedReturning !== null) {
      $envelope = new AnnotationEntity(
        name: '__return__',
        description: '',
        type: 'Boolean',
        required: true,
        parsedType: $method->parsedReturning,
      );

      $resolved = $this->types->resolve($envelope);

      return $this->lowerResolvedReturn($resolved, $imports);
    }

    if ($method->returns === '') {
      return ['bool', null];
    }

    // Schema-vendored prose: extract a structured candidate, fail loudly if
    // the matcher can't parse it. Mirrors `MethodRenderer::resolveReturnType`
    // exactly so the wrapper signature always matches the Method class's
    // ReturnsType const.
    $extracted = $this->extractReturnedType($method->returns);

    if ($extracted === null) {
      throw new LogicException(\sprintf(
        'Could not extract return type for method %s from prose "%s". '
          . 'Tighten BotRenderer::extractReturnedType, or add a `returning:` '
          . 'override to .butcher/methods/%s/replace.yml.',
        $method->name,
        str_replace("\n", ' | ', $method->returns),
        $method->name,
      ));
    }

    $candidate = $this->normaliseReturnAlias($extracted['token']);

    try {
      $resolved = $this->types->resolveWire($extracted['isArray'] ? 'Array of ' . $candidate : $candidate);
    } catch (Throwable $e) {
      throw new LogicException(\sprintf(
        'Method %s prose returned token "%s" but TypeResolver rejected it: %s',
        $method->name,
        $candidate,
        $e->getMessage(),
      ), 0, $e);
    }

    return $this->lowerResolvedReturn($resolved, $imports);
  }

  /**
   * @param array<string, true> $imports
   *
   * @return array{0: string, 1: ?string}
   */
  private function lowerResolvedReturn(PhpType $resolved, array &$imports): array
  {
    switch ($resolved->kind) {
      case PhpTypeKind::Scalar:
        return [$resolved->phpType, null];

      case PhpTypeKind::ClassName:
        if ($resolved->importFqcn !== null) {
          $imports[$resolved->importFqcn] = true;
        }

        return [$resolved->phpType, null];

      case PhpTypeKind::ListOf:
        $inner = $resolved->innerType;

        if ($inner === null) {
          return ['array', null];
        }

        // Collapse the inner type if it's a union covered by a known
        // discriminator-tagged parent. Mirrors `MethodRenderer::returnFromResolved`
        // so the wrapper's `@return list<ChatMember>` matches the
        // corresponding Method's `ReturnsType` const and the runtime
        // serializer's per-union dispatch.
        $collapsed = $this->collapseUnionToParent($inner);

        if ($collapsed !== null) {
          $imports[$collapsed['importFqcn']] = true;

          return ['array', 'list<' . $collapsed['shortName'] . '>'];
        }

        if ($inner->kind === PhpTypeKind::ClassName && $inner->importFqcn !== null) {
          $imports[$inner->importFqcn] = true;
        }

        if ($inner->kind === PhpTypeKind::Union) {
          // Import every class-name union member so the PHPDoc references
          // resolve.
          foreach ($inner->unionMembers as $m) {
            if ($m->importFqcn !== null) {
              $imports[$m->importFqcn] = true;
            }
          }
        }

        return ['array', 'list<' . $inner->phpType . '>'];

      case PhpTypeKind::Union:
        $collapsed = $this->collapseUnionToParent($resolved);

        if ($collapsed !== null) {
          // Tagged-union collapse: every member of the union maps to a
          // single discriminator-tagged parent (e.g. `ChatMember`,
          // `MenuButton`). Use the parent class as the PHP-declared return
          // type so the wrapper signature matches the Method's
          // `ReturnsType` const; the runtime serializer routes the
          // response through `<Parent>Union::resolve()`.
          $imports[$collapsed['importFqcn']] = true;

          return [$collapsed['shortName'], null];
        }

        foreach ($resolved->unionMembers as $m) {
          if ($m->importFqcn !== null) {
            $imports[$m->importFqcn] = true;
          }
        }

        // For unions without a covering parent, the BotRenderer needs a
        // PHP-declarable type. The shorthand `T|U|V` is fine as long as
        // every member is a single class name. When a member is a list,
        // fall back to `array` and emit the union as PHPDoc.
        foreach ($resolved->unionMembers as $m) {
          if ($m->kind === PhpTypeKind::ListOf) {
            return ['array', $resolved->phpType];
          }
        }

        return [$resolved->phpType, null];
    }
  }

  /**
   * If `$type` is a tagged-union (kind=Union) whose members are exactly the
   * children of a known `UnionPlan` parent, return the parent's short name
   * and FQCN so the wrapper can declare the parent as its return type.
   *
   * Mirrors `MethodRenderer::collapseUnionToParent()` — keeping the two
   * renderers in sync is what lets the wrapper's declared return match the
   * Method's `ReturnsType` const for every union return in the schema.
   *
   * @return null|array{shortName: string, importFqcn: string}
   */
  private function collapseUnionToParent(PhpType $type): ?array
  {
    if ($type->kind !== PhpTypeKind::Union) {
      return null;
    }

    /** @var array<string, true> $childNames */
    $childNames = [];

    foreach ($type->unionMembers as $m) {
      if ($m->kind !== PhpTypeKind::ClassName) {
        return null;
      }

      $childNames[$m->phpType] = true;
    }

    if ($childNames === []) {
      return null;
    }

    foreach ($this->unionsByParent as $plan) {
      /** @var array<string, true> $planChildren */
      $planChildren = [];

      foreach ($plan->members as $member) {
        $planChildren[$member->childClassName] = true;
      }

      if ($planChildren == $childNames) { // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
        return [
          'shortName' => $plan->parentName,
          'importFqcn' => 'Gruven\\PhpBotGram\\Types\\' . $plan->parentName,
        ];
      }
    }

    return null;
  }

  /**
   * Mirrors `MethodRenderer::extractReturnedType` — keep the two
   * implementations byte-equivalent so the wrapper's declared return
   * matches the Method's ReturnsType const for every prose phrasing the
   * schema ships.
   *
   * @return null|array{token: string, isArray: bool}
   */
  private function extractReturnedType(string $sentences): ?array
  {
    foreach (preg_split("/\r?\n/", $sentences) ?: [] as $sentence) {
      $sentence = trim($sentence);

      if ($sentence === '') {
        continue;
      }

      $match = $this->matchSentence($sentence);

      if ($match !== null && $this->isValidCandidate($match['token'])) {
        return $match;
      }
    }

    return null;
  }

  /**
   * Per-sentence prose matcher chain — kept in sync with MethodRenderer.
   *
   * @return null|array{token: string, isArray: bool}
   */
  private function matchSentence(string $sentence): ?array
  {
    $arrayPatterns = [
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+an\s+Array\s+of\s+([A-Z][A-Za-z0-9_]*)/',
      '/an?\s+array\s+of\s+([A-Z][A-Za-z0-9_]*)(?:\s+objects?)?\b[^.]*?\s+(?:is|are)\s+returned/i',
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+an\s+array\s+of\s+([A-Z][A-Za-z0-9_]*)/i',
    ];

    foreach ($arrayPatterns as $pattern) {
      if (preg_match($pattern, $sentence, $m) === 1) {
        return ['token' => $m[1], 'isArray' => true];
      }
    }

    $scalarPatterns = [
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:[a-zA-Z ]+?)\s+as\s+(?:a |an |the )?([A-Z][A-Za-z0-9_]*)/',
      '/(?<![A-Za-z])([A-Z][A-Za-z0-9_]*)(?:\s+object)?\s+(?:is|are)\s+returned/',
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:a |an |the )?([A-Z][A-Za-z0-9_]*)/',
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:a |an |the )?(?:[a-z]+\s+)+([A-Z][A-Za-z0-9_]*)/',
      '/\b([A-Z][A-Za-z0-9_]*)\s+object\b/',
    ];

    foreach ($scalarPatterns as $pattern) {
      if (preg_match($pattern, $sentence, $m) === 1) {
        return ['token' => $m[1], 'isArray' => false];
      }
    }

    return null;
  }

  /**
   * Validate a captured prose token against the loaded schema's known
   * types + scalars. Mirrors `MethodRenderer::isValidCandidate`.
   */
  private function isValidCandidate(string $token): bool
  {
    $normalised = $this->normaliseReturnAlias($token);

    if (\in_array($normalised, ['Integer', 'String', 'Boolean', 'Float', 'True', 'False'], true)) {
      return true;
    }

    return $this->isKnownSchemaType($normalised) && isset($this->schemaTypeNames()[$normalised]);
  }

  /**
   * Lazy-built schema name index for the validator. The map is reset on
   * each `render()` call so the renderer remains reusable across schemas.
   *
   * @return array<string, true>
   */
  private function schemaTypeNames(): array
  {
    if ($this->schemaTypeNames === null) {
      /** @var array<string, true> $names */
      $names = [];

      $loaded = $this->loadedForExtraction;

      if ($loaded === null) {
        return [];
      }

      foreach ($loaded->types as $t) {
        $names[$t->name] = true;
      }

      foreach ($loaded->enums as $e) {
        $names[$e->name] = true;
      }

      $this->schemaTypeNames = $names;
    }

    return $this->schemaTypeNames;
  }

  private function normaliseReturnAlias(string $candidate): string
  {
    return match ($candidate) {
      'Int', 'Integer' => 'Integer',
      'Str', 'Text' => 'String',
      'Bool', 'Boolean' => 'Boolean',
      default => $candidate,
    };
  }

  private function isKnownSchemaType(string $name): bool
  {
    if (\strlen($name) < 2) {
      return false;
    }

    $reserved = [
      'Int' => true,
      'Str' => true,
      'Bool' => true,
      'Text' => true,
      'Object' => true,
      'Null' => true,
    ];

    return !isset($reserved[$name]);
  }

  private function declTypeFor(PhpType $type): string
  {
    if ($type->kind === PhpTypeKind::ListOf) {
      return 'array';
    }

    if ($type->kind === PhpTypeKind::Union) {
      /** @var array<string, string> $members */
      $members = [];
      $hasList = false;

      foreach ($type->unionMembers as $m) {
        $hasList = $hasList || $m->kind === PhpTypeKind::ListOf;
        $members[$m->kind === PhpTypeKind::ListOf ? 'array' : $m->phpType] = $m->kind === PhpTypeKind::ListOf ? 'array' : $m->phpType;
      }

      if ($hasList) {
        ksort($members);

        return implode('|', array_values($members));
      }
    }

    return $type->phpType;
  }

  private function phpdocFor(PhpType $type): ?string
  {
    if ($type->kind === PhpTypeKind::ListOf) {
      return $type->phpType;
    }

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
   * Widen a PHPDoc-grade type to admit `null`. Unlike `widenNullable`, this
   * is always additive ('|null' suffix) — PHPDoc tolerates union shapes the
   * PHP declaration cannot express.
   */
  private function widenPhpdocNullable(string $phpdocType): string
  {
    if (str_contains($phpdocType, '|null') || str_starts_with($phpdocType, 'null|')) {
      return $phpdocType;
    }

    return $phpdocType . '|null';
  }

  private function widenForBotDefault(string $declType): string
  {
    if (str_contains($declType, 'BotDefault')) {
      return $declType;
    }

    if (str_starts_with($declType, '?')) {
      $base = substr($declType, 1);

      return 'null|BotDefault|' . $base;
    }

    if (str_contains($declType, '|null') || str_starts_with($declType, 'null|')) {
      return 'BotDefault|' . $declType;
    }

    return 'BotDefault|' . $declType;
  }

  /**
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
