<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Custom;

/**
 * Marker subclass of DateTimeImmutable used for fields whose schema declares
 * an integer Unix timestamp but should be exposed as a DateTime in PHP
 * (e.g. Message::date). The Serializer converts on the way in (timestamp →
 * DateTime) and out (DateTime → timestamp).
 */
final class DateTime extends \DateTimeImmutable
{
    public static function fromTimestamp(int $ts): self
    {
        return new self('@' . $ts);
    }

    public function toTimestamp(): int
    {
        return (int) $this->format('U');
    }
}
