<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a service message about a user allowing a bot to write messages after adding it to the attachment menu, launching a Web App from a link, or accepting an explicit request from a Web App sent by the method requestWriteAccess.
 *
 * Source: https://core.telegram.org/bots/api#writeaccessallowed
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class WriteAccessAllowed extends TelegramObject
{
  public function __construct(
    public readonly ?bool $fromRequest = null,
    public readonly ?string $webAppName = null,
    public readonly ?bool $fromAttachmentMenu = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
