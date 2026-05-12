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
   * Cached set of schema type/enum names for O(1) validation of prose
   * tokens against the known schema. Lazily populated on first matcher hit.
   *
   * @var null|array<string, true>
   */
  private ?array $schemaTypeNames = null;

  /**
   * @param array<string, UnionPlan> $unionsByParent indexed by parent name
   */
  public function __construct(
    private readonly Environment $twig,
    private readonly TypeResolver $types,
    private readonly NameMapper $names,
    private readonly DefaultsResolver $defaults,
    private readonly array $unionsByParent,
    private readonly LoadedSchema $schema,
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
    // 1. Explicit `returning.parsed_type` override from replace.yml — the
    //    authoritative escape hatch for prose the matcher can't parse.
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

    // 2. Methods with no "Returns …" prose at all (e.g. `setWebhook`,
    //    documented out-of-band) fall back to bool — the historical convention
    //    upstream uses for status-only API calls.
    if ($method->returns === '') {
      return $this->returnFromScalar('bool');
    }

    // 3. Heuristic parse of the `Returns …` candidate sentences from the
    //    description. The matcher validates every captured token against the
    //    loaded schema's type/enum names, so a non-null return means we have
    //    a concrete wire type to dispatch on.
    $extracted = $this->extractReturnedType($method->returns);

    if ($extracted === null) {
      // Fail loudly: a schema-vendored method with a "Returns …" prose
      // sentence we can't parse is a wire contract corruption hazard. The
      // fix is either (a) tighten the prose matcher, or (b) add a
      // `returning.parsed_type` override in `replace.yml`. Surfacing the
      // failure at codegen time is the only safe option — silently
      // defaulting to `bool` would ship the wrong runtime decode behaviour.
      throw new LogicException(\sprintf(
        'Could not extract return type for method %s from prose "%s". '
          . 'Tighten MethodRenderer::extractReturnedType, or add a `returning:` '
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
   *
   * `True` and `False` are NOT rejected here — they're valid wire-type
   * tokens that `TypeResolver::resolveAtom` lowers to a `bool` scalar
   * (the `isTrueLiteral` flag tracks the literal narrowing). The reserved
   * list only filters tokens that would mint ghost classes.
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
    ];

    return !isset($reserved[$name]);
  }

  /**
   * Extract a wire-type candidate from a natural-language "Returns …"
   * sentence (or set of sentences joined by `\n` as `SchemaLoader::extractReturnsSentence`
   * emits).
   *
   * For each candidate sentence (in best-first order), tries a fallback
   * chain of regex matchers from most-specific to most-generic, validates
   * the captured token against the loaded schema's known types + scalar
   * names, and returns the first match whose token resolves. Validation is
   * critical: an unguarded match would happily mint `Types\Object` from a
   * prose phrase like "Returns information about the object on success.",
   * which `TypeResolver` would then try to import as a ghost class.
   *
   * Returns null when none of the candidate sentences yield a valid wire
   * type. The caller (`resolveReturnType`) then surfaces the failure as
   * a `LogicException` so the codegen halts loudly instead of silently
   * shipping a `bool` corruption — `bool` is plausible enough to pass
   * type-check but produces wrong runtime decode behaviour.
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
   * Run the prose-matcher chain on a single sentence. Returns `null` if
   * no pattern matched.
   *
   * @return null|array{token: string, isArray: bool}
   */
  private function matchSentence(string $sentence): ?array
  {
    // Specific patterns are tried first; anchors involving "Array" or
    // "array" set the isArray flag so the caller wraps the resolved type
    // in a `list<>` envelope.
    $arrayPatterns = [
      // "Returns an Array of <X>" — canonical schema phrasing.
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+an\s+Array\s+of\s+([A-Z][A-Za-z0-9_]*)/',
      // "an array of <X> objects? [phrasing] is returned" / "are returned".
      '/an?\s+array\s+of\s+([A-Z][A-Za-z0-9_]*)(?:\s+objects?)?\b[^.]*?\s+(?:is|are)\s+returned/i',
      // "On success, an array of <X> [phrasing] is returned" — the loader
      // strips terminal punctuation but the prose can include extra clauses
      // ("array of MessageId of the sent messages is returned") so we let
      // the inner content be anything except a sentence-terminator.
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+an\s+array\s+of\s+([A-Z][A-Za-z0-9_]*)/i',
    ];

    foreach ($arrayPatterns as $pattern) {
      if (preg_match($pattern, $sentence, $m) === 1) {
        return ['token' => $m[1], 'isArray' => true];
      }
    }

    $scalarPatterns = [
      // "Returns [the | a | an] <X> as <Y>" (e.g. "Returns the new invite
      // link as ChatInviteLink object.").
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:[a-zA-Z ]+?)\s+as\s+(?:a |an |the )?([A-Z][A-Za-z0-9_]*)/',
      // "<X> object is returned" / "<X> is returned" / "<X>s are returned".
      '/(?<![A-Za-z])([A-Z][A-Za-z0-9_]*)(?:\s+object)?\s+(?:is|are)\s+returned/',
      // "Returns [the | a | an] <X>" (X is the next PascalCase token,
      // immediately after the article).
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:a |an |the )?([A-Z][A-Za-z0-9_]*)/',
      // "Returns the <english adjective(s)> <X>" — the wider form covers
      // descriptors interleaved between the article and the type
      // ("Returns the uploaded File on success.", "Returns the new invite
      // link as a ChatInviteLink object.").
      '/(?:On success,\s*)?(?:Returns?|returns?)\s+(?:a |an |the )?(?:[a-z]+\s+)+([A-Z][A-Za-z0-9_]*)/',
      // Wildcard fallback: any "<X> object" in the sentence.
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
   * Reject prose tokens that look like Telegram type names but don't
   * correspond to any known schema entity / scalar. Used to fail fast on
   * matches against generic English nouns (`Returns information about
   * the object …` → `Object`) before the resolver tries to import a
   * non-existent class.
   *
   * Accepts:
   *   - Wire scalars: `Integer`, `String`, `Boolean`, `Float` (with their
   *     `Int`/`Bool`/`Str`/`Text` aliases).
   *   - Literal scalars: `True`, `False` (lowered to `bool` by the
   *     resolver — `isTrueLiteral` carries the narrowing forward).
   *   - Any name that resolves through `LoadedSchema::$types` / `$enums`.
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
   * Set of schema type names indexed for O(1) membership tests. Lazily
   * built on first call; the renderer's `TypeResolver` already carries
   * the same info but doesn't expose a public predicate.
   *
   * @return array<string, true>
   */
  private function schemaTypeNames(): array
  {
    if ($this->schemaTypeNames === null) {
      /** @var array<string, true> $names */
      $names = [];

      foreach ($this->schema->types as $t) {
        $names[$t->name] = true;
      }

      foreach ($this->schema->enums as $e) {
        $names[$e->name] = true;
      }

      $this->schemaTypeNames = $names;
    }

    return $this->schemaTypeNames;
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
