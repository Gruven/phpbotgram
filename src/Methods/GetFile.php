<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\File;

/**
 * @extends TelegramMethod<File>
 */
final class GetFile extends TelegramMethod
{
  public const string ApiMethod = 'getFile';
  public const string ReturnsType = File::class;

  public function __construct(public readonly string $fileId, ?Bot $bot = null)
  {
    parent::__construct($bot);
  }
}
