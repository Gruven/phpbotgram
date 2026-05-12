<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;
use RuntimeException;

final class UrlInputFile extends InputFile
{
  public function __construct(
    public readonly string $url,
    /** @var null|array<string, string> */
    public readonly ?array $headers = null,
    ?string $filename = null,
    int $chunkSize = self::DEFAULT_CHUNK_SIZE,
    public readonly int $timeout = 30,
    ?Bot $bot = null,
  ) {
    parent::__construct(filename: $filename, chunkSize: $chunkSize, bot: $bot);
  }

  public function read(Bot $bot): ReadableStream
  {
    // Full implementation lands in Task 1.5 once AmphpSession exposes streamContent.
    throw new RuntimeException('UrlInputFile::read is not yet implemented (Task 1.5)');
  }
}
