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
   */
  public function __construct(
    public string $parentName,
    public string $discriminator,
    public array $members,
  ) {}
}
