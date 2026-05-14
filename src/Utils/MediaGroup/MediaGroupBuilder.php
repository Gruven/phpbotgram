<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MediaGroup;

use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\InputFile;
use Gruven\PhpBotGram\Types\InputMediaAudio;
use Gruven\PhpBotGram\Types\InputMediaDocument;
use Gruven\PhpBotGram\Types\InputMediaPhoto;
use Gruven\PhpBotGram\Types\InputMediaVideo;
use Gruven\PhpBotGram\Types\MessageEntity;
use InvalidArgumentException;
use LogicException;

/**
 * Fluent builder for Telegram media groups.
 *
 * Port of upstream `aiogram/utils/media_group.py` — `MediaGroupBuilder` class.
 *
 * Telegram media-group homogeneity rules:
 *  - Photo and Video items may coexist in the same group.
 *  - Audio items must form a homogeneous group (no mixing with other types).
 *  - Document items must form a homogeneous group (no mixing with other types).
 *
 * Usage:
 * ```php
 * $builder = new MediaGroupBuilder(caption: 'My album');
 * $builder->addPhoto('file_id_1')->addPhoto('file_id_2')->addVideo('file_id_3');
 * $mediaList = $builder->build();
 * ```
 */
final class MediaGroupBuilder
{
  /** Maximum number of items in a Telegram media group. */
  public const int MAX_MEDIA_GROUP_SIZE = 10;

  /**
   * @var list<InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo>
   */
  private array $media = [];

  /**
   * @param null|list<MessageEntity> $captionEntities
   */
  public function __construct(
    private readonly ?string $caption = null,
    private readonly ?array $captionEntities = null,
  ) {}

  /**
   * Add a photo to the media group.
   *
   * @param null|list<MessageEntity> $captionEntities
   */
  public function addPhoto(
    InputFile|string $media,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?bool $showCaptionAboveMedia = null,
    ?bool $hasSpoiler = null,
  ): static {
    return $this->append(new InputMediaPhoto(
      media: $media,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      hasSpoiler: $hasSpoiler,
    ));
  }

  /**
   * Add a video to the media group.
   *
   * @param null|list<MessageEntity> $captionEntities
   */
  public function addVideo(
    InputFile|string $media,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?bool $showCaptionAboveMedia = null,
    ?int $width = null,
    ?int $height = null,
    ?int $duration = null,
    ?bool $supportsStreaming = null,
    ?bool $hasSpoiler = null,
  ): static {
    return $this->append(new InputMediaVideo(
      media: $media,
      thumbnail: $thumbnail,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      showCaptionAboveMedia: $showCaptionAboveMedia,
      width: $width,
      height: $height,
      duration: $duration,
      supportsStreaming: $supportsStreaming,
      hasSpoiler: $hasSpoiler,
    ));
  }

  /**
   * Add an audio file to the media group.
   *
   * @param null|list<MessageEntity> $captionEntities
   */
  public function addAudio(
    InputFile|string $media,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?int $duration = null,
    ?string $performer = null,
    ?string $title = null,
  ): static {
    return $this->append(new InputMediaAudio(
      media: $media,
      thumbnail: $thumbnail,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      duration: $duration,
      performer: $performer,
      title: $title,
    ));
  }

  /**
   * Add a document to the media group.
   *
   * @param null|list<MessageEntity> $captionEntities
   */
  public function addDocument(
    InputFile|string $media,
    ?InputFile $thumbnail = null,
    ?string $caption = null,
    ?string $parseMode = null,
    ?array $captionEntities = null,
    ?bool $disableContentTypeDetection = null,
  ): static {
    return $this->append(new InputMediaDocument(
      media: $media,
      thumbnail: $thumbnail,
      caption: $caption,
      parseMode: $parseMode,
      captionEntities: $captionEntities,
      disableContentTypeDetection: $disableContentTypeDetection,
    ));
  }

  /**
   * Build and return the assembled media list.
   *
   * If a builder-level `$caption` is set it is injected into the first item,
   * overwriting any per-item caption. Same for `$captionEntities`.
   *
   * The returned list is independent from the builder's internal state
   * (items are new instances), so the builder can be reused after a call to `build()`.
   *
   * @return list<InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo>
   *
   * @throws LogicException if the media list is empty.
   */
  public function build(): array
  {
    if ($this->media === []) {
      throw new LogicException('Media group must contain at least one item');
    }

    $result = [];

    foreach ($this->media as $index => $item) {
      if ($index === 0 && ($this->caption !== null || $this->captionEntities !== null)) {
        $caption = $this->caption ?? $item->caption;
        $captionEntities = $this->captionEntities ?? $item->captionEntities;
        $parseMode = $this->captionEntities !== null ? null : $item->parseMode;

        $result[] = $this->copyWithCaption($item, $caption, $captionEntities, $parseMode);
      } else {
        $result[] = $this->copy($item);
      }
    }

    return $result;
  }

