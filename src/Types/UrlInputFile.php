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
    /** Optional fallback bot — used by Phase 6 streaming when read() is called without an explicit bot. */
    public readonly ?Bot $defaultBot = null,
  ) {
    parent::__construct(filename: $filename, chunkSize: $chunkSize);
  }

  public function read(Bot $bot): ReadableStream
  {
    // Full implementation lands in Phase 6 (multipart upload / webhook plumbing)
    // once $bot->session->streamContent is exercised end-to-end with retries.
    throw new RuntimeException('UrlInputFile::read is not yet implemented (Phase 6)');
  }
}
