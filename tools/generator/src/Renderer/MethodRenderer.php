<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator\Renderer;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\DefaultsResolver;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\ParameterDefault;
use Gruven\PhpBotGram\Generator\PhpType;
use Gruven\PhpBotGram\Generator\PhpTypeKind;
use Gruven\PhpBotGram\Generator\TypeResolver;
use Gruven\PhpBotGram\Generator\UnionPlan;
use Throwable;
use Twig\Environment;

/**
 * Renderer for a single Telegram API Method class.
 *
 * Consumes a `MethodEntity` plus the supporting plan stages (`TypeResolver`,
 * `DefaultsResolver`, `UnionPlan`s) and emits one PHP source string per
 * method. Mirrors `TypeRenderer`'s architecture: heavy preprocessing in PHP,
 * a Twig template kept close to literal-text-with-`{{ }}`-holes for
 * readability.
 *
 * Output shape (per file): a `final class <PascalName> extends TelegramMethod`
 * carrying `ApiMethod` (wire name) and `ReturnsType` (a class-string, scalar
 * literal, or `'list:<X>'` sentinel) consts, plus a constructor that exposes
 * every active wire parameter as a promoted-readonly property.
 *
 * The `ReturnsType` const is the runtime serializer's contract for decoding
 * the response payload back into a PHP value:
 *   - `Foo::class`   — call `Serializer::load(Foo::class, $data)`.
 *   - `'bool'`/`'int'`/`'string'` — return the scalar verbatim.
 *   - `'list:Foo'`   — decode each element of `$data` through `Foo::class`.
 * Multi-class union returns (e.g. `getChatMember`) collapse to the
 * discriminator-tagged parent class (`ChatMember::class`); the runtime then
 * routes through the corresponding `<Parent>Union::resolve()` helper.
 *
 * The pipeline orchestrator (Task 2.12) is responsible for writing the
 * result to disk and running cs-fixer once over the whole emitted tree.
 */
final class MethodRenderer
{
  /**
   * @param array<string, UnionPlan> $unionsByParent indexed by parent name
   */
  public function __construct(
    private readonly Environment $twig,
    private readonly TypeResolver $types,
    private readonly NameMapper $names,
    private readonly DefaultsResolver $defaults,
    private readonly array $unionsByParent,
  ) {}

  /**
   * Emit a single Method class source.
   */
  public function render(MethodEntity $method): string
  {
    $className = ucfirst($method->name);
    $imports = $this->collectInitialImports();

    $defaultsForMethod = $this->defaults->forMethod($method->name);
    $parameters = $this->buildParameters($method, $defaultsForMethod, $imports);

    $returns = $this->resolveReturnType($method, $imports);

    $sortedImports = $this->sortImports($imports);

    return $this->twig->render('method.php.twig', [
      'class_name' => $className,
      'namespace' => 'Gruven\\PhpBotGram\\Methods',
      'imports' => $sortedImports,
      'description_lines' => $this->splitDescription($method->description),
      'source_anchor' => strtolower($method->name),
      'extends_generic' => $returns['extendsGeneric'],
      'api_method' => $method->name,
      'returns_expr' => $returns['returnsExpr'],
      'parameters' => $parameters,
    ]);
  }

  /**
   * Initial import map: always pulls `Bot` (for the trailing `?Bot $bot = null`
   * constructor parameter). Other imports — return type, BotDefault sentinel,
   * per-parameter classnames — are added as the renderer walks the method.
   *
   * @return array<string, true>
   */
  private function collectInitialImports(): array
  {
    /** @var array<string, true> $imports */
    $imports = [];
    $imports['Gruven\\PhpBotGram\\Bot'] = true;

    return $imports;
  }