  /**
   * Return a shallow copy of an item (all constructor args preserved as-is).
   *
   * @param InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo $item
   *
   * @return InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo
   */
  private function copy(
    InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo $item,
  ): InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo {
    return match (true) {
      $item instanceof InputMediaPhoto => new InputMediaPhoto(
        media: $item->media,
        caption: $item->caption,
        parseMode: $item->parseMode,
        captionEntities: $item->captionEntities,
        showCaptionAboveMedia: $item->showCaptionAboveMedia,
        hasSpoiler: $item->hasSpoiler,
      ),
      $item instanceof InputMediaVideo => new InputMediaVideo(
        media: $item->media,
        thumbnail: $item->thumbnail,
        cover: $item->cover,
        startTimestamp: $item->startTimestamp,
        caption: $item->caption,
        parseMode: $item->parseMode,
        captionEntities: $item->captionEntities,
        showCaptionAboveMedia: $item->showCaptionAboveMedia,
        width: $item->width,
        height: $item->height,
        duration: $item->duration,
        supportsStreaming: $item->supportsStreaming,
        hasSpoiler: $item->hasSpoiler,
      ),
      $item instanceof InputMediaAudio => new InputMediaAudio(
        media: $item->media,
        thumbnail: $item->thumbnail,
        caption: $item->caption,
        parseMode: $item->parseMode,
        captionEntities: $item->captionEntities,
        duration: $item->duration,
        performer: $item->performer,
        title: $item->title,
      ),
      default => new InputMediaDocument(
        media: $item->media,
        thumbnail: $item->thumbnail,
        caption: $item->caption,
        parseMode: $item->parseMode,
        captionEntities: $item->captionEntities,
        disableContentTypeDetection: $item->disableContentTypeDetection,
      ),
    };
  }

  /**
   * Return a copy of an item with `caption`, `captionEntities`, and `parseMode`
   * overridden. Used by `build()` to inject the builder-level caption into the
   * first item.
   *
   * @param null|list<MessageEntity> $captionEntities
   *
   * @return InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo
   */
  private function copyWithCaption(
    InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo $item,
    ?string $caption,
    ?array $captionEntities,
    null|BotDefault|string $parseMode,
  ): InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo {
    return match (true) {
      $item instanceof InputMediaPhoto => new InputMediaPhoto(
        media: $item->media,
        caption: $caption,
        parseMode: $parseMode,
        captionEntities: $captionEntities,
        showCaptionAboveMedia: $item->showCaptionAboveMedia,
        hasSpoiler: $item->hasSpoiler,
      ),
      $item instanceof InputMediaVideo => new InputMediaVideo(
        media: $item->media,
        thumbnail: $item->thumbnail,
        cover: $item->cover,
        startTimestamp: $item->startTimestamp,
        caption: $caption,
        parseMode: $parseMode,
        captionEntities: $captionEntities,
        showCaptionAboveMedia: $item->showCaptionAboveMedia,
        width: $item->width,
        height: $item->height,
        duration: $item->duration,
        supportsStreaming: $item->supportsStreaming,
        hasSpoiler: $item->hasSpoiler,
      ),
      $item instanceof InputMediaAudio => new InputMediaAudio(
        media: $item->media,
        thumbnail: $item->thumbnail,
        caption: $caption,
        parseMode: $parseMode,
        captionEntities: $captionEntities,
        duration: $item->duration,
        performer: $item->performer,
        title: $item->title,
      ),
      default => new InputMediaDocument(
        media: $item->media,
        thumbnail: $item->thumbnail,
        caption: $caption,
        parseMode: $parseMode,
        captionEntities: $captionEntities,
        disableContentTypeDetection: $item->disableContentTypeDetection,
      ),
    };
  }

  /**
   * Append a media item to the internal list after validating homogeneity
   * and group-size constraints.
   *
   * @throws InvalidArgumentException if the new item breaks the homogeneity
   *                                  rule for the current group.
   * @throws InvalidArgumentException if the group is already at MAX_MEDIA_GROUP_SIZE.
   */
  private function append(
    InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo $item,
  ): static {
    if (count($this->media) >= self::MAX_MEDIA_GROUP_SIZE) {
      throw new InvalidArgumentException(
        sprintf("Media group can't contain more than %d elements", self::MAX_MEDIA_GROUP_SIZE),
      );
    }

    if ($this->media !== []) {
      $this->validateHomogeneity($item);
    }

    $this->media[] = $item;

    return $this;
  }

  /**
   * Enforce Telegram's media-group homogeneity rules against the existing
   * items in `$this->media`:
   *
   *  - Photo + Video may coexist.
   *  - Audio must not be mixed with any other type.
   *  - Document must not be mixed with any other type.
   *
   * @throws InvalidArgumentException on a type conflict.
   */
  private function validateHomogeneity(
    InputMediaAudio|InputMediaDocument|InputMediaPhoto|InputMediaVideo $incoming,
  ): void {
    $existingType = $this->groupType();

    $incomingIsPhotoVideo = $incoming instanceof InputMediaPhoto || $incoming instanceof InputMediaVideo;
    $incomingIsAudio = $incoming instanceof InputMediaAudio;
    $incomingIsDocument = $incoming instanceof InputMediaDocument;

    $conflict = match (true) {
      $incomingIsAudio && $existingType !== 'audio' => sprintf(
        'Cannot mix audio with %s in a media group',
        $existingType,
      ),
      $incomingIsDocument && $existingType !== 'document' => sprintf(
        'Cannot mix document with %s in a media group',
        $existingType,
      ),
      $incomingIsPhotoVideo && $existingType === 'audio' => sprintf(
        'Cannot mix %s with audio in a media group',
        $incoming instanceof InputMediaPhoto ? 'photo' : 'video',
      ),
      $incomingIsPhotoVideo && $existingType === 'document' => sprintf(
        'Cannot mix %s with document in a media group',
        $incoming instanceof InputMediaPhoto ? 'photo' : 'video',
      ),
      default => null,
    };

    if ($conflict !== null) {
      throw new InvalidArgumentException($conflict);
    }
  }

  /**
   * Infer the "type class" of the current group from the first element.
   *
   * Returns `'photo_video'`, `'audio'`, or `'document'`.
   */
  private function groupType(): string
  {
    $first = $this->media[0];

    return match (true) {
      $first instanceof InputMediaAudio => 'audio',
      $first instanceof InputMediaDocument => 'document',
      default => 'photo_video',
    };
  }
}
