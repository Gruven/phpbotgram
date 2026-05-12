<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Amp\ByteStream\ReadableStream;
use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\GetFile;
use Gruven\PhpBotGram\Methods\GetMe;
use Gruven\PhpBotGram\Types\Downloadable;
use Gruven\PhpBotGram\Types\File;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\Token;
use InvalidArgumentException;
use LogicException;
use Revolt\EventLoop\FiberLocal;
use RuntimeException;

trait BotShortcuts
{
  /** @var null|FiberLocal<?Bot> */
  private static ?FiberLocal $currentBotLocal = null;
  private ?User $cachedMe = null;
  private ?int $cachedId = null;

  public function getId(): int
  {
    return $this->cachedId ??= Token::extractBotId($this->token);
  }

  public function context(bool $autoClose = true): Closure
  {
    return function (Closure $body) use ($autoClose): mixed {
      try {
        return $body();
      } finally {
        if ($autoClose) {
          $this->session->close();
        }
      }
    };
  }

  /**
   * @param Closure():?Bot $init
   *
   * @return FiberLocal<?Bot>
   */
  private static function makeBotLocal(Closure $init): FiberLocal
  {
    return new FiberLocal($init);
  }

  /** @return FiberLocal<?Bot> */
  private static function botLocal(): FiberLocal
  {
    if (self::$currentBotLocal === null) {
      self::$currentBotLocal = self::makeBotLocal(static fn(): ?Bot => null);
    }

    return self::$currentBotLocal;
  }

  public static function current(): ?Bot
  {
    return self::botLocal()->get();
  }

  public static function setCurrent(?Bot $bot): void
  {
    self::botLocal()->set($bot);
  }

  /**
   * Test/teardown helper — clears the FiberLocal storage so the next test
   * starts with a clean current bot. Production code should not call this;
   * use setCurrent(null) instead, which keeps the FiberLocal slot but resets
   * the stored Bot for the current fiber.
   */
  public static function resetCurrentBot(): void
  {
    self::$currentBotLocal = null;
  }

  public function me(): User
  {
    if ($this->cachedMe !== null) {
      return $this->cachedMe;
    }

    /** @var User $me */
    $me = $this(new GetMe());
    $this->cachedMe = $me;

    return $me;
  }

  public function downloadFile(File|string $fileOrPath, mixed $destination = null, int $chunkSize = 65536): ?string
  {
    $path = $fileOrPath instanceof File
        ? ($fileOrPath->filePath ?? throw new LogicException('File has no filePath'))
        : $fileOrPath;
    $url = $this->session->api->fileUrl($this->token, $path);
    $stream = $this->session->streamContent($url, chunkSize: $chunkSize);

    return $this->consumeStream($stream, $destination);
  }

  public function download(Downloadable $object, mixed $destination = null, int $chunkSize = 65536): ?string
  {
    /** @var File $file */
    $file = $this(new GetFile(fileId: $object->fileId()));

    return $this->downloadFile($file, $destination, $chunkSize);
  }

  private function consumeStream(ReadableStream $stream, mixed $destination): ?string
  {
    if ($destination === null) {
      $buf = '';

      while (($chunk = $stream->read()) !== null) {
        $buf .= $chunk;
      }

      return $buf;
    }

    $handle = is_string($destination) ? fopen($destination, 'wb') : $destination;

    if (!is_resource($handle)) {
      throw new InvalidArgumentException('Destination must be a string path or writable resource');
    }

    while (($chunk = $stream->read()) !== null) {
      $expected = strlen($chunk);
      $written = fwrite($handle, $chunk);

      if ($written === false || $written !== $expected) {
        if (is_string($destination)) {
          fclose($handle);
        }

        throw new RuntimeException("Failed to write {$expected} bytes to destination (wrote: " . ($written === false ? 'false' : (string)$written) . ')');
      }
    }

    if (is_string($destination)) {
      fclose($handle);
    }

    return null;
  }
}
