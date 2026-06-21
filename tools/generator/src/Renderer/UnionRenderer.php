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
   * @param array<string, TypeEntity> $typesByName indexed by type name. No
   *                                               longer load-bearing after
   *                                               the Cycle 3 marker-interface
   *                                               refactor; preserved on the
   *                                               constructor so external
   *                                               callers (the renderer test
   *                                               suite) keep their factory
   *                                               wiring unchanged.
   */
  public function __construct(
    private readonly Environment $twig,
    private readonly array $typesByName = [],
  ) {}

  /**
   * Emit the marker interface for this union (`<Parent>Interface`).
   *
   * The interface is empty — its sole role is to carry the multi-parent
   * union-membership for children whose canonical `extends` parent points
   * elsewhere. The abstract parent class declares `implements
   * <X>Interface` so single-parent children pick it up via inheritance;
   * multi-parent children declare it explicitly via
   * `TypeEntity::$additionalUnionMemberships`.
   */
  public function renderInterface(UnionPlan $plan): string
  {
    return $this->twig->render('union_interface.php.twig', [
      'class_name' => $plan->parentName . 'Interface',
      'namespace' => 'Gruven\\PhpBotGram\\Types',
      'parent_name' => $plan->parentName,
    ]);
  }

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

    // The resolver's return type is either the union's marker interface
    // (when at least one shadow member exists — its PHP `extends` chain
    // points to a different union, so PHPStan would reject it under the
    // abstract class return type) or the abstract class itself (the
    // common case — every member directly extends this parent).
    //
    // Detection: scan `typesByName` for any member whose canonical
    // `subtypeOf` doesn't point at this parent — these are the shadow
    // members surfaced via `additionalUnionMemberships`. The interface
    // emitted via `renderInterface()` collects them.
    $hasShadow = false;

    foreach ($plan->members as $m) {
      $child = $this->typesByName[$m->childClassName] ?? null;

      if ($child !== null && $child->subtypeOf !== $plan->parentName) {
        $hasShadow = true;

        break;
      }
    }

    $effectiveReturn = $hasShadow ? $plan->parentName . 'Interface' : $plan->parentName;

    return $this->twig->render('union.php.twig', [
      'class_name' => $plan->parentName . 'Union',
      'namespace' => 'Gruven\\PhpBotGram\\Types',
      'parent_name' => $plan->parentName,
      'return_type' => $effectiveReturn,
      'discriminator' => $this->escapeStringLiteral($plan->discriminator),
      'members' => $members,
      'all_members' => $allMembers,
      // When two children share a wire discriminator value (the
      // `InlineQueryResult` family's `audio`/`document`/`gif`/… are claimed
      // by both the cached and non-cached subtypes), a `match` resolver
      // would silently dispatch every payload to whichever child happens
      // to land in the table first. The template omits resolve() in that
      // case and emits a docblock explaining why; callers must use
      // `instanceof`-against-`members()` or a runtime payload heuristic
      // (e.g. presence of `audio_file_id` vs `audio_url`) instead.
      'has_ambiguous_discriminator' => $plan->hasAmbiguousDiscriminator,
    ]);
  }

  /**
   * Escape a wire-side literal for safe inclusion inside a PHP
   * single-quoted string.
   *
   * The vendored schema never ships a discriminator with embedded
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
