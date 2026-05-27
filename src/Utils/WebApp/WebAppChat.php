<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\WebApp;

use function is_int;
use function is_string;

/**
 * Represents the chat object embedded in WebApp init data.
 *
 * Minimal hand-rolled DTO mirroring the `chat` field of `WebAppInitData`.
 * Fields match the Telegram Bot API WebAppChat spec.
 */
final class WebAppChat
{
  public function __construct(
    public readonly int $id,
    public readonly string $type,
    public readonly string $title,
    public readonly ?string $username = null,
    public readonly ?string $photoUrl = null,
  ) {}

  /**
   * Construct from a raw JSON-decoded assoc array.
   *
   * @param array<string, mixed> $data
   */
  public static function fromArray(array $data): self
  {
    $id = $data['id'] ?? 0;
    $type = $data['type'] ?? '';
    $title = $data['title'] ?? '';
    $username = $data['username'] ?? null;
    $photoUrl = $data['photo_url'] ?? null;

    return new self(
      id: is_int($id) ? $id : (int)(is_string($id) ? $id : 0),
      type: is_string($type) ? $type : '',
      title: is_string($title) ? $title : '',
      username: is_string($username) ? $username : null,
      photoUrl: is_string($photoUrl) ? $photoUrl : null,
    );
  }
}
