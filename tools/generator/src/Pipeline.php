<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use Gruven\PhpBotGram\Generator\Renderer\BotRenderer;
use Gruven\PhpBotGram\Generator\Renderer\EnumRenderer;
use Gruven\PhpBotGram\Generator\Renderer\MethodRenderer;
use Gruven\PhpBotGram\Generator\Renderer\TypeRenderer;
use Gruven\PhpBotGram\Generator\Renderer\UnionRenderer;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Orchestrator for the full Phase 2 codegen pipeline (Task 2.12).
 *
 * Composes every stage built across Tasks 2.2–2.10c into a single
 * `run()` method that turns a `.butcher/` schema directory into a fully
 * regenerated `src/` tree on disk:
 *
 *   1. `SchemaLoader::load()`           — read `.butcher/schema/schema.json`
 *      and the per-entity patches into a `LoadedSchema`.
 *   2. `TypeOverrideApplier::apply()`   — propagate union-parent base lifts.
 *   3. `NameMapper`                     — pure stateless mapper, no input.
 *   4. `TypeResolver`                   — wire-type / parsed-type lowering.
 *   5. `UnionDetector::plans()`         — discriminator-tagged union plans.
 *   6. `ShortcutDetector::plans()`      — `aliases.yml`-driven shortcuts.
 *   7. `DefaultsResolver`               — per-parameter constructor defaults.
 *   8. `HandAuthoredShortcutsIntegrator::plans()` — trait integration plans
 *      from `src/Types/Shortcuts/`.
 *   9. Build Twig env + renderer fleet.
 *  10. Render each entity (sorted alphabetical by name) and emit through
 *      `FileEmitter`, recording the per-path outcome (written|skipped).
 *  11. Render the union resolvers (sorted alphabetical by parent).
 *  12. Render the single Bot facade.
 *  13. Single-shot cs-fixer pass over the whole `$outDir`.
 *
 * Determinism guarantee: every iteration order in this class is anchored to
 * an explicit alphabetical sort by name. Given the same `.butcher/` input,
 * two successive `run()` invocations produce byte-identical trees (verified
 * by `PipelineTest::testDeterministic`).
 *
 * The cs-fixer pass runs as a subprocess against `$outDir`. The pipeline's
 * `$repoRoot` is used as the cwd so cs-fixer can locate `.php-cs-fixer.dist.php`
 * by traversing upward from the target tree.
 */
final class Pipeline
{
  public function __construct(
    private readonly string $schemaDir,
    private readonly string $outDir,
    private readonly string $fixerBin = 'vendor/bin/php-cs-fixer',
    private readonly string $repoRoot = '.',
  ) {}

  /**
   * Execute the full pipeline and return a per-path manifest.
   *
   * @return array{written: list<string>, skipped: list<string>}
   */
  public function run(): array
  {
    // Stages 1-2: load + apply structural overrides.
    $loaded = new TypeOverrideApplier(new SchemaLoader($this->schemaDir)->load())->apply();

    // Stages 3-7: supporting plans the renderers consume.
    $names = new NameMapper();
    $types = new TypeResolver($loaded);
    $defaults = new DefaultsResolver($loaded);

    $shortcutPlans = new ShortcutDetector($loaded, $names)->plans();

    /** @var array<string, list<ShortcutPlan>> $shortcutsByOwner */
    $shortcutsByOwner = [];

    foreach ($shortcutPlans as $plan) {
      $shortcutsByOwner[$plan->ownerTypeName][] = $plan;
    }

    /** @var array<string, UnionPlan> $unionsByParent */
    $unionsByParent = [];

    foreach (new UnionDetector($loaded)->plans() as $u) {
      $unionsByParent[$u->parentName] = $u;
    }

    // Stage 8: hand-authored shortcuts traits. Anchored at the OUTPUT tree
    // (`$outDir/Types/Shortcuts`) because that's where the maintainer drops
    // trait files; the integrator both detects the trait FQCN and
    // collision-checks against the alias-driven shortcuts above.
    $traitPlans = new HandAuthoredShortcutsIntegrator(
      shortcutsDir: $this->outDir . '/Types/Shortcuts',
      shortcutPlans: $shortcutPlans,
    )->plans();

    /** @var array<string, HandAuthoredShortcutPlan> $traitsByOwner */
    $traitsByOwner = [];

    foreach ($traitPlans as $plan) {
      $traitsByOwner[$plan->ownerTypeName] = $plan;
    }

    /** @var array<string, MethodEntity> $methodsByName */
    $methodsByName = [];

    foreach ($loaded->methods as $m) {
      $methodsByName[$m->name] = $m;
    }

    // Stage 9: Twig environment shared by every renderer instance.
    $twig = new Environment(
      new FilesystemLoader($this->resolveTemplateDir()),
      [
        'autoescape' => false,
        'strict_variables' => true,
      ],
    );

    $typeRenderer = new TypeRenderer(
      twig: $twig,
      types: $types,
      names: $names,
      defaults: $defaults,
      unionsByParent: $unionsByParent,
      shortcutsByOwner: $shortcutsByOwner,
      traitsByOwner: $traitsByOwner,
      methodsByName: $methodsByName,
    );

    $methodRenderer = new MethodRenderer(
      twig: $twig,
      types: $types,
      names: $names,
      defaults: $defaults,
      unionsByParent: $unionsByParent,
    );

    $enumRenderer = new EnumRenderer(twig: $twig, names: $names, schema: $loaded);
    $unionRenderer = new UnionRenderer(twig: $twig);

    $botRenderer = new BotRenderer(
      twig: $twig,
      types: $types,
      names: $names,
      defaults: $defaults,
    );

    $emitter = new FileEmitter($this->outDir);

    /** @var list<string> $written */
    $written = [];

    /** @var list<string> $skipped */
    $skipped = [];

    // Stage 10: types (sorted alphabetical for determinism).
    foreach ($this->sortedByName($loaded->types) as $type) {
      $src = $typeRenderer->render($type);
      $rel = 'Types/' . $type->name . '.php';
      $this->record($emitter->emit($rel, $src), $rel, $written, $skipped);
    }

    // Stage 10: methods (sorted alphabetical, lowercase Wire name preserved
    // by ucfirst on disk).
    foreach ($this->sortedByName($loaded->methods) as $method) {
      $src = $methodRenderer->render($method);
      $rel = 'Methods/' . ucfirst($method->name) . '.php';
      $this->record($emitter->emit($rel, $src), $rel, $written, $skipped);
    }

    // Stage 10: enums (sorted alphabetical).
    foreach ($this->sortedByName($loaded->enums) as $enum) {
      $src = $enumRenderer->render($enum);
      $rel = 'Enums/' . $enum->name . '.php';
      $this->record($emitter->emit($rel, $src), $rel, $written, $skipped);
    }

    // Stage 11: union resolvers (sorted alphabetical by parent name).
    $unionPlans = array_values($unionsByParent);
    usort($unionPlans, static fn(UnionPlan $a, UnionPlan $b): int => strcmp($a->parentName, $b->parentName));

    foreach ($unionPlans as $plan) {
      $src = $unionRenderer->render($plan);
      $rel = 'Types/' . $plan->parentName . 'Union.php';
      $this->record($emitter->emit($rel, $src), $rel, $written, $skipped);
    }

    // Stage 12: Bot facade — the single non-namespaced output.
    $botSrc = $botRenderer->render($loaded);
    $this->record($emitter->emit('Bot.php', $botSrc), 'Bot.php', $written, $skipped);

    // Stage 13: cs-fixer pass over the entire output tree. Single shot so
    // the per-file invocation overhead (Twig + autoload + parallel-pool
    // teardown) is amortised once.
    $this->runFixer();

    return ['written' => $written, 'skipped' => $skipped];
  }

