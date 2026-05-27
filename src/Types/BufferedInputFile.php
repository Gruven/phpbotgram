<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;
use RuntimeException;

final class BufferedInputFile extends InputFile
{
  public function __construct(
    public readonly string $data,
    string $filename,
    int $chunkSize = self::DEFAULT_CHUNK_SIZE,
  ) {
    parent::__construct(filename: $filename, chunkSize: $chunkSize);
  }

  public static function fromFile(string $path, ?string $filename = null, int $chunkSize = self::DEFAULT_CHUNK_SIZE): self
  {
    $data = file_get_contents($path);

    if ($data === false) {
      throw new RuntimeException("Failed to read file: {$path}");
    }
    $filename ??= basename($path);

    return new self(data: $data, filename: $filename, chunkSize: $chunkSize);
  }

  public function read(Bot $bot): ReadableStream
  {
    return new ReadableBuffer($this->data);
  }
}
