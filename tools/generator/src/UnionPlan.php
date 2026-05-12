<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Per-union resolution plan emitted by `UnionDetector`.
 *
 * Captures the three pieces of information the renderer (Task 2.10) needs to
 * emit the `<Parent>Union` helper class:
 *
 *   - the parent type name (drives the helper class name);
 *   - the wire-side discriminator field (`$payload['type']`, `$payload['source']`,
 *     `$payload['status']`, …);
 *   - the ordered (parent->subtypes order) list of `(childClassName, wireValue)`
 *     pairs that populate the `match` arms.
 *
 * Order preservation matters because the renderer also emits a `members(): list`
 * accessor used by callers performing exhaustive `instanceof` switches against
 * the union — keeping it in schema-declaration order avoids gratuitous diffs
 * when the upstream Telegram schema reshuffles its bullet list.
 */
final readonly class UnionPlan
{
  /**
   * @param list<UnionMember> $members
   * @param bool $hasAmbiguousDiscriminator true when two or more members
   *                                        share the same wire discriminator
   *                                        value. The renderer omits the
   *                                        `resolve()` helper in that case
   *                                        since a `match($payload[$disc])`
   *                                        would silently route every
   *                                        ambiguous payload to the first
   *                                        registered member.
   */
  public function __construct(
    public string $parentName,
    public string $discriminator,
    public array $members,
    public bool $hasAmbiguousDiscriminator = false,
  ) {}
}