  /**
   * Sort a list of `{name: string, ...}` value objects alphabetically by name.
   * Generic helper to keep stage-10 iteration order deterministic.
   *
   * @template T of EnumEntity|MethodEntity|TypeEntity
   *
   * @param list<T> $list
   *
   * @return list<T>
   */
  private function sortedByName(array $list): array
  {
    usort($list, static fn(object $a, object $b): int => strcmp($a->name, $b->name));

    return $list;
  }

  /**
   * Record an emit-outcome against the appropriate manifest list.
   *
   * @param 'skipped'|'written' $outcome
   * @param list<string> $written
   * @param list<string> $skipped
   */
  private function record(string $outcome, string $path, array &$written, array &$skipped): void
  {
    if ($outcome === 'written') {
      $written[] = $path;
    } else {
      $skipped[] = $path;
    }
  }

  /**
   * Locate the Twig templates directory. Resolution order:
   *   1. The vendored copy adjacent to this file
   *      (`tools/generator/templates/`) — production layout.
   *   2. A test override path under the supplied `$repoRoot`. This branch
   *      only fires for the integration tests that ship a synthetic schema
   *      but want to pick up the canonical templates.
   */
  private function resolveTemplateDir(): string
  {
    $vendored = __DIR__ . '/../templates';

    if (is_dir($vendored)) {
      return $vendored;
    }

    $repo = rtrim($this->repoRoot, '/') . '/tools/generator/templates';

    if (is_dir($repo)) {
      return $repo;
    }

    throw new RuntimeException("Could not locate Twig templates dir (tried {$vendored} and {$repo})");
  }

  /**
   * Run `vendor/bin/php-cs-fixer fix` against `$outDir`. Throws on non-zero
   * exit so the orchestrator surfaces fixer failures rather than emitting
   * an unformatted tree silently.
   */
  private function runFixer(): void
  {
    $cmd = sprintf(
      '%s fix --quiet %s 2>&1',
      escapeshellarg($this->fixerBin),
      escapeshellarg($this->outDir),
    );

    $cwd = $this->repoRoot;
    $previousCwd = getcwd();

    if (!is_dir($cwd)) {
      throw new RuntimeException("Pipeline repoRoot is not a directory: {$cwd}");
    }

    if (!chdir($cwd)) {
      throw new RuntimeException("Failed to chdir to repoRoot for cs-fixer: {$cwd}");
    }

    try {
      /** @var list<string> $output */
      $output = [];
      $exitCode = 0;
      exec($cmd, $output, $exitCode);

      if ($exitCode !== 0) {
        throw new RuntimeException(
          "cs-fixer failed (exit {$exitCode}):\n" . implode("\n", $output),
        );
      }
    } finally {
      if ($previousCwd !== false) {
        chdir($previousCwd);
      }
    }
  }
}
