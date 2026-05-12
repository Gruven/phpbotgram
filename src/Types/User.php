<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

final class User extends TelegramObject
{
  public function __construct(
    public readonly int $id,
    public readonly bool $isBot,
    public readonly string $firstName,
    // Nullable fields accept `Unspecified::instance()` to opt out of wire serialization
    // (Serializer::dump strips these); explicit `null` is preserved on the wire.
    public readonly null|string|Unspecified $lastName = null,
    public readonly null|string|Unspecified $username = null,
    public readonly null|string|Unspecified $languageCode = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
