<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * Represents a result of an inline query that was chosen by the user and sent to their chat partner.
 * Note: It is necessary to enable inline feedback via @BotFather in order to receive these objects in updates.
 *
 * Source: https://core.telegram.org/bots/api#choseninlineresult
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChosenInlineResult extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  public function __construct(
    public readonly string $resultId,
    public readonly User $fromUser,
    public readonly string $query,
    public readonly ?Location $location = null,
    public readonly ?string $inlineMessageId = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
