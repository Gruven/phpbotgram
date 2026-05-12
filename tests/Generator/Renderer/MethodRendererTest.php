<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator\Renderer;

use Gruven\PhpBotGram\Generator\DefaultsResolver;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\NameMapper;
use Gruven\PhpBotGram\Generator\Renderer\MethodRenderer;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeOverrideApplier;
use Gruven\PhpBotGram\Generator\TypeResolver;
use Gruven\PhpBotGram\Generator\UnionDetector;
use Gruven\PhpBotGram\Generator\UnionPlan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\Renderer\MethodRenderer
 */
final class MethodRendererTest extends TestCase
{
  private static ?LoadedSchema $schema = null;

  /** @var array<string, MethodEntity> */
  private static array $methodsByName = [];

  private static ?MethodRenderer $renderer = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 3) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    $loaded = new TypeOverrideApplier($loader->load())->apply();
    self::$schema = $loaded;

    foreach ($loaded->methods as $m) {
      self::$methodsByName[$m->name] = $m;
    }

    $names = new NameMapper();
    $types = new TypeResolver($loaded);
    $defaults = new DefaultsResolver($loaded);

    /** @var array<string, UnionPlan> $unionsByParent */
    $unionsByParent = [];

    foreach (new UnionDetector($loaded)->plans() as $u) {
      $unionsByParent[$u->parentName] = $u;
    }

    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 3) . '/tools/generator/templates'), [
      'autoescape' => false,
      'strict_variables' => true,
    ]);

    self::$renderer = new MethodRenderer(
      twig: $twig,
      types: $types,
      names: $names,
      defaults: $defaults,
      unionsByParent: $unionsByParent,
      schema: $loaded,
    );
  }

  public function testGetMeRendersAsZeroParamMethod(): void
  {
    $out = $this->render('getMe');

    self::assertStringContainsString("<?php\n\ndeclare(strict_types=1);\n\nnamespace Gruven\\PhpBotGram\\Methods;", $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Bot;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\User;', $out);
    self::assertStringContainsString('@extends TelegramMethod<User>', $out);
    self::assertStringContainsString('@generated', $out);
    self::assertStringContainsString('Source: https://core.telegram.org/bots/api#getme', $out);
    self::assertMatchesRegularExpression('/final\s+class\s+GetMe\s+extends\s+TelegramMethod/', $out);
    self::assertStringContainsString("public const string ApiMethod = 'getMe';", $out);
    self::assertStringContainsString('public const string ReturnsType = User::class;', $out);
    // Constructor body and trailing bot parameter.
    self::assertStringContainsString('?Bot $bot = null', $out);
    self::assertStringContainsString('parent::__construct($bot);', $out);
    // Self-namespace classes (TelegramMethod) must NOT be imported.
    self::assertStringNotContainsString('use Gruven\\PhpBotGram\\Methods\\TelegramMethod;', $out);
  }

  public function testSendMessageRendersFullSurface(): void
  {
    $out = $this->render('sendMessage');

    self::assertMatchesRegularExpression('/final\s+class\s+SendMessage\s+extends\s+TelegramMethod/', $out);
    self::assertStringContainsString("public const string ApiMethod = 'sendMessage';", $out);
    self::assertStringContainsString('public const string ReturnsType = Message::class;', $out);
    self::assertStringContainsString('@extends TelegramMethod<Message>', $out);

    // Required param: chatId (Integer or String) — no default, schema order preserved
    // relative to other required params (text).
    self::assertStringContainsString('public readonly int|string $chatId,', $out);
    self::assertStringContainsString('public readonly string $text,', $out);

    // Optional with BotDefault (parse_mode).
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Client\\BotDefault;', $out);
    self::assertStringContainsString(
      "public readonly null|BotDefault|string \$parseMode = new BotDefault('parse_mode'),",
      $out,
    );

    // BotDefault rename: disable_web_page_preview -> link_preview_is_disabled.
    // (This annotation is filtered out as deprecated upstream — not present.)

    // BotDefault rename: link_preview_options -> link_preview.
    self::assertStringContainsString(
      "public readonly null|BotDefault|LinkPreviewOptions \$linkPreviewOptions = new BotDefault('link_preview'),",
      $out,
    );

    // Optional without BotDefault: entities (Array of MessageEntity) — defaults to null,
    // PHPDoc carries the list<MessageEntity> shape.
    self::assertStringContainsString('public readonly ?array $entities = null,', $out);
    self::assertStringContainsString('list<MessageEntity>', $out);

    // Reply markup union: optional without BotDefault, multi-class union widens to null.
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\InlineKeyboardMarkup;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\ReplyKeyboardMarkup;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\ReplyKeyboardRemove;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\ForceReply;', $out);
    self::assertMatchesRegularExpression(
      '/null\|ForceReply\|InlineKeyboardMarkup\|ReplyKeyboardMarkup\|ReplyKeyboardRemove \$replyMarkup = null/',
      $out,
    );

    self::assertStringContainsString('?Bot $bot = null', $out);
    self::assertStringContainsString('parent::__construct($bot);', $out);
  }

  public function testGetUpdatesReturnsListSentinel(): void
  {
    $out = $this->render('getUpdates');

    self::assertStringContainsString("public const string ReturnsType = 'list:Update';", $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\Update;', $out);
    // Class-level @extends carries the element type for PHPStan.
    self::assertStringContainsString('@extends TelegramMethod<list<Update>>', $out);
  }

  public function testGetChatMemberCountReturnsIntScalar(): void
  {
    $out = $this->render('getChatMemberCount');

    self::assertStringContainsString("public const string ReturnsType = 'int';", $out);
    self::assertStringContainsString('@extends TelegramMethod<int>', $out);
  }

  public function testLogOutReturnsBoolScalar(): void
  {
    $out = $this->render('logOut');

    self::assertStringContainsString("public const string ReturnsType = 'bool';", $out);
    self::assertStringContainsString('@extends TelegramMethod<bool>', $out);
  }

  public function testGetChatMemberReturnsUnionParent(): void
  {
    $out = $this->render('getChatMember');

    // parsedReturning is a union of all ChatMember subtypes — collapse to the
    // discriminator-tagged parent so the runtime can route through
    // ChatMemberUnion::resolve.
    self::assertStringContainsString('public const string ReturnsType = ChatMember::class;', $out);
    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\ChatMember;', $out);
    self::assertStringContainsString('@extends TelegramMethod<ChatMember>', $out);
  }

  /**
   * Cycle 2 review fix: the prose return-type matcher silently lost six
   * methods whose "On success, an array of <X> is returned." / "Returns a
   * <X> object." phrasings escaped the old regex chain and fell back to a
   * `bool` default. Each of these methods now has a concrete `ReturnsType`
   * const tied to the schema entity their prose describes; emitting `bool`
   * for any of them would corrupt the runtime wire-contract.
   *
   * The data-provider lists the six methods plus the expected
   * (returnsExpr, extendsGeneric) pair the renderer must surface in the
   * emitted class source.
   *
   * @return list<array{0: string, 1: string, 2: string}>
   */
  public static function cycle2ReturnTypeMethods(): array
  {
    return [
      ['getStarTransactions', 'public const string ReturnsType = StarTransactions::class;', '@extends TelegramMethod<StarTransactions>'],
      ['sendMediaGroup', "public const string ReturnsType = 'list:Message';", '@extends TelegramMethod<list<Message>>'],
      ['copyMessages', "public const string ReturnsType = 'list:MessageId';", '@extends TelegramMethod<list<MessageId>>'],
      ['forwardMessages', "public const string ReturnsType = 'list:MessageId';", '@extends TelegramMethod<list<MessageId>>'],
      ['getGameHighScores', "public const string ReturnsType = 'list:GameHighScore';", '@extends TelegramMethod<list<GameHighScore>>'],
      ['getUserPersonalChatMessages', "public const string ReturnsType = 'list:Message';", '@extends TelegramMethod<list<Message>>'],
    ];
  }

  #[DataProvider('cycle2ReturnTypeMethods')]
  public function testCycle2ReturnTypeFix(string $name, string $returnsType, string $extendsGeneric): void
  {
    $out = $this->render($name);

    self::assertStringContainsString($returnsType, $out, "{$name}: ReturnsType const must surface the correct schema type");
    self::assertStringContainsString($extendsGeneric, $out, "{$name}: @extends generic must match the ReturnsType");
  }

  public function testSendPhotoUsesInputFileStringUnion(): void
  {
    $out = $this->render('sendPhoto');

    self::assertStringContainsString('use Gruven\\PhpBotGram\\Types\\InputFile;', $out);
    // Required: photo type is `InputFile or String` -> `InputFile|string`.
    self::assertMatchesRegularExpression(
      '/public readonly InputFile\|string \$photo,/',
      $out,
    );
  }

  public function testRequiredParamsComeBeforeOptionalParams(): void
  {
    $out = $this->render('sendMessage');

    // chatId and text are required; they must appear before any param that
    // carries a `=` clause. business_connection_id is the first wire param in
    // schema order but it's optional — it must NOT precede chatId/text.
    $chatIdPos = strpos($out, '$chatId');
    $textPos = strpos($out, '$text');
    $businessPos = strpos($out, '$businessConnectionId');

    self::assertNotFalse($chatIdPos);
    self::assertNotFalse($textPos);
    self::assertNotFalse($businessPos);

    self::assertLessThan($businessPos, $chatIdPos, 'chatId must precede businessConnectionId');
    self::assertLessThan($businessPos, $textPos, 'text must precede businessConnectionId');
  }

  public function testNoSelfNamespaceImports(): void
  {
    // The generated class lives in Methods\; it must never import another
    // class from Methods\ (specifically TelegramMethod, the parent).
    foreach (['getMe', 'sendMessage', 'getChatMember', 'sendPhoto'] as $name) {
      $out = $this->render($name);
      self::assertStringNotContainsString(
        'use Gruven\\PhpBotGram\\Methods\\',
        $out,
        "{$name} must not import classes from its own namespace",
      );
    }
  }

  public function testGeneratedHeaderIsPresent(): void
  {
    $out = $this->render('sendMessage');

    self::assertStringContainsString('@generated do not edit; regenerate via `make regenerate`', $out);
  }

  /**
   * Sanity sweep: render every method in the loaded schema and assert each
   * emit is valid PHP via `php -l`.
   */
  public function testAllMethodsAreValidPhp(): void
  {
    $failed = [];

    foreach (self::schema()->methods as $method) {
      try {
        $out = $this->renderer()->render($method);
      } catch (Throwable $e) {
        $failed[$method->name] = 'render: ' . $e->getMessage();

        continue;
      }

      $tmp = tempnam(sys_get_temp_dir(), 'phpbg_method_');

      if ($tmp === false) {
        self::fail('Failed to create temp file');
      }

      try {
        file_put_contents($tmp, $out);
        $cmd = 'php -l ' . escapeshellarg($tmp) . ' 2>&1';
        $output = shell_exec($cmd);

        if (!str_contains((string)$output, 'No syntax errors detected')) {
          $failed[$method->name] = "lint: {$output}\n--- source ---\n{$out}";
        }
      } finally {
        @unlink($tmp);
      }
    }

    self::assertSame(
      [],
      $failed,
      "Some rendered methods failed php -l:\n" . implode("\n\n", array_map(
        static fn(string $name, string $msg): string => "  [{$name}]: " . substr($msg, 0, 800),
        array_keys($failed),
        array_values($failed),
      )),
    );
  }

  private function render(string $name): string
  {
    $method = self::$methodsByName[$name] ?? null;

    if ($method === null) {
      self::fail("Method {$name} not present in schema");
    }

    return $this->renderer()->render($method);
  }

  private function renderer(): MethodRenderer
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
