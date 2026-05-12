<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;

/**
 * Standalone abstract — InputFile is intentionally NOT a TelegramObject. Upstream
 * aiogram declares `class InputFile(ABC)` (aiogram/types/input_file.py); attaching
 * it to the TelegramObject tree would make it eligible for Serializer dump/load,
 * which is wrong: InputFile values are detached by `BaseSession::prepareValue`
 * into the multipart `$files` channel and never go through JSON.
 */
abstract class InputFile
{
  public const int DEFAULT_CHUNK_SIZE = 65536;

  public function __construct(
    public readonly ?string $filename = null,
    public readonly int $chunkSize = self::DEFAULT_CHUNK_SIZE,
  ) {}

  /** Returns a Fiber-aware readable stream of file bytes. */
  abstract public function read(Bot $bot): ReadableStream;
}
