<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Represents an invite link for a chat.
 *
 * Source: https://core.telegram.org/bots/api#chatinvitelink
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class ChatInviteLink extends TelegramObject
{
  public function __construct(
    public readonly string $inviteLink,
    public readonly User $creator,
    public readonly bool $createsJoinRequest,
    public readonly bool $isPrimary,
    public readonly bool $isRevoked,
    public readonly ?string $name = null,
    public readonly ?DateTime $expireDate = null,
    public readonly ?int $memberLimit = null,
    public readonly ?int $pendingJoinRequestCount = null,
    public readonly ?int $subscriptionPeriod = null,
    public readonly ?int $subscriptionPrice = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
