<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\WebApp;

use function is_bool;
use function is_int;
use function is_string;

/**
 * Represents the user object embedded in WebApp init data.
 *
 * Minimal hand-rolled DTO mirroring the `user` field of `WebAppInitData`.
 * Fields match the Telegram Bot API WebAppUser spec.
 */
final class WebAppUser
{
  public function __construct(
    public readonly int $id,
    public readonly bool $isBot,
    public readonly string $firstName,
    public readonly ?string $lastName = null,
    public readonly ?string $username = null,
    public readonly ?string $languageCode = null,
    public readonly ?bool $isPremium = null,
    public readonly ?bool $addedToAttachmentMenu = null,
    public readonly ?bool $allowsWriteToPm = null,
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
    $firstName = $data['first_name'] ?? '';
    $lastName = $data['last_name'] ?? null;
    $username = $data['username'] ?? null;
    $languageCode = $data['language_code'] ?? null;
    $isPremium = $data['is_premium'] ?? null;
    $addedToAttachmentMenu = $data['added_to_attachment_menu'] ?? null;
    $allowsWriteToPm = $data['allows_write_to_pm'] ?? null;
    $photoUrl = $data['photo_url'] ?? null;

    return new self(
      id: is_int($id) ? $id : (int)(is_string($id) ? $id : 0),
      isBot: is_bool($data['is_bot'] ?? null) ? (bool)$data['is_bot'] : false,
      firstName: is_string($firstName) ? $firstName : '',
      lastName: is_string($lastName) ? $lastName : null,
      username: is_string($username) ? $username : null,
      languageCode: is_string($languageCode) ? $languageCode : null,
      isPremium: is_bool($isPremium) ? $isPremium : null,
      addedToAttachmentMenu: is_bool($addedToAttachmentMenu) ? $addedToAttachmentMenu : null,
      allowsWriteToPm: is_bool($allowsWriteToPm) ? $allowsWriteToPm : null,
      photoUrl: is_string($photoUrl) ? $photoUrl : null,
    );
  }
}
