<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator\Renderer;

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
  public function __construct(private readonly Environment $twig) {}

  /**
   * Emit one `<Parent>Union` resolver class source.
   */
  public function render(UnionPlan $plan): string
  {
    /** @var list<array{className: string, wireValue: string}> $members */
    $members = [];

    foreach ($plan->members as $m) {
      $members[] = [
        'className' => $m->childClassName,
        'wireValue' => $this->escapeStringLiteral($m->wireValue),
      ];
    }

    return $this->twig->render('union.php.twig', [
      'class_name' => $plan->parentName . 'Union',
      'namespace' => 'Gruven\\PhpBotGram\\Types',
      'parent_name' => $plan->parentName,
      'discriminator' => $this->escapeStringLiteral($plan->discriminator),
      'members' => $members,
    ]);
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
