<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use LogicException;
use PhpToken;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

/**
 * Stage 8 of the codegen pipeline.
 *
 * Detects hand-authored shortcuts traits living at
 * `src/Types/Shortcuts/<TypeName>Shortcuts.php` and emits a
 * `HandAuthoredShortcutPlan` per detected trait. The renderer (Task 2.10)
 * consumes these plans to insert `use <TypeName>Shortcuts;` directives on the
 * matching generated Type class.
 *
 * Shortcuts traits are the maintainer escape hatch for behavior that can't be
 * expressed through `aliases.yml`'s lowering grammar — e.g. a custom
 * `Message::isPm()` helper that inspects the chat type, or a `User::link()`
 * derivation that doesn't map to any TelegramMethod call. They live alongside
 * the schema-driven shortcuts and the integrator must guarantee no two
 * sources ever publish a same-named method on the same owner type. Silently
 * shadowing a generated method with a trait method would produce a confusing
 * "where does this implementation live" bug the next time someone adds a
 * matching alias.
 *
 * Fail-closed conditions:
 *   - A trait declares a public method whose name matches an `aliases.yml`-derived
 *     `ShortcutPlan::$phpMethodName` for the same owner type. The exception
 *     names both the alias and the trait so the maintainer can find the
 *     collision quickly.
 *
 * Tolerant skip conditions (deliberately permissive, since the directory is
 * the maintainer's editable surface):
 *   - The directory doesn't exist. Phase 1 ships without it; the renderer
 *     gets an empty plan list and emits no `use` directives.
 *   - A file in the directory doesn't match the `*Shortcuts.php` pattern.
 *     The maintainer may keep supporting files (`AbstractMixin.php`, etc.)
 *     adjacent to the traits; we don't impose a single-file-per-dir rule.
 *   - A `*Shortcuts.php` file declares something that isn't a trait
 *     (e.g. a class). Half-finished work in progress shouldn't block codegen.
 *
 * Reflection contract: traits are autoloadable via the project's PSR-4
 * autoloader iff their namespace matches the directory layout. For arbitrary
 * supplied directories (used by the test harness for isolated trait
 * declarations) we fall back to `include_once`. Either way, the reflection
 * pass enumerates only methods whose declaring class is the trait itself —
 * inherited methods can't exist on a trait, but the guard is cheap and
 * future-proofs against a maintainer accidentally extending a trait via
 * `use Other;` and then expecting the integrator to surface those methods.
 */
final class HandAuthoredShortcutsIntegrator
{
  /**
   * Pre-computed lookup map for collision detection.
   *
   * Indexed by owner type name and then by camelCased PHP method name so the
   * per-trait collision check is O(declaredMethods) rather than O(plans ×
   * declaredMethods). Built once at construction from the supplied
   * `ShortcutPlan` list.
   *
   * @var array<string, array<string, true>> ownerType -> phpMethodName set
   */
  private readonly array $aliasShortcutsByOwner;

  /**
   * @param list<ShortcutPlan> $shortcutPlans the `aliases.yml`-derived plans this
   *                                          integrator collision-checks against
   */
  public function __construct(
    private readonly string $shortcutsDir,
    array $shortcutPlans,
  ) {
    /** @var array<string, array<string, true>> $lookup */
    $lookup = [];

    foreach ($shortcutPlans as $plan) {
      $lookup[$plan->ownerTypeName][$plan->phpMethodName] = true;
    }

    $this->aliasShortcutsByOwner = $lookup;
  }

  /**
   * @return list<HandAuthoredShortcutPlan>
   */
  public function plans(): array
  {
    if (!is_dir($this->shortcutsDir)) {
      // Phase 1: directory absent. No-op.
      return [];
    }

    $entries = scandir($this->shortcutsDir);

    if ($entries === false) {
      // Race-y read after a directory existed at is_dir() time but vanished
      // before scandir() — treat as empty rather than throwing.
      return [];
    }

    // Sort entries so the emitted plan order is reproducible across
    // filesystems (HFS+ enumerates alphabetically, ext4 doesn't).
    sort($entries, SORT_STRING);

    /** @var list<HandAuthoredShortcutPlan> $plans */
    $plans = [];

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }

      $ownerName = $this->ownerNameFromFilename($entry);

      if ($ownerName === null) {
        // Filename doesn't match the `*Shortcuts.php` convention. Skip
        // without including — the file may be supporting material the
        // maintainer keeps alongside the trait files.
        continue;
      }

      $plan = $this->loadPlan($this->shortcutsDir . '/' . $entry, $ownerName);

      if ($plan === null) {
        continue;
      }

      $this->assertNoCollision($plan);

