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
      typesByName: self::$typesByName,
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

  /**
   * Cycle 1 review fix: TypeRenderer must emit constructor parameters in the
   * canonical order (required-no-default → required-with-default → optional-
   * with-default) so cs-fixer's `no_unreachable_default_argument_value` rule
   * does not strip `= null` defaults from optional params that would otherwise
   * precede required ones in raw schema order.
   *
   * Concrete reproducer: in the raw `Message` schema, `message_thread_id`
   * (optional) appears before `date` (required). Naively emitting the
   * constructor in schema order yields
   *
   *     public readonly ?int $messageThreadId = null,    // optional
   *     ...
   *     public readonly DateTime $date,                  // required
   *
   * which cs-fixer rewrites to drop every `= null` clause before `$date`,
   * forcing callers to pass `null` positionally for each preceding optional.
   * The renderer must reorder so all required-no-default params come first.
   */
  public function testMessageConstructorReordersRequiredBeforeOptional(): void
  {
    $out = $this->render('Message');

    // Locate the constructor signature so we can scan it linearly.
    $start = strpos($out, 'public function __construct(');
    self::assertNotFalse($start, 'Constructor not found in Message render');

    $bodyOpen = strpos($out, ') {', $start);
    self::assertNotFalse($bodyOpen, 'Constructor signature has no closing `) {`');

    $sig = substr($out, $start, $bodyOpen - $start);

    // Extract per-param lines (skip the empty signature-open line).
    /** @var list<array{name: string, hasDefault: bool, required: bool}> $params */
    $params = [];

    foreach (preg_split("/\r?\n/", $sig) ?: [] as $line) {
      $line = trim($line);

      if ($line === '' || $line === 'public function __construct(') {
        continue;
      }

      // Match: [public readonly ]<type> $name[ = <default>],
      if (preg_match('/\$([A-Za-z0-9_]+)(\s*=\s*(.+))?,?$/', $line, $m) !== 1) {
        continue;
      }

      $name = $m[1];

      if ($name === 'bot') {
        continue; // Trailing `?Bot $bot = null` is always last; ignore.
      }

      $hasDefault = isset($m[2]) && $m[2] !== '';
      $params[] = [
        'name' => $name,
        'hasDefault' => $hasDefault,
      ];
    }

    self::assertNotEmpty($params, 'Failed to extract any constructor parameters');

    // Sanity: every required (no-default) param must precede every param with a default.
    $seenWithDefault = false;

    foreach ($params as $p) {
      if (!$p['hasDefault']) {
        self::assertFalse(
          $seenWithDefault,
          "Constructor param order violation: required-no-default param \${$p['name']} appears after a param with a default. Order: " . implode(
            ', ',
            array_map(static fn(array $q): string => '$' . $q['name'] . ($q['hasDefault'] ? '=' : ''), $params),
          ),
        );

        continue;
      }

      $seenWithDefault = true;
    }

    // Concrete waypoint asserts — these would all fail under the unfixed
    // renderer because `messageThreadId` (optional) currently precedes
    // `messageId`/`date`/`chat` (required).
    $names = array_column($params, 'name');
    $messageIdIdx = array_search('messageId', $names, true);
    $dateIdx = array_search('date', $names, true);
    $chatIdx = array_search('chat', $names, true);
    $threadIdx = array_search('messageThreadId', $names, true);

    self::assertNotFalse($messageIdIdx, '$messageId missing');
    self::assertNotFalse($dateIdx, '$date missing');
    self::assertNotFalse($chatIdx, '$chat missing');
    self::assertNotFalse($threadIdx, '$messageThreadId missing');

    self::assertLessThan($threadIdx, $messageIdIdx, '$messageId must precede $messageThreadId');
    self::assertLessThan($threadIdx, $dateIdx, '$date must precede $messageThreadId');
    self::assertLessThan($threadIdx, $chatIdx, '$chat must precede $messageThreadId');
  }

  /**
   * Cycle 1 review fix: union children with a discriminator carry a
   * `required-with-default` parameter (`public readonly string $type =
   * 'solid'`). Such params must sort between required-no-default and
   * optional-with-default — they are required by schema (no `null` accepted)
   * but the default exists to pin the literal wire value. Mirrors
   * `MethodRenderer::buildParameters` ordering.
   */
  public function testBackgroundFillSolidConstructorOrdersDiscriminatorBeforeOptional(): void
  {
    $out = $this->render('BackgroundFillSolid');

    // The discriminator default must still be present (the regression was
    // cs-fixer stripping it because an optional preceded it).
    self::assertMatchesRegularExpression(
      "/public readonly string \\\$type\\s*=\\s*'solid'/",
      $out,
    );

    // And the `color` required-no-default param must precede the
    // `$type = 'solid'` default-bearing one.
    $sigStart = strpos($out, 'public function __construct(');
    self::assertNotFalse($sigStart);
    $sigEnd = strpos($out, ') {', $sigStart);
    self::assertNotFalse($sigEnd);
    $sig = substr($out, $sigStart, $sigEnd - $sigStart);

    $colorPos = strpos($sig, '$color');
    $typePos = strpos($sig, '$type');

    self::assertNotFalse($colorPos, '$color missing from BackgroundFillSolid ctor');
    self::assertNotFalse($typePos, '$type missing from BackgroundFillSolid ctor');
    self::assertLessThan($typePos, $colorPos, '$color (required-no-default) must precede $type (required-with-default)');
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
      typesByName: self::$typesByName,
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
