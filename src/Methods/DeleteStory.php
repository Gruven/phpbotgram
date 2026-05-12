<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Deletes a story previously posted by the bot on behalf of a managed business account. Requires the can_manage_stories business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#deletestory
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class DeleteStory extends TelegramMethod
{
  public const string ApiMethod = 'deleteStory';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly int $storyId,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
