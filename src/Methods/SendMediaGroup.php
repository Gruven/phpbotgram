<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\InputMediaAudio;
use Gruven\PhpBotGram\Types\InputMediaDocument;
use Gruven\PhpBotGram\Types\InputMediaLivePhoto;
use Gruven\PhpBotGram\Types\InputMediaPhoto;
use Gruven\PhpBotGram\Types\InputMediaVideo;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\ReplyParameters;

/**
 * Use this method to send a group of photos, live photos, videos, documents or audios as an album. Documents and audio files can be only grouped in an album with messages of the same type. On success, an array of Message objects that were sent is returned.
 *
 * Source: https://core.telegram.org/bots/api#sendmediagroup
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<Message>>
 */
final class SendMediaGroup extends TelegramMethod
{
  public const string ApiMethod = 'sendMediaGroup';
  public const string ReturnsType = 'list:Message';

  public function __construct(
    public readonly int|string $chatId,
    /** @var list<InputMediaAudio|InputMediaDocument|InputMediaLivePhoto|InputMediaPhoto|InputMediaVideo> */
    public readonly array $media,
    public readonly ?string $businessConnectionId = null,
    public readonly ?int $messageThreadId = null,
    public readonly ?int $directMessagesTopicId = null,
    public readonly ?bool $disableNotification = null,
    public readonly null|bool|BotDefault $protectContent = new BotDefault('protect_content'),
    public readonly ?bool $allowPaidBroadcast = null,
    public readonly ?string $messageEffectId = null,
    public readonly ?ReplyParameters $replyParameters = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
