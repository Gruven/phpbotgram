<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Per-trait integration plan emitted by `HandAuthoredShortcutsIntegrator`.
 *
 * Captures the four pieces of information the renderer (Task 2.10) needs to
 * weave a hand-authored shortcuts trait into the generated Type class:
 *
 *   - the owner type name (`Message`, `User`, …) — derived from the trait
 *     filename via the `<TypeName>Shortcuts.php` convention. The renderer uses
 *     it to match the trait against the generated class it's about to emit.
 *   - the trait's fully-qualified class name (FQCN) — what the renderer puts
 *     after `use ` in the generated class body.
 *   - the trait's short name (the unqualified class basename) — what the
 *     renderer actually writes after `use ` (the `use` statement at the top
 *     of the generated file already imports the FQCN).
 *   - the list of public method names the trait declares — the renderer uses
 *     this to collision-check against `aliases.yml`-derived shortcuts that
 *     also lower to instance methods on the same owner type.
 *
 * The integrator pre-checks the collision against the supplied `ShortcutPlan`
 * list and throws before returning, so by the time the renderer sees a
 * `HandAuthoredShortcutPlan` the conflict is already proven impossible. The
 * `$declaredMethods` field is preserved on the plan anyway so future stages
 * (e.g. a docblock generator) can introspect the trait's public surface
 * without re-reflecting on disk.
 */
final readonly class HandAuthoredShortcutPlan
{
  /**
   * @param list<string> $declaredMethods public method names declared on the trait
   */
  public function __construct(
    public string $ownerTypeName,
    public string $traitFqcn,
    public string $traitShortName,
    public array $declaredMethods,
  ) {}
}
