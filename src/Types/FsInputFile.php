<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Amp\ByteStream\ReadableStream;
use Amp\File;
use Gruven\PhpBotGram\Bot;

final class FsInputFile extends InputFile
{
    public function __construct(
        public readonly string $path,
        ?string $filename = null,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        ?Bot $bot = null,
    ) {
        parent::__construct(filename: $filename ?? basename($path), chunkSize: $chunkSize, bot: $bot);
    }

    public function read(Bot $bot): ReadableStream
    {
        return File\openFile($this->path, 'r');
    }
}