  /**
   * Lower the method's wire annotations into the constructor-parameter
   * descriptor list the template iterates.
   *
   * Mutates `$imports` to register every referenced classname FQCN. Returns
   * the parameters re-ordered so required params (no `default`) appear
   * before optional ones (which carry an `= null` / `= new BotDefault(...)`
   * clause). Within each group, schema declaration order is preserved.
   *
   * @param array<string, ParameterDefault> $defaultsForMethod
   * @param array<string, true> $imports
   *
   * @return list<array{
   *   phpName: string,
   *   phpType: string,
   *   phpdocType: ?string,
   *   default: ?string,
   *   description: string,
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
     *   description: string,
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

      // Required `True`-literal annotations (rare on methods; defensive).
      if ($a->required && $resolved->isTrueLiteral && $default === null) {
        $default = 'true';
      }

      // Nullable widening for `= null` defaults — admits null on top of the
      // declared type (PHP's `?T` shorthand or `null|<union>` form).
      if ($default === 'null') {
        $declType = $this->widenNullable($declType);
      }

      // BotDefault widening: a `= new BotDefault(...)` default needs the
      // parameter type to admit the sentinel alongside the natural type.
      // Optional params with a BotDefault also widen to admit null so a
      // caller can explicitly opt out of the bot-level default by passing
      // null (matches the hand-coded Phase 1 surface and aiogram's Python
      // port).
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
        'description' => $a->description,
        'originalOrder' => $i,
      ];
    }

    // Reorder: required-without-default params first (in schema order), then
    // params with defaults (also in schema order). PHP forbids an optional
    // parameter preceding a required one in a constructor.
    usort($params, static function (array $a, array $b): int {
      $aRequired = $a['default'] === null ? 0 : 1;
      $bRequired = $b['default'] === null ? 0 : 1;

      if ($aRequired !== $bRequired) {
        return $aRequired <=> $bRequired;
      }

      return $a['originalOrder'] <=> $b['originalOrder'];
    });

    // Drop the bookkeeping field before the template sees the list.
    /** @var list<array{
     *   phpName: string,
     *   phpType: string,
     *   phpdocType: ?string,
     *   default: ?string,
     *   description: string,
     * }> $stripped */
    $stripped = array_map(
      static fn(array $p): array => [
        'phpName' => $p['phpName'],
        'phpType' => $p['phpType'],
        'phpdocType' => $p['phpdocType'],
        'default' => $p['default'],
        'description' => $p['description'],
      ],
      $params,
    );

