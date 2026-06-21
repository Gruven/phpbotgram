<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator\Renderer;

use Gruven\PhpBotGram\Generator\Renderer\UnionRenderer;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeEntity;
use Gruven\PhpBotGram\Generator\TypeOverrideApplier;
use Gruven\PhpBotGram\Generator\UnionDetector;
use Gruven\PhpBotGram\Generator\UnionPlan;
use PHPUnit\Framework\TestCase;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\Renderer\UnionRenderer
 */
final class UnionRendererTest extends TestCase
{
  /** @var array<string, UnionPlan> */
  private static array $plansByParent = [];

  private static ?UnionRenderer $renderer = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 3) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    $loaded = new TypeOverrideApplier($loader->load())->apply();

    foreach (new UnionDetector($loaded)->plans() as $plan) {
      self::$plansByParent[$plan->parentName] = $plan;
    }

    /** @var array<string, TypeEntity> $typesByName */
    $typesByName = [];

    foreach ($loaded->types as $t) {
      $typesByName[$t->name] = $t;
    }

    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/tools/generator/templates'), [
      'autoescape' => false,
      'strict_variables' => true,
    ]);

    self::$renderer = new UnionRenderer($twig, $typesByName);
  }

  public function testRendersBackgroundFillUnion(): void
  {
    $out = $this->render('BackgroundFill');

    self::assertStringContainsString("<?php\n\ndeclare(strict_types=1);\n\nnamespace Gruven\\PhpBotGram\\Types;", $out);
    self::assertStringContainsString('@generated do not edit; regenerate via `make regenerate`', $out);
    self::assertMatchesRegularExpression('/final\s+class\s+BackgroundFillUnion/', $out);

    // Imports.
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Bot;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Client\\Serializer;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Exceptions\\ClientDecodeException;', $out);

    // members() body lists every child in declaration order.
    self::assertStringContainsString('BackgroundFillSolid::class,', $out);
    self::assertStringContainsString('BackgroundFillGradient::class,', $out);
    self::assertStringContainsString('BackgroundFillFreeformGradient::class,', $out);

    // resolve() match arms keyed by the wire discriminator.
    self::assertStringContainsString("\$discriminator = \$payload['type'] ?? null;", $out);
    self::assertStringContainsString('match (is_string($discriminator) ? $discriminator : null)', $out);
    self::assertStringContainsString("'solid' => Serializer::load(BackgroundFillSolid::class, \$payload, \$bot),", $out);
    self::assertStringContainsString("'gradient' => Serializer::load(BackgroundFillGradient::class, \$payload, \$bot),", $out);
    self::assertStringContainsString("'freeform_gradient' => Serializer::load(BackgroundFillFreeformGradient::class, \$payload, \$bot),", $out);

    // default arm throws ClientDecodeException with helpful diagnostic.
    self::assertStringContainsString('default => throw new ClientDecodeException(', $out);
    self::assertStringContainsString('Unknown BackgroundFill type', $out);
  }

  public function testRendersMessageOriginUnion(): void
  {
    $out = $this->render('MessageOrigin');

    self::assertMatchesRegularExpression('/final\s+class\s+MessageOriginUnion/', $out);
    self::assertStringContainsString("\$discriminator = \$payload['type'] ?? null;", $out);
    self::assertStringContainsString('match (is_string($discriminator) ? $discriminator : null)', $out);

    // Four members.
    self::assertStringContainsString("'user' => Serializer::load(MessageOriginUser::class", $out);
    self::assertStringContainsString("'hidden_user' => Serializer::load(MessageOriginHiddenUser::class", $out);
    self::assertStringContainsString("'chat' => Serializer::load(MessageOriginChat::class", $out);
    self::assertStringContainsString("'channel' => Serializer::load(MessageOriginChannel::class", $out);
  }

  public function testRendersChatBoostSourceUnionWithSourceDiscriminator(): void
  {
    $out = $this->render('ChatBoostSource');

    self::assertMatchesRegularExpression('/final\s+class\s+ChatBoostSourceUnion/', $out);
    // Discriminator is `source` here (not `type`).
    self::assertStringContainsString("\$discriminator = \$payload['source'] ?? null;", $out);
    self::assertStringContainsString('match (is_string($discriminator) ? $discriminator : null)', $out);
  }

  /**
   * Cycle 1 review fix: a union whose member discriminator values collide
   * (e.g. `InlineQueryResult` has 16 children where 8 cached + 8 non-cached
   * subtypes share `audio`/`document`/`gif`/…/`voice`) cannot have a safe
   * `resolve()` helper — `match($payload['type'])` would dispatch only to
   * the first-registered member, silently making the rest unreachable.
   *
   * The renderer must skip emitting `resolve()` for such unions and emit
   * only `members()` plus a docblock that explains why dispatch is unsafe.
   * The Telegram API never returns `InlineQueryResult` in any wire response
   * (it's input-only via `answerInlineQuery`), so this is a defensive fix
   * for dead code that was actively misleading.
   */
  public function testInlineQueryResultUnionOmitsResolveWhenDiscriminatorsCollide(): void
  {
    $out = $this->render('InlineQueryResult');

    // The members() listing must remain — callers performing exhaustive
    // instanceof switches still use it.
    self::assertMatchesRegularExpression(
      '/public static function members\\(\\): array/',
      $out,
      'members() must remain even when resolve() is unsafe',
    );

    // resolve() MUST be absent because `audio`/`document`/`gif`/`mpeg4_gif`/
    // `photo`/`sticker`/`video`/`voice` each appear on two children.
    self::assertDoesNotMatchRegularExpression(
      '/public static function resolve\\(/',
      $out,
      'resolve() must be omitted for unions with ambiguous discriminator values',
    );

    // The renderer should document why resolve() is missing.
    self::assertMatchesRegularExpression(
      '/ambiguous|cannot.*discriminator|unsafe/i',
      $out,
      'Class docblock must explain why resolve() is unsafe',
    );

    // Imports unused by the trimmed class must drop too (Serializer/
    // ClientDecodeException/RuntimeException are only referenced from
    // inside resolve()). Asserting on import absence is brittle, but
    // worth a smoke check that the renderer didn't leak them.
    self::assertStringNotContainsString(
      'Gruven\\PhpBotGram\\Client\\Serializer',
      $out,
      'Serializer import should drop when resolve() is omitted',
    );
  }

  /**
   * Cycle 1 review fix: unions with non-colliding discriminators (the
   * common case) must still emit resolve(). This guards the positive
   * branch of `hasAmbiguousDiscriminator`.
   */
  public function testBackgroundFillUnionKeepsResolveWhenDiscriminatorsUnique(): void
  {
    $out = $this->render('BackgroundFill');

    self::assertMatchesRegularExpression(
      '/public static function resolve\\(array \\$payload, \\?Bot \\$bot = null\\): BackgroundFill/',
      $out,
    );
    self::assertStringContainsString('Gruven\\PhpBotGram\\Client\\Serializer', $out);
  }

  public function testRendersMaybeInaccessibleMessageStructuralUnion(): void
  {
    $out = $this->renderer()->renderMaybeInaccessibleMessage();

    self::assertMatchesRegularExpression('/final\s+class\s+MaybeInaccessibleMessageUnion/', $out);
    self::assertStringContainsString('Message::class,', $out);
    self::assertStringContainsString('InaccessibleMessage::class,', $out);
    self::assertStringContainsString("if ((\$payload['date'] ?? null) === 0)", $out);
    self::assertStringContainsString('Serializer::load(InaccessibleMessage::class, $payload, $bot)', $out);
    self::assertStringContainsString('Serializer::load(Message::class, $payload, $bot)', $out);
  }

  public function testReturnTypeAndStaticMethodsHavePhpDoc(): void
  {
    $out = $this->render('BackgroundFill');

    // members() return annotation.
    self::assertMatchesRegularExpression(
      '/@return list<class-string<BackgroundFill>>/',
      $out,
    );

    // resolve() signature: array payload + optional Bot, returns the parent.
    self::assertMatchesRegularExpression(
      '/public static function resolve\\(array \\$payload, \\?Bot \\$bot = null\\): BackgroundFill/',
      $out,
    );
    // members() static method.
    self::assertMatchesRegularExpression(
      '/public static function members\\(\\): array/',
      $out,
    );
  }

  /**
   * Sanity sweep: render every detected union plan and verify each emits
   * valid PHP.
   */
  public function testAllUnionsAreValidPhp(): void
  {
    $failed = [];

    foreach (self::$plansByParent as $name => $plan) {
      try {
        $out = $this->renderer()->render($plan);
      } catch (Throwable $e) {
        $failed[$name] = 'render: ' . $e->getMessage();

        continue;
      }

      $tmp = tempnam(sys_get_temp_dir(), 'phpbg_union_');

      if ($tmp === false) {
        self::fail('Failed to create temp file');
      }

      try {
        file_put_contents($tmp, $out);
        $cmd = 'php -l ' . escapeshellarg($tmp) . ' 2>&1';
        $output = shell_exec($cmd);

        if (!str_contains((string)$output, 'No syntax errors detected')) {
          $failed[$name] = "lint: {$output}\n--- source ---\n{$out}";
        }
      } finally {
        @unlink($tmp);
      }
    }

    self::assertSame(
      [],
      $failed,
      "Some rendered unions failed php -l:\n" . implode("\n\n", array_map(
        static fn(string $name, string $msg): string => "  [{$name}]: " . substr($msg, 0, 800),
        array_keys($failed),
        array_values($failed),
      )),
    );
  }

  private function render(string $parentName): string
  {
    $plan = self::$plansByParent[$parentName] ?? null;

    if ($plan === null) {
      self::fail("Union plan for {$parentName} not present in schema");
    }

    return $this->renderer()->render($plan);
  }

  private function renderer(): UnionRenderer
  {
    $r = self::$renderer;

    if ($r === null) {
      self::fail('Renderer not initialised');
    }

    return $r;
  }
}
