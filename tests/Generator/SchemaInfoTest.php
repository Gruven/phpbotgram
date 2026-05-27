<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use DirectoryIterator;
use FilesystemIterator;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\Pipeline;
use Gruven\PhpBotGram\Generator\SchemaInfo;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\SchemaInfo
 */
final class SchemaInfoTest extends TestCase
{
  private static string $repoRoot = '';
  private static string $schemaDir = '';
  private static ?LoadedSchema $loaded = null;

  /** Lazily-built cache of the freshly-emitted output tree so the 6 emit-count assertions amortise one pipeline run. */
  private static ?string $emittedOut = null;

  public static function setUpBeforeClass(): void
  {
    self::$repoRoot = \dirname(__DIR__, 2);
    self::$schemaDir = self::$repoRoot . '/.butcher';
    self::$loaded = new SchemaLoader(self::$schemaDir)->load();
  }

  public static function tearDownAfterClass(): void
  {
    if (is_string(self::$emittedOut) && is_dir(self::$emittedOut)) {
      self::removeRecursively(self::$emittedOut);
      self::$emittedOut = null;
    }
  }

  public function testApiVersionMatchesLoadedSchema(): void
  {
    self::assertSame(SchemaInfo::API_VERSION, $this->loaded()->apiVersion);
  }

  public function testApiReleaseDateMatchesLoadedSchema(): void
  {
    self::assertSame(SchemaInfo::API_RELEASE_DATE, $this->loaded()->releaseDate);
  }

  public function testTypeEntityCountMatchesLoadedSchema(): void
  {
    self::assertCount(SchemaInfo::TYPE_ENTITIES, $this->loaded()->types);
  }

  public function testMethodEntityCountMatchesLoadedSchema(): void
  {
    self::assertCount(SchemaInfo::METHOD_ENTITIES, $this->loaded()->methods);
  }

  public function testEnumEntityCountMatchesLoadedSchema(): void
  {
    self::assertCount(SchemaInfo::ENUM_ENTITIES, $this->loaded()->enums);
  }

  public function testEmittedTypeFileCountMatchesPin(): void
  {
    self::assertCount(SchemaInfo::EMITTED_TYPE_FILES, $this->phpFilesIn($this->emittedTree() . '/Types'));
  }

  public function testEmittedMethodFileCountMatchesPin(): void
  {
    self::assertCount(SchemaInfo::EMITTED_METHOD_FILES, $this->phpFilesIn($this->emittedTree() . '/Methods'));
  }

  public function testEmittedEnumFileCountMatchesPin(): void
  {
    self::assertCount(SchemaInfo::EMITTED_ENUM_FILES, $this->phpFilesIn($this->emittedTree() . '/Enums'));
  }

  public function testBotFacadeIsEmittedAtTreeRoot(): void
  {
    self::assertFileExists($this->emittedTree() . '/Bot.php');
  }

  private function loaded(): LoadedSchema
  {
    if (self::$loaded === null) {
      self::fail('LoadedSchema was not initialised in setUpBeforeClass()');
    }

    return self::$loaded;
  }

  /**
   * Lazily run the full pipeline into a class-scoped tmp dir so the six
   * emit-count test cases share a single (slow) pipeline invocation.
   * Cleaned up in `tearDownAfterClass()`.
   */
  private function emittedTree(): string
  {
    if (self::$emittedOut !== null) {
      return self::$emittedOut;
    }

    $dir = sys_get_temp_dir() . '/phpbg-info-' . bin2hex(random_bytes(8));
    mkdir($dir, 0o755, true);

    new Pipeline(
      schemaDir: self::$schemaDir,
      outDir: $dir,
      fixerBin: self::$repoRoot . '/vendor/bin/php-cs-fixer',
      repoRoot: self::$repoRoot,
    )->run();

    self::$emittedOut = $dir;

    return $dir;
  }

  /**
   * @return list<string>
   */
  private function phpFilesIn(string $dir): array
  {
    if (!is_dir($dir)) {
      return [];
    }

    /** @var list<string> $out */
    $out = [];

    foreach (new DirectoryIterator($dir) as $entry) {
      if ($entry->isFile() && str_ends_with($entry->getFilename(), '.php')) {
        $out[] = $entry->getPathname();
      }
    }

    sort($out, SORT_STRING);

    return $out;
  }

  private static function removeRecursively(string $path): void
  {
    if (!is_dir($path)) {
      if (is_file($path)) {
        @unlink($path);
      }

      return;
    }

    $iter = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iter as $entry) {
      if (!$entry instanceof SplFileInfo) {
        continue;
      }

      if ($entry->isDir()) {
        @rmdir($entry->getPathname());
      } else {
        @unlink($entry->getPathname());
      }
    }

    @rmdir($path);
  }
}
