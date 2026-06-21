<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;

/**
 * This object contains information about one answer option in a poll to be sent.
 *
 * Source: https://core.telegram.org/bots/api#inputpolloption
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class InputPollOption extends TelegramObject
{
  /**
   * @param null|list<MessageEntity> $textEntities
   */
  public function __construct(
    public readonly string $text,
    public readonly BotDefault|string|null $textParseMode = new BotDefault('parse_mode'),
    public readonly ?array $textEntities = null,
    public readonly ?InputPollOptionMediaInterface $media = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
