<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator\Renderer;

use Gruven\PhpBotGram\Generator\DefaultsResolver;
use Gruven\PhpBotGram\Generator\HandAuthoredShortcutPlan;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\Renderer\TypeRenderer;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\ShortcutDetector;
use Gruven\PhpBotGram\Generator\ShortcutPlan;
use Gruven\PhpBotGram\Generator\TypeEntity;
use Gruven\PhpBotGram\Generator\TypeOverrideApplier;
use Gruven\PhpBotGram\Generator\TypeResolver;
use Gruven\PhpBotGram\Generator\UnionDetector;
use Gruven\PhpBotGram\Generator\UnionPlan;
use PHPUnit\Framework\TestCase;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\Renderer\TypeRenderer
 */
final class TypeRendererTest extends TestCase
{
  private static ?LoadedSchema $schema = null;

  /** @var array<string, TypeEntity> */
  private static array $typesByName = [];

  private static ?TypeRenderer $renderer = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 3) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    $loaded = new TypeOverrideApplier($loader->load())->apply();
    self::$schema = $loaded;

    foreach ($loaded->types as $t) {
      self::$typesByName[$t->name] = $t;
    }

    $names = new NameMapper();
    $types = new TypeResolver($loaded);
    $defaults = new DefaultsResolver($loaded);
    $shortcuts = new ShortcutDetector($loaded, $names)->plans();

    /** @var array<string, list<ShortcutPlan>> $shortcutsByOwner */
    $shortcutsByOwner = [];

    foreach ($shortcuts as $plan) {
      $shortcutsByOwner[$plan->ownerTypeName][] = $plan;
    }

    /** @var array<string, UnionPlan> $unionsByParent */
    $unionsByParent = [];

    foreach (new UnionDetector($loaded)->plans() as $u) {
      $unionsByParent[$u->parentName] = $u;
    }

    /** @var array<string, MethodEntity> $methodsByName */
    $methodsByName = [];

    foreach ($loaded->methods as $m) {
      $methodsByName[$m->name] = $m;
    }

    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/tools/generator/templates'), [
      'autoescape' => false,
      'strict_variables' => true,
    ]);

    self::$renderer = new TypeRenderer(
      twig: $twig,
      types: $types,
      names: $names,
      defaults: $defaults,
      unionsByParent: $unionsByParent,
      shortcutsByOwner: $shortcutsByOwner,
      traitsByOwner: [],
      methodsByName: $methodsByName,
    );
  }

  public function testRendersUserHeaderAndConstructor(): void
  {
    $out = $this->render('User');

    self::assertStringContainsString("<?php\n\ndeclare(strict_types=1);\n\nnamespace Gruven\\PhpBotGram\\Types;", $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Bot;', $out);
    self::assertMatchesRegularExpression('/final\s+class\s+User\s+extends\s+TelegramObject/', $out);
    self::assertStringContainsString('public readonly int $id,', $out);
    self::assertStringContainsString('public readonly bool $isBot,', $out);
    self::assertStringContainsString('public readonly string $firstName,', $out);
    // Optional widens to nullable, with `= null` default.
    self::assertStringContainsString('public readonly ?string $lastName = null,', $out);
    // Trailing bot parameter.
    self::assertStringContainsString('?Bot $bot = null,', $out);
    self::assertStringContainsString('parent::__construct($bot);', $out);

    // Shortcut from aliases.yml.
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Methods\\GetUserProfilePhotos;', $out);
    self::assertMatchesRegularExpression('/public function getProfilePhotos\\(/', $out);
    self::assertStringContainsString('return new GetUserProfilePhotos(', $out);
    self::assertStringContainsString('userId: $this->id', $out);
    self::assertStringContainsString('bot: $this->bot', $out);
  }

  public function testRendersMessageWireNamesAndDateTimeOverride(): void
  {
    $out = $this->render('Message');

    // The `from` field renames to fromUser and must surface in WireNames.
    self::assertMatchesRegularExpression(
      "/public const array WireNames = \\[[^\\]]*'fromUser'\\s*=>\\s*'from'/s",
      $out,
    );

    // Custom DateTime import (from replace.yml parsed_type override).
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\Custom\\DateTime;', $out);
    self::assertStringContainsString('public readonly DateTime $date,', $out);

    // Renamed property surfaces in ctor as ?User $fromUser.
    self::assertStringContainsString('public readonly ?User $fromUser = null,', $out);

    // Message is a tagged-union child of `MaybeInaccessibleMessage`; the
    // renderer routes the `extends` clause to the union parent for every
    // schema-declared subtype.
    self::assertMatchesRegularExpression('/final\s+class\s+Message\s+extends\s+MaybeInaccessibleMessage/', $out);
  }

  public function testRendersUnionParentAsAbstract(): void
  {
    $out = $this->render('BackgroundFill');

    self::assertMatchesRegularExpression('/abstract\s+class\s+BackgroundFill\s+extends\s+TelegramObject/', $out);
    self::assertStringNotContainsString('final class', $out);
    // No WireNames const on a parent without renames.
    self::assertStringNotContainsString('public const array WireNames', $out);
    // Union parents emit no annotation-driven properties (subtype-only) — they
    // simply provide a base class for `extends` resolution.
  }

  public function testRendersUnionChildExtendsParent(): void
  {
    $out = $this->render('BackgroundFillSolid');

    self::assertMatchesRegularExpression(
      '/final\s+class\s+BackgroundFillSolid\s+extends\s+BackgroundFill/',
      $out,
    );

    // Discriminator literal must appear as a default value on the `type` param.
    self::assertMatchesRegularExpression(
      "/public readonly string \\\$type\\s*=\\s*'solid'/",
      $out,
    );
  }

  public function testRendersInlineKeyboardMarkupAsMutable(): void
  {
    $out = $this->render('InlineKeyboardMarkup');

    self::assertMatchesRegularExpression(
      '/final\s+class\s+InlineKeyboardMarkup\s+extends\s+MutableTelegramObject/',
      $out,
    );
    // Nested list-of-list — list<list<InlineKeyboardButton>> as a PHPDoc, array as ctor type.
    self::assertStringContainsString('public readonly array $inlineKeyboard,', $out);
    self::assertStringContainsString('list<list<InlineKeyboardButton>>', $out);
  }

  public function testRendersChatFullInfoExtendsChat(): void
  {
    $out = $this->render('ChatFullInfo');

    self::assertMatchesRegularExpression(
      '/final\s+class\s+ChatFullInfo\s+extends\s+Chat/',
      $out,
    );
  }

  public function testChatHasNoWireNamesConst(): void
  {
    // Chat has neither `from` nor any other renamed property — the WireNames
    // const must be omitted entirely.
    $out = $this->render('Chat');

    self::assertStringNotContainsString('public const array WireNames', $out);
  }

  public function testHandAuthoredTraitInjectsUseDirective(): void
  {
    $names = new NameMapper();
    $types = new TypeResolver(self::schema());
    $defaults = new DefaultsResolver(self::schema());
    $shortcuts = new ShortcutDetector(self::schema(), $names)->plans();

    /** @var array<string, list<ShortcutPlan>> $shortcutsByOwner */
    $shortcutsByOwner = [];

    foreach ($shortcuts as $plan) {
      $shortcutsByOwner[$plan->ownerTypeName][] = $plan;
    }

    /** @var array<string, UnionPlan> $unionsByParent */
    $unionsByParent = [];

    foreach (new UnionDetector(self::schema())->plans() as $u) {
      $unionsByParent[$u->parentName] = $u;
    }

    /** @var array<string, MethodEntity> $methodsByName */
    $methodsByName = [];

    foreach (self::schema()->methods as $m) {
      $methodsByName[$m->name] = $m;
    }

    $trait = new HandAuthoredShortcutPlan(
      ownerTypeName: 'Message',
      traitFqcn: 'Gruven\\PhpBotGram\\Types\\Shortcuts\\MessageShortcuts',
      traitShortName: 'MessageShortcuts',
      declaredMethods: ['isPm'],
    );

    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/tools/generator/templates'), [
      'autoescape' => false,
      'strict_variables' => true,
    ]);

    $renderer = new TypeRenderer(
      twig: $twig,
      types: $types,
      names: $names,
      defaults: $defaults,
      unionsByParent: $unionsByParent,
      shortcutsByOwner: $shortcutsByOwner,
      traitsByOwner: ['Message' => $trait],
      methodsByName: $methodsByName,
    );

    $out = $renderer->render(self::$typesByName['Message']);

    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\Shortcuts\\MessageShortcuts;', $out);
    // The `use <Trait>;` directive must appear inside the class body — assert
    // it lives between `class Message extends ...` and the constructor.
    self::assertMatchesRegularExpression(
      '/class\s+Message[^{]*\{[\s]*use\s+MessageShortcuts;/',
      $out,
    );
  }

  /**
   * Sanity sweep: render every type in the loaded schema and assert each emit
   * is valid PHP via `php -l`. Skips the hand-written `TelegramObject` /
   * `MutableTelegramObject` / `Custom\DateTime` allow-list since those are
   * never emitted by the renderer.
   */
  public function testAllTypesAreValidPhp(): void
  {
    $failed = [];

    foreach (self::schema()->types as $type) {
      try {
        $out = $this->renderer()->render($type);
      } catch (Throwable $e) {
        $failed[$type->name] = 'render: ' . $e->getMessage();

        continue;
      }

      $tmp = tempnam(sys_get_temp_dir(), 'phpbg_render_');

      if ($tmp === false) {
        self::fail('Failed to create temp file');
      }

      try {
        file_put_contents($tmp, $out);
        $cmd = 'php -l ' . escapeshellarg($tmp) . ' 2>&1';
        $output = shell_exec($cmd);
        $exit = 0;

        if (!str_contains((string)$output, 'No syntax errors detected')) {
          $exit = 1;
        }

        if ($exit !== 0) {
          $failed[$type->name] = "lint: {$output}\n--- source ---\n{$out}";
        }
      } finally {
        @unlink($tmp);
      }
    }

    self::assertSame(
      [],
      $failed,
      "Some rendered types failed php -l:\n" . implode("\n\n", array_map(
        static fn(string $name, string $msg): string => "  [{$name}]: " . substr($msg, 0, 600),
        array_keys($failed),
        array_values($failed),
      )),
    );
  }

  private function render(string $name): string
  {
    $type = self::$typesByName[$name] ?? null;

    if ($type === null) {
      self::fail("Type {$name} not present in schema");
    }

    return $this->renderer()->render($type);
  }

  private function renderer(): TypeRenderer
  {
    $r = self::$renderer;

    if ($r === null) {
      self::fail('Renderer not initialised');
    }

    return $r;
  }

  private static function schema(): LoadedSchema
  {
    $s = self::$schema;

    if ($s === null) {
      self::fail('Schema not loaded');
    }

    return $s;
  }
}
