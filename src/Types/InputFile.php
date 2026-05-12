<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableStream;
use Gruven\PhpBotGram\Bot;

abstract class InputFile extends TelegramObject
{
    public const int DEFAULT_CHUNK_SIZE = 65536;

    public function __construct(
        public readonly ?string $filename = null,
        public readonly int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?Bot $bot = null,
    ) {
        parent::__construct($bot);
    }

    /** Returns a Fiber-aware readable stream of file bytes. */
    abstract public function read(Bot $bot): ReadableStream;
}
