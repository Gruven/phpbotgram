<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator\Renderer;

use Gruven\PhpBotGram\Generator\EnumEntity;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\Renderer\EnumRenderer;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeOverrideApplier;
use PHPUnit\Framework\TestCase;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\Renderer\EnumRenderer
 */
final class EnumRendererTest extends TestCase
{
  private static ?LoadedSchema $schema = null;

  /** @var array<string, EnumEntity> */
  private static array $enumsByName = [];

  private static ?EnumRenderer $renderer = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 3) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    $loaded = new TypeOverrideApplier($loader->load())->apply();
    self::$schema = $loaded;

    foreach ($loaded->enums as $e) {
      self::$enumsByName[$e->name] = $e;
    }

    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/tools/generator/templates'), [
      'autoescape' => false,
      'strict_variables' => true,
    ]);

    self::$renderer = new EnumRenderer(
      twig: $twig,
      names: new NameMapper(),
      schema: $loaded,
    );
  }

  public function testRendersChatTypeStringBackedEnum(): void
  {
    $out = $this->render('ChatType');

    self::assertStringContainsString("<?php\n\ndeclare(strict_types=1);\n\nnamespace Gruven\\PhpBotGram\\Enums;", $out);
    self::assertStringContainsString('@generated do not edit; regenerate via `make regenerate`', $out);
    self::assertMatchesRegularExpression('/enum\s+ChatType:\s+string/', $out);

    // static SENDER first, then parsed: private/group/supergroup/channel.
    self::assertStringContainsString("case Sender = 'sender';", $out);
    self::assertStringContainsString("case Private = 'private';", $out);
    self::assertStringContainsString("case Group = 'group';", $out);
    self::assertStringContainsString("case Supergroup = 'supergroup';", $out);
    self::assertStringContainsString("case Channel = 'channel';", $out);
  }

  public function testRendersBotCommandScopeTypeMultiParseEnum(): void
  {
    $out = $this->render('BotCommandScopeType');

    self::assertMatchesRegularExpression('/enum\s+BotCommandScopeType:\s+string/', $out);
    // multi_parse regex `must be ([a-z_]+)` against each entity's `type` annotation.
    self::assertStringContainsString("case Default = 'default';", $out);
    self::assertStringContainsString("case AllPrivateChats = 'all_private_chats';", $out);
    self::assertStringContainsString("case AllGroupChats = 'all_group_chats';", $out);
    self::assertStringContainsString("case AllChatAdministrators = 'all_chat_administrators';", $out);
    self::assertStringContainsString("case Chat = 'chat';", $out);
    self::assertStringContainsString("case ChatAdministrators = 'chat_administrators';", $out);
    self::assertStringContainsString("case ChatMember = 'chat_member';", $out);
  }

  public function testRendersUpdateTypeExtractEnum(): void
  {
    $out = $this->render('UpdateType');

    self::assertMatchesRegularExpression('/enum\s+UpdateType:\s+string/', $out);
    // Extract maps Update's annotation names (minus excluded update_id) into
    // PascalCase cases keyed by the snake_case wire name.
    self::assertStringContainsString("case Message = 'message';", $out);
    self::assertStringContainsString("case EditedMessage = 'edited_message';", $out);
    self::assertStringContainsString("case ChannelPost = 'channel_post';", $out);
    self::assertStringContainsString("case PollAnswer = 'poll_answer';", $out);
    self::assertStringContainsString("case ChatMember = 'chat_member';", $out);
    // The excluded field must not appear.
    self::assertStringNotContainsString('case UpdateId', $out);
  }

  public function testRendersCurrencyEnumWithStaticOnlyCases(): void
  {
    $out = $this->render('Currency');

    self::assertMatchesRegularExpression('/enum\s+Currency:\s+string/', $out);
    // Per the task convention, case names PascalCase from `strtolower(KEY)`
    // — so the 3-letter ISO `AED` key lowers to `aed`, then `Aed`.
    self::assertStringContainsString("case Aed = 'AED';", $out);
    self::assertStringContainsString("case Usd = 'USD';", $out);
    self::assertStringContainsString("case Eur = 'EUR';", $out);
  }

  public function testRendersTopicIconColorAsIntBackedEnum(): void
  {
    $out = $this->render('TopicIconColor');

    self::assertMatchesRegularExpression('/enum\s+TopicIconColor:\s+int/', $out);
    // Hex literals preserved verbatim.
    self::assertStringContainsString('case Blue = 0x6FB9F0;', $out);
    self::assertStringContainsString('case Yellow = 0xFFD67E;', $out);
    self::assertStringContainsString('case Red = 0xFB6F5F;', $out);
  }

  public function testRendersDiceEmojiWithUnicodeValues(): void
  {
    $out = $this->render('DiceEmoji');

    self::assertMatchesRegularExpression('/enum\s+DiceEmoji:\s+string/', $out);
    // Emoji literals are kept verbatim as single-quoted values.
    self::assertStringContainsString("case Dice = '\u{1F3B2}';", $out);
    self::assertStringContainsString("case Dart = '\u{1F3AF}';", $out);
    self::assertStringContainsString("case SlotMachine = '\u{1F3B0}';", $out);
  }

  public function testRendersInputPaidMediaTypeFromRstDescription(): void
  {
    $out = $this->render('InputPaidMediaType');

    // `format: rst` requires consulting the rst_description for the `\*([a-z_]+)\*`
    // matcher to find the asterisk-wrapped wire literals.
    self::assertMatchesRegularExpression('/enum\s+InputPaidMediaType:\s+string/', $out);
    self::assertStringContainsString("case Photo = 'photo';", $out);
    self::assertStringContainsString("case Video = 'video';", $out);
  }

  /**
   * Sanity sweep: render every enum and verify each emits valid PHP.
   */
  public function testAllEnumsAreValidPhp(): void
  {
    $failed = [];

    foreach (self::schema()->enums as $enum) {
      try {
        $out = $this->renderer()->render($enum);
      } catch (Throwable $e) {
        $failed[$enum->name] = 'render: ' . $e->getMessage();

        continue;
      }

      $tmp = tempnam(sys_get_temp_dir(), 'phpbg_enum_');

      if ($tmp === false) {
        self::fail('Failed to create temp file');
      }

      try {
        file_put_contents($tmp, $out);
        $cmd = 'php -l ' . escapeshellarg($tmp) . ' 2>&1';
        $output = shell_exec($cmd);

        if (!str_contains((string)$output, 'No syntax errors detected')) {
          $failed[$enum->name] = "lint: {$output}\n--- source ---\n{$out}";
        }
      } finally {
        @unlink($tmp);
      }
    }

    self::assertSame(
      [],
      $failed,
      "Some rendered enums failed php -l:\n" . implode("\n\n", array_map(
        static fn(string $name, string $msg): string => "  [{$name}]: " . substr($msg, 0, 800),
        array_keys($failed),
        array_values($failed),
      )),
    );
  }

  private function render(string $name): string
  {
    $enum = self::$enumsByName[$name] ?? null;

    if ($enum === null) {
      self::fail("Enum {$name} not present in schema");
    }

    return $this->renderer()->render($enum);
  }

  private function renderer(): EnumRenderer
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