    return $stripped;
  }

  /**
   * Resolve the method's return type into the `ReturnsType` const expression
   * AND the corresponding `@extends TelegramMethod<…>` generic.
   *
   * Output expression flavours:
   *   - `Foo::class`        — class-typed return; adds `use … Foo` import.
   *   - `'bool'`/`'int'`/`'string'` — scalar return.
   *   - `'list:Foo'`        — array-of-Foo (or array-of-union-parent) return;
   *                            also imports Foo for use in the @extends generic.
   *
   * Multi-class union returns collapse to the discriminator-tagged parent
   * class (e.g. `ChatMember::class` for the ChatMember union) so the runtime
   * can route through `<Parent>Union::resolve()`. When no union parent exists
   * the renderer keeps the resolved union form and uses the first class
   * member's FQCN — that path is exercised only by hand-patched
   * `replace.yml`s, which always tag the parent. Plain unions falling through
   * to mixed are an extreme fallback.
   *
   * @param array<string, true> $imports
   *
   * @return array{returnsExpr: string, extendsGeneric: string}
   */
  private function resolveReturnType(MethodEntity $method, array &$imports): array
  {
    // 1. Explicit `returning.parsed_type` override from replace.yml.
    if ($method->parsedReturning !== null) {
      $envelope = new AnnotationEntity(
        name: '__return__',
        description: '',
        type: 'Boolean',
        required: true,
        parsedType: $method->parsedReturning,
      );

      $resolved = $this->types->resolve($envelope);

      return $this->returnFromResolved($resolved, $imports);
    }

    // 2. Heuristic parse of the `Returns …` sentence from the description.
    if ($method->returns === '') {
      return $this->returnFromScalar('bool');
    }

    $candidate = $this->extractReturnedType($method->returns);

    if ($candidate === null) {
      // Fall back to bool — the safe default for status-only methods. The
      // schema's verbose-prose returns ("return to the chat on their own
      // using invite links, etc.") all describe methods that return True.
      return $this->returnFromScalar('bool');
    }

    $candidate = $this->normaliseReturnAlias($candidate);

    try {
      $resolved = $this->types->resolveWire($candidate);
    } catch (Throwable) {
      return $this->returnFromScalar('bool');
    }

    // Guard against `Types\Int`/`Types\Object`-style ghost classes the prose
    // matcher could mint from generic English nouns. When the resolver lands
    // a class-typed return whose name isn't a known schema entity, fall back
    // to the safest scalar.
    if (
      $resolved->kind === PhpTypeKind::ClassName
      && $resolved->importFqcn !== null
      && str_starts_with($resolved->importFqcn, 'Gruven\\PhpBotGram\\Types\\')
      && !$this->isKnownSchemaType($resolved->phpType)
    ) {
      return $this->returnFromScalar('bool');
    }

    return $this->returnFromResolved($resolved, $imports);
  }

  /**
   * Lower a resolved `PhpType` into the `(returnsExpr, extendsGeneric)` pair
   * the template consumes.
   *
   * @param array<string, true> $imports
   *
   * @return array{returnsExpr: string, extendsGeneric: string}
   */
  private function returnFromResolved(PhpType $resolved, array &$imports): array
  {
    switch ($resolved->kind) {
      case PhpTypeKind::Scalar:
        return $this->returnFromScalar($resolved->phpType);

      case PhpTypeKind::ClassName:
        if ($resolved->importFqcn !== null) {
          $imports[$resolved->importFqcn] = true;
        }

        return [
          'returnsExpr' => $resolved->phpType . '::class',
          'extendsGeneric' => $resolved->phpType,
        ];

      case PhpTypeKind::ListOf:
        $inner = $resolved->innerType;

        if ($inner === null) {
          return $this->returnFromScalar('bool');
        }

        // Collapse the inner type if it's a union covered by a known parent.
        $collapsed = $this->collapseUnionToParent($inner);

        if ($collapsed !== null) {
          $imports[$collapsed['importFqcn']] = true;

          return [
            'returnsExpr' => "'list:{$collapsed['shortName']}'",
            'extendsGeneric' => 'list<' . $collapsed['shortName'] . '>',
          ];
        }

        if ($inner->kind === PhpTypeKind::ClassName && $inner->importFqcn !== null) {
          $imports[$inner->importFqcn] = true;

          return [
            'returnsExpr' => "'list:{$inner->phpType}'",
            'extendsGeneric' => 'list<' . $inner->phpType . '>',
          ];
        }

        if ($inner->kind === PhpTypeKind::Scalar) {
          return [
            'returnsExpr' => "'list:{$inner->phpType}'",
            'extendsGeneric' => 'list<' . $inner->phpType . '>',
          ];
        }

        // Defensive: any other inner shape (nested list, raw union without a
        // tagged parent) falls back to a generic list sentinel keyed by the
        // textual form. The runtime resolves the inner shape best-effort.
        return [
          'returnsExpr' => "'list:" . $inner->phpType . "'",
          'extendsGeneric' => 'list<' . $inner->phpType . '>',
        ];

      case PhpTypeKind::Union:
        $collapsed = $this->collapseUnionToParent($resolved);

        if ($collapsed !== null) {
          $imports[$collapsed['importFqcn']] = true;

          return [
            'returnsExpr' => $collapsed['shortName'] . '::class',
            'extendsGeneric' => $collapsed['shortName'],
          ];
        }

        // No covering parent — emit the raw union as the PHPStan generic but
        // pin the const to the first class member's class-string. Imports for
        // every member are added so the file references compile.
        foreach ($resolved->unionMembers as $m) {
          if ($m->importFqcn !== null) {
            $imports[$m->importFqcn] = true;
          }
        }

        $firstClass = null;

        foreach ($resolved->unionMembers as $m) {
          if ($m->kind === PhpTypeKind::ClassName) {
            $firstClass = $m;

            break;
          }
        }

        if ($firstClass === null) {
          return $this->returnFromScalar('bool');
        }

        return [
          'returnsExpr' => $firstClass->phpType . '::class',
          'extendsGeneric' => $resolved->phpType,
        ];
    }
  }

  /**
   * Build the scalar-return pair, sharing the runtime sentinel form
   * (`'int'`/`'string'`/`'bool'`/`'float'`) between the const value and the
   * PHPStan generic.
   *
   * @return array{returnsExpr: string, extendsGeneric: string}
   */
  private function returnFromScalar(string $scalar): array
  {
    return [
      'returnsExpr' => "'{$scalar}'",
      'extendsGeneric' => $scalar,
    ];
  }

  /**
   * If `$type` is a union (kind=Union) whose members are exactly the
   * subtypes of a known `UnionPlan` parent, return the parent's short name
   * and FQCN so callers can collapse the union to its discriminator-tagged
   * parent class.
   *
   * Returns null when:
   *   - `$type` is not a Union,
   *   - no `UnionPlan` matches the members one-for-one,
   *   - or the members include a non-classname shape.
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

      // Loose equality so the comparison ignores key order — the resolved
      // union's members are alphabetically sorted (TypeResolver::ksort) while
      // the plan's members track parent->subtypes declaration order.
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
   * Map prose-level scalar aliases (`Int`, `Str`, `Bool`) onto the canonical
   * wire-type tokens. Mirrors `TypeRenderer::normaliseReturnAlias` so a
   * future schema patch that broadens the prose vocabulary lands in one
   * place.
   */
  private function normaliseReturnAlias(string $candidate): string
  {
    return match ($candidate) {
      'Int', 'Integer' => 'Integer',
      'Str', 'Text' => 'String',
      'Bool', 'Boolean' => 'Boolean',
      default => $candidate,
    };
  }

  /**
   * Reject prose tokens that look like Telegram type names but are PHP
   * reserved keywords / built-ins. Returning false here causes the caller
   * to fall back to a safe scalar rather than minting a `Types\Int` /
   * `Types\Object` ghost class. Mirrors `TypeRenderer::isKnownSchemaType`.
   */
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
      'False' => true,
      'True' => true,
    ];

    return !isset($reserved[$name]);
  }

  /**
   * Extract a wire-type candidate from a natural-language "Returns …"
   * sentence.
   *
   * Extends `TypeRenderer::extractReturnedType` with two extra matchers
   * that pick up the prose shapes the type-side never sees but methods do:
   *
   *   - `Returns the <thing> as <X> on success.`     -> X
   *     (covers `Returns the uploaded File on success.` and the
   *      `Returns the new invite link as ChatInviteLink object.` family)
   *   - `… <X> object`                               -> X
   *     (covers `Returns basic information about the bot in form of a
   *      User object.` — `User` is the type but isn't the first PascalCase
   *      token after "Returns")
   *
   * The fallback chain runs the most specific matchers first; the very
   * last `<X> object` pattern is a wildcard scan that catches anything
   * the targeted patterns miss.
   */
  private function extractReturnedType(string $sentence): ?string
  {
    $patterns = [
      // "Returns an Array of <X>"
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+an\s+Array\s+of\s+([A-Z][A-Za-z0-9_]*)/',
      // "Returns [the | a | an] <X> as <Y>" (e.g. "Returns the new invite link as ChatInviteLink")
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:[a-zA-Z ]+?)\s+as\s+(?:a |an |the )?([A-Z][A-Za-z0-9_]*)/',
      // "Returns [the | a | an] <X>" (X is the next PascalCase token)
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:a |an |the )?([A-Z][A-Za-z0-9_]*)/',
      // "<X> [is | are] returned"
      '/(?<![A-Za-z])([A-Z][A-Za-z0-9_]*)\s+(?:is|are)\s+returned/',
      // Wildcard fallback: any "<X> object" in the sentence (e.g.
      // "Returns basic information about the bot in form of a User object.")
      '/\b([A-Z][A-Za-z0-9_]*)\s+object\b/',
    ];

    foreach ($patterns as $i => $pattern) {
      if (preg_match($pattern, $sentence, $m) === 1) {
        $token = $m[1];

        if ($i === 0) {
          return 'Array of ' . $token;
        }

        return $token;
      }
    }

    return null;
  }

  /**
   * Compute the PHPDoc-grade type for an annotation, when the PHP-level
   * declaration is lossy (lists collapse to `array`, union-of-lists ditto).
   * Mirrors `TypeRenderer::phpdocFor` to keep type-handling consistent
   * across the two renderers.
   */
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
   * Compute the PHP-level type declaration for a constructor parameter.
   * Lists and union-of-lists collapse to `array`; everything else renders
   * verbatim from the resolver. Mirrors `TypeRenderer::declTypeFor`.
   */
  private function declTypeFor(PhpType $type): string
  {
    if ($type->kind === PhpTypeKind::ListOf) {
      return 'array';
    }

    if ($type->kind === PhpTypeKind::Union) {
      foreach ($type->unionMembers as $m) {
        if ($m->kind === PhpTypeKind::ListOf) {
          return 'array';
        }
      }
    }

    return $type->phpType;
  }

  /**
   * Add all imports referenced by a resolved PhpType into the import map.
   * Skips entries inside the Methods namespace (the rendered class lives
   * there itself, so a self-namespace import would be redundant).
   *
   * @param array<string, true> $imports
   */
  private function collectImportsForType(PhpType $type, array &$imports): void
  {
    if ($type->importFqcn !== null && !str_starts_with($type->importFqcn, 'Gruven\\PhpBotGram\\Methods\\')) {
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
   * Split a multi-line description into trimmed lines for docblock emission.
   * Mirrors `TypeRenderer::splitDescription`.
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
   * Widen a PHP type declaration to admit null. Mirrors
   * `TypeRenderer::widenNullable`.
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
   * Widen a parameter's type declaration so it admits a `BotDefault`
   * sentinel alongside the natural type. Mirrors
   * `TypeRenderer::widenForBotDefault`.
   */
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
   * Sort the import set alphabetically by FQCN for stable output ordering.
   * Mirrors `TypeRenderer::sortImports`.
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
