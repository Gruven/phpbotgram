<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

final class File extends TelegramObject implements Downloadable
{
  public function __construct(
    public readonly string $fileId,
    public readonly ?string $filePath = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }

  public function fileId(): string
  {
    return $this->fileId;
  }
}
