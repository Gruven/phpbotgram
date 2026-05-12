<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;
use RuntimeException;

final class FsInputFile extends InputFile
{
  public function __construct(
    public readonly string $path,
    ?string $filename = null,
    int $chunkSize = self::DEFAULT_CHUNK_SIZE,
  ) {
    parent::__construct(filename: $filename ?? basename($path), chunkSize: $chunkSize);
  }

  /**
   * Reads the file fully into memory then wraps it in a ReadableBuffer.
   *
   * The previous implementation used Amp\File\openFile() which spawns an
   * amphp/parallel worker process. The worker's destructor (during PHP
   * shutdown) tries to reference a callback on an EventLoop driver that
   * RunAsyncTrait::resetEventLoop() has already replaced, producing a
   * fatal "Invalid callback identifier" and exit code 255 in CI.
   *
   * For Phase 1 the typical Telegram file size (photos/voice/docs under
   * ~50 MB) fits comfortably in memory; the buffered path is simpler and
   * has no worker-cleanup race. If truly streaming uploads matter later,
   * Phase 5+ can swap in a Fiber-friendly file driver that doesn't go
   * through amphp/parallel.
   */
  public function read(Bot $bot): ReadableStream
  {
    $data = file_get_contents($this->path);

    if ($data === false) {
      throw new RuntimeException("Failed to read file: {$this->path}");
    }

    return new ReadableBuffer($data);
  }
}
