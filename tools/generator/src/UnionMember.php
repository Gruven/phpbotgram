<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * One concrete child of a discriminator-tagged union.
 *
 * Pairs the child's PHP class-name (matches `TypeEntity::$name`, since the
 * downstream renderer never renames union children) with the literal wire-value
 * the parent's discriminator takes when the payload is this child's shape.
 *
 * Emitted as part of `UnionPlan::$members`; the renderer consumes the list to
 * generate the `match` arms inside each `<Parent>Union::resolve()` helper.
 */
final readonly class UnionMember
{
  public function __construct(
    public string $childClassName,
    public string $wireValue,
  ) {}
}
