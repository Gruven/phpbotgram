<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\File;

/**
 * Use this method to get basic information about a file and prepare it for downloading. For the moment, bots can download files of up to 20MB in size. On success, a File object is returned. The file can then be downloaded via the link https://api.telegram.org/file/bot<token>/<file_path>, where <file_path> is taken from the response. It is guaranteed that the link will be valid for at least 1 hour. When the link expires, a new one can be requested by calling getFile again.
 * Note: This function may not preserve the original file name and MIME type. You should save the file's MIME type and name (if available) when the File object is received.
 *
 * Source: https://core.telegram.org/bots/api#getfile
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<File>
 */
final class GetFile extends TelegramMethod
{
  public const string ApiMethod = 'getFile';
  public const string ReturnsType = File::class;

  public function __construct(
    public readonly string $fileId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
