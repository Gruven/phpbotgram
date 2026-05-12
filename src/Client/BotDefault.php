<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

/**
 * Sentinel for "use the bot's configured default for this field".
 *
 * Renamed from upstream `Default` because PHP reserves `default` as a keyword
 * (case-insensitive) so `class Default` won't parse even when namespaced.
 *
 * The Serializer always resolves BotDefault instances against
 * $bot->getDefaultProperties() before encoding. jsonSerialize throws so a
 * BotDefault that escapes resolution fails loudly rather than silently
 * emitting `null` on the wire.
 */
final readonly class BotDefault implements \JsonSerializable
{
    public function __construct(public string $name) {}

    public function equals(BotDefault $other): bool
    {
        return $this->name === $other->name;
    }

    public function jsonSerialize(): never
    {
        throw new \LogicException(
            "BotDefault sentinel reached json_encode without being resolved: {$this->name}"
        );
    }

    public function __toString(): string
    {
        return "BotDefault('{$this->name}')";
    }
}
