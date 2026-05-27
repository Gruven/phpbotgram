<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Story;

/**
 * Reposts a story on behalf of a business account from another business account. Both business accounts must be managed by the same bot, and the story on the source account must have been posted (or reposted) by the bot. Requires the can_manage_stories business bot right for both business accounts. Returns Story on success.
 *
 * Source: https://core.telegram.org/bots/api#repoststory
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<Story>
 */
final class RepostStory extends TelegramMethod
{
  public const string ApiMethod = 'repostStory';
  public const string ReturnsType = Story::class;

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly int $fromChatId,
    public readonly int $fromStoryId,
    public readonly int $activePeriod,
    public readonly ?bool $postToChatPage = null,
    public readonly ?bool $protectContent = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
