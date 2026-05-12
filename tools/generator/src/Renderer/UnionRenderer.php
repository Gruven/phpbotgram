<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator\Renderer;

use Gruven\PhpBotGram\Generator\TypeEntity;
use Gruven\PhpBotGram\Generator\UnionPlan;
use Twig\Environment;

/**
 * Renderer for the discriminator resolver class of a single tagged-union.
 *
 * Consumes one `UnionPlan` from `UnionDetector` and emits a final
 * `<Parent>Union` class with two static methods:
 *
 *   - `members(): list<class-string<Parent>>` — every subtype in declaration
 *     order; callers performing exhaustive `instanceof` switches can iterate
 *     this without re-deriving the membership.
 *   - `resolve(array $payload, ?Bot $bot = null): <Parent>` — a `match`
 *     expression keyed by `$payload[$discriminator]` that dispatches to
 *     `Serializer::load(<Child>::class, $payload, $bot)`; the default arm
 *     throws `ClientDecodeException` with a diagnostic.
 *
 * `$discriminator` is the wire field name (`type`, `source`, `status`, …)
 * captured by `UnionDetector` from each parent's `subtypes.yml`. Member
 * order matches `UnionPlan::$members` exactly — the renderer never re-sorts
 * so a future schema reshuffle is visible in the diff.
 *
 * Architectural mirror of `TypeRenderer` / `MethodRenderer`: heavy
 * preprocessing happens here, the Twig template is close to a
 * literal-text-with-`{{ }}`-holes form. cs-fixer polishes the final
 * whitespace at orchestration time.
 */
final class UnionRenderer
{
  /**
   * @param array<string, TypeEntity> $typesByName indexed by type name; used
   *                                               to detect "shadow" union
   *                                               parents whose subtypes
   *                                               already belong to another
   *                                               parent (e.g.
   *                                               `InputPollMedia` whose
   *                                               subtypes all extend
   *                                               `InputMedia`).
   */
  public function __construct(
    private readonly Environment $twig,
    private readonly array $typesByName = [],
  ) {}

  /**
   * Emit one `<Parent>Union` resolver class source.
   */
  public function render(UnionPlan $plan): string
  {
    /** @var list<array{className: string, wireValue: string}> $allMembers */
    $allMembers = [];

    foreach ($plan->members as $m) {
      $allMembers[] = [
        'className' => $m->childClassName,
        'wireValue' => $this->escapeStringLiteral($m->wireValue),
      ];
    }

    // Dedupe match arms by discriminator value, preserving first-wins
    // ordering. The vendored schema's InlineQueryResult union ships several
    // subtype pairs that share a discriminator literal (`audio` is reused by
    // both `InlineQueryResultCachedAudio` and `InlineQueryResultAudio`, etc.)
    // — PHP's `match` would emit unreachable arm warnings (and PHPStan
    // surfaces `match.alwaysFalse`) on the duplicates. The runtime
    // disambiguates via the payload's content keys (`audio_file_id` vs
    // `audio_url`); the resolver's job is just to pick a starting class,
    // which the first occurrence in declaration order already provides.
    /** @var array<string, true> $seen */
    $seen = [];

    /** @var list<array{className: string, wireValue: string}> $members */
    $members = [];

    foreach ($allMembers as $member) {
      if (isset($seen[$member['wireValue']])) {
        continue;
      }

      $seen[$member['wireValue']] = true;
      $members[] = $member;
    }

    // Detect the "shadow parent" case: the parent declares subtypes whose
    // PHP-level `subtypeOf` points to a different schema type. The
    // `InputPollMedia` and `InputPollOptionMedia` unions in the vendored
    // 10.0 schema have this shape — they enumerate the same InputMedia
    // subtypes (`InputMediaAnimation`, …) that already extend the
    // canonical `InputMedia` union parent. PHP's single inheritance
    // prevents these classes from extending both parents at once, so the
    // resolver must declare the actual common ancestor as the return type;
    // otherwise PHPStan surfaces `return.type` errors because the subtypes
    // aren't statically subtypes of the shadow parent.
    $effectiveReturn = $this->resolveEffectiveReturn($plan);

    return $this->twig->render('union.php.twig', [
      'class_name' => $plan->parentName . 'Union',
      'namespace' => 'Gruven\\PhpBotGram\\Types',
      'parent_name' => $plan->parentName,
      'return_type' => $effectiveReturn,
      'discriminator' => $this->escapeStringLiteral($plan->discriminator),
      'members' => $members,
      'all_members' => $allMembers,
    ]);
  }

  /**
   * Determine the PHP-level return type the resolver's `resolve()` method
   * should declare.
   *
   * Defaults to the union's parent type name. When the parent's subtypes
   * have a fragmented inheritance chain (the InputPollMedia /
   * InputPollOptionMedia "shadow union" case in the vendored 10.0
   * schema), we widen the return type to a common ancestor.
   *
   * The widening rules:
   *   - If every child legitimately considers this union as its `subtypeOf`,
   *     keep the parent name (the most precise return type).
   *   - If every child shares a single different `subtypeOf`, use that —
   *     covers the simple shadow case where one canonical parent dominates.
   *   - Otherwise — the children are scattered across multiple PHP-level
   *     hierarchies — fall back to `MutableTelegramObject` (since these
   *     unions exclusively wrap input-media types which all extend it
   *     transitively).
   */
  private function resolveEffectiveReturn(UnionPlan $plan): string
  {
    if ($this->typesByName === []) {
      return $plan->parentName;
    }

    /** @var array<string, true> $ancestors */
    $ancestors = [];
    $allMatchPlanParent = true;

    foreach ($plan->members as $m) {
      $childType = $this->typesByName[$m->childClassName] ?? null;

      if ($childType === null) {
        return $plan->parentName;
      }

      if ($childType->subtypeOf !== $plan->parentName) {
        $allMatchPlanParent = false;
      }

      $effectiveParent = $childType->subtypeOf ?? $plan->parentName;
      $ancestors[$effectiveParent] = true;
    }

    if ($allMatchPlanParent) {
      return $plan->parentName;
    }

    if (count($ancestors) === 1) {
      return (string)array_key_first($ancestors);
    }

    // Members scattered across multiple PHP hierarchies. The only common
    // schema ancestor at this point is the structurally lifted base
    // (`MutableTelegramObject`) — every InputMedia subtype in the vendored
    // 10.0 schema extends it transitively, so widening to it preserves
    // static type safety without introducing a synthetic intermediate.
    return 'MutableTelegramObject';
  }

  /**
   * Escape a wire-side literal for safe inclusion inside a PHP
   * single-quoted string.
   *
   * The vendored 10.0 schema never ships a discriminator with embedded
   * apostrophes or backslashes, but the escape costs nothing and makes
   * the renderer forward-compatible.
   */
  private function escapeStringLiteral(string $raw): string
  {
    return strtr($raw, [
      '\\' => '\\\\',
      "'" => "\\'",
    ]);
  }
}
