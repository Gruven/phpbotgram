<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use ArrayAccess;
use Gruven\PhpBotGram\Types\LinkPreviewOptions;
use LogicException;

/**
 * @implements ArrayAccess<string, mixed>
 */
final class DefaultBotProperties implements ArrayAccess
{
  public readonly ?LinkPreviewOptions $linkPreview;

  public function __construct(
    public readonly ?string $parseMode = null,
    public readonly ?bool $disableNotification = null,
    public readonly ?bool $protectContent = null,
    public readonly ?bool $allowSendingWithoutReply = null,
    ?LinkPreviewOptions $linkPreview = null,
    public readonly ?bool $linkPreviewIsDisabled = null,
    public readonly ?bool $linkPreviewPreferSmallMedia = null,
    public readonly ?bool $linkPreviewPreferLargeMedia = null,
    public readonly ?bool $linkPreviewShowAboveText = null,
    public readonly ?bool $showCaptionAboveMedia = null,
  ) {
    $hasAnyLinkPreview = $linkPreviewIsDisabled !== null
        || $linkPreviewPreferSmallMedia !== null
        || $linkPreviewPreferLargeMedia !== null
        || $linkPreviewShowAboveText !== null;

    if ($linkPreview === null && $hasAnyLinkPreview) {
      $linkPreview = new LinkPreviewOptions(
        isDisabled: $linkPreviewIsDisabled,
        preferSmallMedia: $linkPreviewPreferSmallMedia,
        preferLargeMedia: $linkPreviewPreferLargeMedia,
        showAboveText: $linkPreviewShowAboveText,
      );
    }
    $this->linkPreview = $linkPreview;
  }

  public function get(string $name): mixed
  {
    return match ($name) {
      'parse_mode' => $this->parseMode,
      'disable_notification' => $this->disableNotification,
      'protect_content' => $this->protectContent,
      'allow_sending_without_reply' => $this->allowSendingWithoutReply,
      'link_preview' => $this->linkPreview,
      'link_preview_is_disabled' => $this->linkPreviewIsDisabled,
      'link_preview_prefer_small_media' => $this->linkPreviewPreferSmallMedia,
      'link_preview_prefer_large_media' => $this->linkPreviewPreferLargeMedia,
      'link_preview_show_above_text' => $this->linkPreviewShowAboveText,
      'show_caption_above_media' => $this->showCaptionAboveMedia,
      default => null,
    };
  }

  public function offsetExists(mixed $offset): bool
  {
    return is_string($offset) && $this->get($offset) !== null;
  }

  public function offsetGet(mixed $offset): mixed
  {
    return is_string($offset) ? $this->get($offset) : null;
  }

  public function offsetSet(mixed $offset, mixed $value): void
  {
    throw new LogicException('DefaultBotProperties is immutable');
  }

  public function offsetUnset(mixed $offset): void
  {
    throw new LogicException('DefaultBotProperties is immutable');
  }
}
