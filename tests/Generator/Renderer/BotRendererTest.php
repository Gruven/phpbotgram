<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator\Renderer;

use Gruven\PhpBotGram\Generator\DefaultsResolver;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\Renderer\BotRenderer;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeOverrideApplier;
use Gruven\PhpBotGram\Generator\TypeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\Renderer\BotRenderer
 */
final class BotRendererTest extends TestCase
{
  private static ?LoadedSchema $schema = null;

  private static ?BotRenderer $renderer = null;

  private static ?string $rendered = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 3) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    $loaded = new TypeOverrideApplier($loader->load())->apply();
    self::$schema = $loaded;

    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/tools/generator/templates'), [
      'autoescape' => false,
      'strict_variables' => true,
    ]);

    self::$renderer = new BotRenderer(
      twig: $twig,
      types: new TypeResolver($loaded),
      names: new NameMapper(),
      defaults: new DefaultsResolver($loaded),
    );
  }

  public function testHeaderAndNamespace(): void
  {
    self::assertStringContainsString(
      "<?php\n\ndeclare(strict_types=1);\n\nnamespace Gruven\\PhpBotGram;",
      $this->rendered(),
    );
    self::assertStringContainsString('@generated do not edit; regenerate via `make regenerate`', $this->rendered());
  }

  public function testGetMeWrapperReturnsUser(): void
  {
    $out = $this->rendered();

    // Import for return type.
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\User;', $out);
    // Import for the Method class.
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Methods\\GetMe;', $out);
    // Wrapper signature: zero-param method (besides timeout) returning User.
    self::assertMatchesRegularExpression(
      '/public function getMe\\(\\s*\\?int \\$timeout = null,\\s*\\): User/',
      $out,
    );
    self::assertMatchesRegularExpression(
      '/return \\$this\\(new GetMe\\(\\s*\\), \\$timeout\\);/',
      $out,
    );
  }

  public function testSendMessageWrapperHasAllParamsAndReturnsMessage(): void
  {
    $out = $this->rendered();

    self::assertStringContainsString('use Gruven\\PhpBotGram\\Methods\\SendMessage;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\Message;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Client\\BotDefault;', $out);

    // Required params chatId + text come first, before any param with default.
    self::assertMatchesRegularExpression(
      '/public function sendMessage\\(\\s*int\\|string \\$chatId,\\s*string \\$text,/',
      $out,
    );

    // BotDefault parse_mode (with the canonical name).
    self::assertStringContainsString(
      "null|BotDefault|string \$parseMode = new BotDefault('parse_mode'),",
      $out,
    );

    // BotDefault rename for link_preview_options -> link_preview.
    self::assertStringContainsString(
      "\$linkPreviewOptions = new BotDefault('link_preview'),",
      $out,
    );

    // The wrapper returns Message.
    self::assertMatchesRegularExpression(
      '/\\): Message \\{/',
      $out,
    );

    // The call forwards every parameter by name.
    self::assertStringContainsString('return $this(new SendMessage(', $out);
    self::assertStringContainsString('chatId: $chatId,', $out);
    self::assertStringContainsString('text: $text,', $out);
    self::assertStringContainsString('parseMode: $parseMode,', $out);
  }

  public function testGetUpdatesWrapperReturnsListUpdate(): void
  {
    $out = $this->rendered();

    self::assertStringContainsString('use Gruven\\PhpBotGram\\Methods\\GetUpdates;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\Update;', $out);

    // The PHP-level return type is `array`, the @return PHPDoc carries the
    // list<Update> shape.
    self::assertMatchesRegularExpression(
      '/@return list<Update>/',
      $out,
    );
    self::assertMatchesRegularExpression(
      '/public function getUpdates\\([^)]*\\): array \\{/s',
      $out,
    );
  }

  public function testLogOutWrapperReturnsBool(): void
  {
    $out = $this->rendered();

    self::assertStringContainsString('use Gruven\\PhpBotGram\\Methods\\LogOut;', $out);
    // logOut takes no wire params; the wrapper still emits the trailing
    // facade-side `?int $timeout = null` on its own line.
    self::assertMatchesRegularExpression(
      '/public function logOut\\(\\s*\\?int \\$timeout = null,\\s*\\): bool/',
      $out,
    );
  }

  /**
   * Cycle 2 review fix: the Bot facade's wrapper return-type lowering
   * mirrors `MethodRenderer::resolveReturnType`. The six methods whose
   * prose escaped the old matcher chain must now declare matching return
   * types on the wrapper signature so the Method class's `ReturnsType`
   * const and the Bot wrapper's declared return don't drift.
   *
   * @return list<array{0: string, 1: string, 2: null|string}>
   */
  public static function cycle2WrapperReturnTypes(): array
  {
    return [
      // [name, php-level return (after wrapper signature), nullable phpdoc-return]
      ['getStarTransactions', 'StarTransactions', null],
      ['sendMediaGroup', 'array', 'list<Message>'],
      ['copyMessages', 'array', 'list<MessageId>'],
      ['forwardMessages', 'array', 'list<MessageId>'],
      ['getGameHighScores', 'array', 'list<GameHighScore>'],
      ['getUserPersonalChatMessages', 'array', 'list<Message>'],
    ];
  }

  #[DataProvider('cycle2WrapperReturnTypes')]
  public function testCycle2WrapperReturnType(string $name, string $phpReturn, ?string $phpdocReturn): void
  {
    $out = $this->rendered();

    // Locate the wrapper function. The signature spans multiple lines so we
    // scan for `public function <name>(` followed by ANY content up to the
    // first `)` that immediately precedes `: <type> {`. The non-greedy `.*?`
    // with the `s` flag handles newlines; bounding on the explicit
    // `: <type> {` shape rules out accidental matches inside the body.
    $pattern = '/public function ' . preg_quote($name, '/') . '\\(.*?\\): ' . preg_quote($phpReturn, '/') . '\s*\{/s';
    self::assertMatchesRegularExpression($pattern, $out, "{$name}: wrapper return must be {$phpReturn}");

    if ($phpdocReturn !== null) {
      self::assertStringContainsString('@return ' . $phpdocReturn, $out, "{$name}: wrapper docblock must declare list-element PHPDoc");
    }
  }

  public function testInvokeAndConstructorPreserved(): void
  {
    $out = $this->rendered();

    // Constructor + Token::validate from Phase 1.
    self::assertStringContainsString('public function __construct(', $out);
    self::assertStringContainsString('public readonly string $token,', $out);
    self::assertStringContainsString('?BaseSession $session = null,', $out);
    self::assertStringContainsString('?DefaultBotProperties $defaultProperties = null,', $out);
    self::assertStringContainsString('Token::validate($token);', $out);

    // __invoke entrypoint.
    self::assertMatchesRegularExpression(
      '/public function __invoke\\(TelegramMethod \\$method, \\?int \\$timeout = null\\): mixed/',
      $out,
    );
    self::assertStringContainsString('return ($this->session)($this, $method, $timeout);', $out);

    // Trait use + contract implementation.
    self::assertStringContainsString('use BotShortcuts;', $out);
    self::assertStringContainsString('class Bot implements BotShortcutsContract', $out);
  }

  public function testRenderedOutputIsValidPhp(): void
  {
    $tmp = tempnam(sys_get_temp_dir(), 'phpbg_bot_');

    if ($tmp === false) {
      self::fail('Failed to create temp file');
    }

    try {
      file_put_contents($tmp, $this->rendered());
      $cmd = 'php -l ' . escapeshellarg($tmp) . ' 2>&1';
      $output = shell_exec($cmd);

      self::assertStringContainsString(
        'No syntax errors detected',
        (string)$output,
        "php -l output:\n{$output}\n--- source ---\n" . $this->rendered(),
      );
    } finally {
      @unlink($tmp);
    }
  }

  public function testEveryMethodInSchemaIsEmitted(): void
  {
    $out = $this->rendered();

    foreach (self::schema()->methods as $method) {
      $wrapperPattern = '/public function ' . preg_quote($method->name, '/') . '\\(/';
      self::assertMatchesRegularExpression(
        $wrapperPattern,
        $out,
        "Method wrapper '{$method->name}' is missing from the generated Bot facade.",
      );
    }
  }

  private function rendered(): string
  {
    if (self::$rendered === null) {
      $r = self::$renderer;

      if ($r === null) {
        self::fail('Renderer not initialised');
      }

      self::$rendered = $r->render(self::schema());
    }

    return self::$rendered;
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
