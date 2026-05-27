<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents one special entity in a text message. For example, hashtags, usernames, URLs, etc.
 *
 * Source: https://core.telegram.org/bots/api#messageentity
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MessageEntity extends MutableTelegramObject
{
  public function __construct(
    public readonly string $type,
    public readonly int $offset,
    public readonly int $length,
    public readonly ?string $url = null,
    public readonly ?User $user = null,
    public readonly ?string $language = null,
    public readonly ?string $customEmojiId = null,
    public readonly ?int $unixTime = null,
    public readonly ?string $dateTimeFormat = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
