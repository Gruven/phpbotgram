<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use LogicException;

/**
 * Stage 5 of the codegen pipeline.
 *
 * Walks `LoadedSchema::$types` and computes the **union resolution plan** for
 * every discriminator-tagged polymorphic parent (e.g. `BackgroundFill`,
 * `MessageOrigin`, `ChatBoostSource`, `ReactionType`, `MenuButton`, …): the
 * wire-field that carries the tag, and the ordered `(wireValue, childClassName)`
 * mapping the renderer (Task 2.10) uses to emit each `<Parent>Union::resolve()`
 * `match` arm.
 *
 * Wire-value extraction reads each child's annotation for the parent's
 * discriminator field and pulls the hard-coded literal out of the
 * human-readable description. Telegram's schema uses two stable phrasings in
 * the vendored 10.0 release:
 *
 *   1. `… always 'xxx'`                  — single-quoted literal
 *   2. `… must be xxx`                    — bare token at end of clause
 *
 * Both patterns are tried in order; the first match wins. If neither matches,
 * we fail loudly with a `LogicException` naming the child and the parent —
 * silently emitting a wrong arm would corrupt every `resolve()` call against
 * that union. New schema versions that introduce a third phrasing should add a
 * matcher here rather than hand-patch the per-entity `replace.yml`.
 *
 * Parents whose `subtypes.yml` does not supply a `discriminator:` (today:
 * `InputMessageContent` — structurally typed, no shared field; and
 * `MaybeInaccessibleMessage` — tagged on `date == 0`) cannot fit the
 * `match($payload['<field>'])` shape and are excluded from `plans()`. The
 * renderer handles them via a different code path that lives outside the scope
 * of this stage.
 */
final class UnionDetector
{
  /**
   * Ordered list of regex matchers tried against a child's discriminator
   * annotation description. First capture group is the wire literal.
   *
   * The first form covers ~14 unions (`Type of …, always 'solid'`,
   * `The member's status in the chat, always 'creator'`, etc.); the second
   * covers the remaining ~7 (`Scope type, must be default`, `Type of the
   * result, must be article`, etc.).
   *
   * @var list<string>
   */
  private const array DISCRIMINATOR_PATTERNS = [
    "/always\\s+'([^']+)'/i",
    '/must\\s+be\\s+([A-Za-z0-9_]+)/i',
  ];

  public function __construct(private readonly LoadedSchema $schema) {}

  /**
   * @return list<UnionPlan>
   */
  public function plans(): array
  {
    /** @var array<string, TypeEntity> $byName */
    $byName = [];

    foreach ($this->schema->types as $t) {
      $byName[$t->name] = $t;
    }

    /** @var list<UnionPlan> $plans */
    $plans = [];

    foreach ($this->schema->types as $parent) {
      if ($parent->subtypes === null) {
        continue;
      }

      if ($parent->discriminator === null) {
        // Tagged / structural unions (InputMessageContent,
        // MaybeInaccessibleMessage). Out of scope for this stage.
        continue;
      }

      $plans[] = $this->buildPlan($parent, $byName);
    }

    return $plans;
  }

  /**
   * @param array<string, TypeEntity> $byName
   */
  private function buildPlan(TypeEntity $parent, array $byName): UnionPlan
  {
    /** @var list<UnionMember> $members */
    $members = [];

    // PHPStan: $parent->subtypes is non-null because plans() guards on it before
    // calling buildPlan(), but reasserting keeps the local invariant explicit
    // for the linter at level 9.
    $subtypes = $parent->subtypes ?? [];

    // PHPStan: same guard for discriminator.
    $discriminator = $parent->discriminator ?? '';

    foreach ($subtypes as $childName) {
      $child = $byName[$childName] ?? null;

      if ($child === null) {
        throw new LogicException(
          "Union parent {$parent->name} references unknown subtype {$childName}",
        );
      }

      $members[] = new UnionMember(
        childClassName: $childName,
        wireValue: $this->extractWireValue($parent->name, $child, $discriminator),
      );
    }

    return new UnionPlan(
      parentName: $parent->name,
      discriminator: $discriminator,
      members: $members,
    );
  }

  private function extractWireValue(
    string $parentName,
    TypeEntity $child,
    string $discriminator,
  ): string {
    $anno = null;

    foreach ($child->annotations as $a) {
      if ($a->name === $discriminator) {
        $anno = $a;

        break;
      }
    }

    if ($anno === null) {
      throw new LogicException(
        "Union child {$child->name} (parent {$parentName}) is missing the discriminator annotation '{$discriminator}'",
      );
    }

    foreach (self::DISCRIMINATOR_PATTERNS as $pattern) {
      if (preg_match($pattern, $anno->description, $m) === 1) {
        return $m[1];
      }
    }

    throw new LogicException(
      "Union child {$child->name} (parent {$parentName}) discriminator annotation '{$discriminator}' description does not carry an extractable literal: " . $anno->description,
    );
  }
}
