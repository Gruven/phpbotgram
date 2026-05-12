<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

final class User extends TelegramObject
{
    public function __construct(
        public readonly int $id,
        public readonly bool $isBot,
        public readonly string $firstName,
        // Nullable fields accept `Unspecified::instance()` to opt out of wire serialization
        // (Serializer::dump strips these); explicit `null` is preserved on the wire.
        public readonly string|Unspecified|null $lastName = null,
        public readonly string|Unspecified|null $username = null,
        public readonly string|Unspecified|null $languageCode = null,
        ?\Gruven\PhpBotGram\Bot $bot = null,
    ) {
        parent::__construct($bot);
    }
}
