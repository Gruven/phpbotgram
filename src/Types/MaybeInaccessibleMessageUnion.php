<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;

/**
 * Structural resolver for the {@see MaybeInaccessibleMessage} union.
 *
 * Telegram marks inaccessible messages with `date = 0`; all other payloads use
 * the full {@see Message} shape. This union cannot be rendered as a normal
 * discriminator match because the same `date` field is also real message data.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MaybeInaccessibleMessageUnion
{
  /**
   * @return list<class-string<MaybeInaccessibleMessage>>
   */
  public static function members(): array
  {
    return [
      Message::class,
      InaccessibleMessage::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): MaybeInaccessibleMessage
  {
    if (($payload['date'] ?? null) === 0) {
      return Serializer::load(InaccessibleMessage::class, $payload, $bot);
    }

    return Serializer::load(Message::class, $payload, $bot);
  }
}