      $plans[] = $plan;
    }

    return $plans;
  }

  /**
   * Derive the owner type name (e.g. `Message`) from a filename
   * (e.g. `MessageShortcuts.php`). Returns null when the filename doesn't
   * match the `<Name>Shortcuts.php` convention.
   */
  private function ownerNameFromFilename(string $filename): ?string
  {
    if (!str_ends_with($filename, 'Shortcuts.php')) {
      return null;
    }

    $base = substr($filename, 0, -\strlen('Shortcuts.php'));

    if ($base === '') {
      return null;
    }

    return $base;
  }

  private function loadPlan(string $filepath, string $ownerName): ?HandAuthoredShortcutPlan
  {
    // include_once is idempotent and safe across repeated calls in the same
    // PHP process; PHP's trait-redeclaration error only fires when two files
    // declare a trait of the same FQCN.
    try {
      include_once $filepath;
    } catch (Throwable) {
      // A parse error or include failure in a maintainer's half-finished file
      // shouldn't fail codegen — skip the file and let the renderer carry on.
      return null;
    }

    $traitFqcn = $this->findDeclaredTrait($filepath);

    if ($traitFqcn === null) {
      return null;
    }

    // `trait_exists()` doubles as the load-check (it triggers autoload) AND
    // the type-narrowing guard PHPStan needs to accept the FQCN as a
    // `class-string` argument to ReflectionClass below. After this guard the
    // ReflectionClass constructor cannot raise — the class is known-loaded.
    if (!trait_exists($traitFqcn)) {
      return null;
    }

    $reflection = new ReflectionClass($traitFqcn);

    if (!$reflection->isTrait()) {
      // Defensive: trait_exists() already proved the symbol is a trait, but
      // double-checking against the reflection object guards against a
      // pathological PHP-edition mismatch where the two views diverge.
      return null;
    }

    /** @var list<string> $declaredMethods */
    $declaredMethods = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      // A trait can't extend another trait; getDeclaringClass() on a trait
      // method always returns the trait itself. The guard exists so a future
      // refactor that aliases a method via `use Other { foo as bar; }` doesn't
      // silently widen the integrator's surface area.
      if ($method->getDeclaringClass()->getName() !== $traitFqcn) {
        continue;
      }

      $declaredMethods[] = $method->getName();
    }

    return new HandAuthoredShortcutPlan(
      ownerTypeName: $ownerName,
      traitFqcn: $traitFqcn,
      traitShortName: $this->shortName($traitFqcn),
      declaredMethods: $declaredMethods,
    );
  }

  /**
   * Parse the file source to discover the FQCN of the trait it declares.
   *
   * Reflection alone can't enumerate just-included traits — `get_declared_traits()`
   * returns every trait the process has loaded, which is process-global state
   * that grows across test cases. Parsing the file via PHP's tokenizer lets
   * us pin the FQCN to this specific file deterministically.
   */
  private function findDeclaredTrait(string $filepath): ?string
  {
    $source = file_get_contents($filepath);

    if ($source === false) {
      return null;
    }

    $tokens = PhpToken::tokenize($source);
    $namespace = '';
    $traitName = null;
    $expectNamespace = false;
    $expectTraitName = false;
    $collectingNamespace = false;

    foreach ($tokens as $token) {
      if ($expectNamespace) {
        if ($token->is(T_NAME_QUALIFIED) || $token->is(T_NAME_FULLY_QUALIFIED) || $token->is(T_STRING)) {
          $namespace = ltrim($token->text, '\\');
          $expectNamespace = false;
          $collectingNamespace = false;

          continue;
        }

        if ($token->is(T_WHITESPACE)) {
          continue;
        }

        // `namespace;` / `namespace {}` — no name. Defensive bail-out.
        $expectNamespace = false;
        $collectingNamespace = false;

        continue;
      }

      if ($expectTraitName) {
        if ($token->is(T_STRING)) {
          $traitName = $token->text;

          break;
        }

        if ($token->is(T_WHITESPACE)) {
          continue;
        }

        // Unexpected token between `trait` and the name — bail.
        return null;
      }

      if ($token->is(T_NAMESPACE)) {
        $expectNamespace = true;
        $collectingNamespace = true;

        continue;
      }

      if ($token->is(T_TRAIT)) {
        $expectTraitName = true;

        continue;
      }
    }

    if ($traitName === null) {
      return null;
    }

    return $namespace === '' ? $traitName : $namespace . '\\' . $traitName;
  }

  private function shortName(string $fqcn): string
  {
    $pos = strrpos($fqcn, '\\');

    return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
  }

  private function assertNoCollision(HandAuthoredShortcutPlan $plan): void
  {
    $owned = $this->aliasShortcutsByOwner[$plan->ownerTypeName] ?? [];

    if ($owned === []) {
      return;
    }

    foreach ($plan->declaredMethods as $method) {
      if (isset($owned[$method])) {
        throw new LogicException(
          "Method collision on {$plan->ownerTypeName}: alias and trait '{$plan->traitShortName}' both declare '{$method}()'",
        );
      }
    }
  }
}
