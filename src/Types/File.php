<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a file ready to be downloaded. The file can be downloaded via the link https://api.telegram.org/file/bot<token>/<file_path>. It is guaranteed that the link will be valid for at least 1 hour. When the link expires, a new one can be requested by calling getFile.
 * The maximum file size to download is 20 MB
 *
 * Source: https://core.telegram.org/bots/api#file
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class File extends TelegramObject
{
  public function __construct(
    public readonly string $fileId,
    public readonly string $fileUniqueId,
    public readonly ?int $fileSize = null,
    public readonly ?string $filePath = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
