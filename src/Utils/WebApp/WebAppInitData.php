<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\WebApp;

/**
 * Parsed representation of Telegram WebApp init data.
 *
 * Minimal hand-rolled DTO mirroring `WebAppInitData` from
 * `aiogram/utils/web_app.py`. Generated Telegram types in
 * `src/Types/Generated/` do not include this structure, so it lives here.
 */
final class WebAppInitData
{
  public function __construct(
    /** Unix timestamp of when the init data was created. */
    public readonly int $authDate,
    /** HMAC-SHA256 hash for integrity verification. */
    public readonly string $hash,
    /** Optional query id from the Mini App launch. */
    public readonly ?string $queryId = null,
    /** The user who opened the Mini App. */
    public readonly ?WebAppUser $user = null,
    /** The user who initiated the conversation (in group context). */
    public readonly ?WebAppUser $receiver = null,
    /** The chat from which the Mini App was opened (supergroup/channel). */
    public readonly ?WebAppChat $chat = null,
    /** The type of chat from which the Mini App was opened. */
    public readonly ?string $chatType = null,
    /** The global identifier of the chat from which the Mini App was opened. */
    public readonly ?string $chatInstance = null,
    /** The value of the `startattach` parameter or an empty string. */
    public readonly ?string $startParam = null,
    /**
     * Duration in seconds for which the Mini App can be opened without
     * requiring user interaction.
     */
    public readonly ?int $canSendAfter = null,
  ) {}
}
