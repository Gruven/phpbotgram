<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputStoryContent;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\Story;
use Gruven\PhpBotGram\Types\StoryArea;

/**
 * Posts a story on behalf of a managed business account. Requires the can_manage_stories business bot right. Returns Story on success.
 *
 * Source: https://core.telegram.org/bots/api#poststory
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Story>
 */
final class PostStory extends TelegramMethod
{
  public const string ApiMethod = 'postStory';
  public const string ReturnsType = Story::class;

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly InputStoryContent $content,
    public readonly int $activePeriod,
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    /** @var list<MessageEntity> */
    public readonly ?array $captionEntities = null,
    /** @var list<StoryArea> */
    public readonly ?array $areas = null,
    public readonly ?bool $postToChatPage = null,
    public readonly ?bool $protectContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
