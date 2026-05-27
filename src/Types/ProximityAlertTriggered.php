<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents the content of a service message, sent whenever a user in the chat triggers a proximity alert set by another user.
 *
 * Source: https://core.telegram.org/bots/api#proximityalerttriggered
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ProximityAlertTriggered extends TelegramObject
{
  public function __construct(
    public readonly User $traveler,
    public readonly User $watcher,
    public readonly int $distance,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
