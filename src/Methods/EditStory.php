<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputStoryContent;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Types\Story;
use Gruven\PhpBotGram\Types\StoryArea;

/**
 * Edits a story previously posted by the bot on behalf of a managed business account. Requires the can_manage_stories business bot right. Returns Story on success.
 *
 * Source: https://core.telegram.org/bots/api#editstory
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Story>
 */
final class EditStory extends TelegramMethod
{
  public const string ApiMethod = 'editStory';
  public const string ReturnsType = Story::class;

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly int $storyId,
    public readonly InputStoryContent $content,
    public readonly ?string $caption = null,
    public readonly ?string $parseMode = null,
    /** @var list<MessageEntity> */
    public readonly ?array $captionEntities = null,
    /** @var list<StoryArea> */
    public readonly ?array $areas = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
