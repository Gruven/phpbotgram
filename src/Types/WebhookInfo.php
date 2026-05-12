<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Custom\DateTime;

/**
 * Describes the current status of a webhook.
 *
 * Source: https://core.telegram.org/bots/api#webhookinfo
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class WebhookInfo extends TelegramObject
{
  /**
   * @param list<string> $allowedUpdates
   */
  public function __construct(
    public readonly string $url,
    public readonly bool $hasCustomCertificate,
    public readonly int $pendingUpdateCount,
    public readonly ?string $ipAddress = null,
    public readonly ?DateTime $lastErrorDate = null,
    public readonly ?string $lastErrorMessage = null,
    public readonly ?DateTime $lastSynchronizationErrorDate = null,
    public readonly ?int $maxConnections = null,
    public readonly ?array $allowedUpdates = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
