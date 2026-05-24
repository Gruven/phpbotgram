<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MediaGroup;

use Gruven\PhpBotGram\Client\BotDefault;
use Gruven\PhpBotGram\Types\FsInputFile;
use Gruven\PhpBotGram\Types\InputMediaAudio;
use Gruven\PhpBotGram\Types\InputMediaDocument;
use Gruven\PhpBotGram\Types\InputMediaPhoto;
use Gruven\PhpBotGram\Types\InputMediaVideo;
use Gruven\PhpBotGram\Types\MessageEntity;
use Gruven\PhpBotGram\Utils\MediaGroup\MediaGroupBuilder;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural tests for {@see MediaGroupBuilder}.
 *
 * Covers the port of `aiogram/utils/media_group.py`.
 *
 * Upstream skips
 * --------------
 * - `test_add_incorrect_media` (calls `builder._add("test")`): PHP has no
 *   public `_add()` method — API divergence (a).
 * - `test_extend` (calls `builder._extend([media, media])`): PHP has no
 *   public `_extend()` method — API divergence (a).
 * - `test_add` (generic `builder.add(type="audio", ...)` dispatch): PHP uses
 *   type-specific `addAudio/addVideo/addPhoto/addDocument` methods — API
 *   divergence (a); covered implicitly by the per-type add tests.
 * - `test_add_unknown_type`: same — API divergence (a).
 * - `test_build_empty` upstream returns `[]`; PHP throws `LogicException` —
 *   API divergence (a); covered by `testEmptyBuildThrowsLogicException`.
 * - `test_build_with_caption` row where builder caption overrides per-item
 *   caption only on first item while subsequent items retain their own
 *   caption: covered by `testBuilderCaptionOnlyChangesFirstItem`.
 *
 * @internal
 */
final class MediaGroupBuilderTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // Empty builder
  // ---------------------------------------------------------------------------

  public function testEmptyBuildThrowsLogicException(): void
  {
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Media group must contain at least one item');

    (new MediaGroupBuilder())->build();
  }

  // ---------------------------------------------------------------------------
  // addPhoto
  // ---------------------------------------------------------------------------

  public function testAddPhotoReturnsSelf(): void
  {
    $builder = new MediaGroupBuilder();
    $result = $builder->addPhoto('file_id_1');

    self::assertSame($builder, $result);
  }

  public function testAddPhotoTwiceBuildsTwoItems(): void
  {
    $items = (new MediaGroupBuilder())
      ->addPhoto('photo_1')
      ->addPhoto('photo_2')
      ->build();

    self::assertCount(2, $items);
    self::assertInstanceOf(InputMediaPhoto::class, $items[0]);
    self::assertInstanceOf(InputMediaPhoto::class, $items[1]);
    self::assertSame('photo_1', $items[0]->media);
    self::assertSame('photo_2', $items[1]->media);
  }

  // ---------------------------------------------------------------------------
  // addVideo
  // ---------------------------------------------------------------------------

  public function testAddVideoReturnsSelf(): void
  {
    $builder = new MediaGroupBuilder();
    $result = $builder->addVideo('video_id');

    self::assertSame($builder, $result);
  }

  public function testAddVideoWithOptionalArgs(): void
  {
    $thumb = new FsInputFile('/tmp/thumb.jpg');
    $items = (new MediaGroupBuilder())
      ->addVideo(
        media: 'video_id',
        thumbnail: $thumb,
        duration: 120,
        width: 1920,
        height: 1080,
        supportsStreaming: true,
        hasSpoiler: false,
      )
      ->build();

    self::assertCount(1, $items);
    $video = $items[0];
    self::assertInstanceOf(InputMediaVideo::class, $video);
    self::assertSame('video_id', $video->media);
    self::assertSame($thumb, $video->thumbnail);
    self::assertSame(120, $video->duration);
    self::assertSame(1920, $video->width);
    self::assertSame(1080, $video->height);
    self::assertTrue($video->supportsStreaming);
    self::assertFalse($video->hasSpoiler);
  }

  // ---------------------------------------------------------------------------
  // addAudio
  // ---------------------------------------------------------------------------

  public function testAddAudioReturnsSelf(): void
  {
    $builder = new MediaGroupBuilder();
    $result = $builder->addAudio('audio_id');

    self::assertSame($builder, $result);
  }

  public function testAddAudioWithPerformerAndTitle(): void
  {
    $items = (new MediaGroupBuilder())
      ->addAudio(
        media: 'audio_id',
        performer: 'The Artist',
        title: 'Great Song',
        duration: 240,
      )
      ->build();

    self::assertCount(1, $items);
    $audio = $items[0];
    self::assertInstanceOf(InputMediaAudio::class, $audio);
    self::assertSame('The Artist', $audio->performer);
    self::assertSame('Great Song', $audio->title);
    self::assertSame(240, $audio->duration);
  }

  // ---------------------------------------------------------------------------
  // addDocument
  // ---------------------------------------------------------------------------

  public function testAddDocumentReturnsSelf(): void
  {
    $builder = new MediaGroupBuilder();
    $result = $builder->addDocument('doc_id');

    self::assertSame($builder, $result);
  }

  public function testAddDocumentWithDisableContentTypeDetection(): void
  {
    $items = (new MediaGroupBuilder())
      ->addDocument(media: 'doc_id', disableContentTypeDetection: true)
      ->build();

    self::assertCount(1, $items);
    $doc = $items[0];
    self::assertInstanceOf(InputMediaDocument::class, $doc);
    self::assertTrue($doc->disableContentTypeDetection);
  }

  // ---------------------------------------------------------------------------
  // Photo + Video coexistence
  // ---------------------------------------------------------------------------

  public function testPhotoAndVideoCanCoexist(): void
  {
    $items = (new MediaGroupBuilder())
      ->addPhoto('photo_id')
      ->addVideo('video_id')
      ->addPhoto('photo_id_2')
      ->build();

    self::assertCount(3, $items);
  }

  // ---------------------------------------------------------------------------
  // Homogeneity enforcement
  // ---------------------------------------------------------------------------

  public function testAddAudioAfterPhotoThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);

    (new MediaGroupBuilder())
      ->addPhoto('photo_id')
      ->addAudio('audio_id');
  }

  public function testAddPhotoAfterAudioThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);

    (new MediaGroupBuilder())
      ->addAudio('audio_id')
      ->addPhoto('photo_id');
  }

  public function testAddDocumentAfterPhotoThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);

    (new MediaGroupBuilder())
      ->addPhoto('photo_id')
      ->addDocument('doc_id');
  }

  public function testAddAudioAfterDocumentThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);

    (new MediaGroupBuilder())
      ->addDocument('doc_id')
      ->addAudio('audio_id');
  }

  public function testAddVideoAfterDocumentThrows(): void
  {
    $this->expectException(InvalidArgumentException::class);

    (new MediaGroupBuilder())
      ->addDocument('doc_id')
      ->addVideo('video_id');
  }

  // ---------------------------------------------------------------------------
  // Caption placement
  // ---------------------------------------------------------------------------

  public function testBuilderCaptionSetOnFirstItemOnly(): void
  {
    $items = (new MediaGroupBuilder(caption: 'Album caption'))
      ->addPhoto('photo_1')
      ->addPhoto('photo_2')
      ->addPhoto('photo_3')
      ->build();

    self::assertCount(3, $items);
    self::assertSame('Album caption', $items[0]->caption);
    self::assertNull($items[1]->caption);
    self::assertNull($items[2]->caption);
  }

  public function testBuilderCaptionDoesNotModifyBuilderInternalState(): void
  {
    $builder = (new MediaGroupBuilder(caption: 'First build'))
      ->addPhoto('photo_1')
      ->addPhoto('photo_2');

    $first = $builder->build();
    $second = $builder->build();

    // Both builds should produce the same result independently.
    self::assertSame('First build', $first[0]->caption);
    self::assertSame('First build', $second[0]->caption);
    self::assertNull($first[1]->caption);
    self::assertNull($second[1]->caption);
  }

  public function testBuilderCaptionEntitiesAloneDoesNotOverrideFirstItem(): void
  {
    // Upstream: caption_entities alone (no caption) does NOT trigger the
    // override block — only when self.caption is not None.
    $entity = new MessageEntity(type: 'bold', offset: 0, length: 5);
    $items = (new MediaGroupBuilder(captionEntities: [$entity]))
      ->addPhoto('photo_1', caption: 'per-item caption')
      ->addPhoto('photo_2')
      ->build();

    self::assertCount(2, $items);
    // First item caption must be preserved (builder caption is null → no override).
    self::assertSame('per-item caption', $items[0]->caption);
    // Builder-level captionEntities are NOT injected when caption is null.
    self::assertNull($items[0]->captionEntities);
    self::assertNull($items[1]->captionEntities);
  }

  public function testBuildLeavesFirstItemCaptionWhenBuilderCaptionIsNull(): void
  {
    // When the builder has captionEntities but no caption, build() must leave
    // the first item's per-item caption and entities untouched.
    $entity = new MessageEntity(type: 'italic', offset: 0, length: 3);
    $items = (new MediaGroupBuilder(captionEntities: [$entity]))
      ->addPhoto('photo_1', caption: 'original caption')
      ->build();

    self::assertCount(1, $items);
    self::assertSame('original caption', $items[0]->caption);
    self::assertNull($items[0]->captionEntities); // per-item had no entities
  }

  public function testBuilderCaptionOnlyChangesFirstItem(): void
  {
    // Per-item captions on non-first items must be preserved.
    $items = (new MediaGroupBuilder(caption: 'Group caption'))
      ->addPhoto('photo_1')
      ->addPhoto('photo_2', caption: 'Item 2 caption')
      ->build();

    self::assertSame('Group caption', $items[0]->caption);
    // Non-first items keep their own per-item caption.
    self::assertSame('Item 2 caption', $items[1]->caption);
  }

  // ---------------------------------------------------------------------------
  // Constructor media list
  // ---------------------------------------------------------------------------

  public function testConstructorAcceptsMediaList(): void
  {
    $photo1 = new InputMediaPhoto(media: 'file_id_1', caption: 'First');
    $photo2 = new InputMediaPhoto(media: 'file_id_2');
    $builder = new MediaGroupBuilder(media: [$photo1, $photo2]);
    $items = $builder->build();

    self::assertCount(2, $items);
    self::assertInstanceOf(InputMediaPhoto::class, $items[0]);
    self::assertInstanceOf(InputMediaPhoto::class, $items[1]);
    self::assertSame('file_id_1', $items[0]->media);
    self::assertSame('file_id_2', $items[1]->media);
    self::assertSame('First', $items[0]->caption);
  }

  public function testConstructorMediaListRespectsCaptionOverride(): void
  {
    $photo = new InputMediaPhoto(media: 'file_id_1', caption: 'per-item');
    $items = (new MediaGroupBuilder(caption: 'builder caption', media: [$photo]))->build();

    self::assertCount(1, $items);
    // Builder caption takes precedence for first item.
    self::assertSame('builder caption', $items[0]->caption);
  }

  public function testConstructorMediaListEnforcesHomogeneity(): void
  {
    $this->expectException(InvalidArgumentException::class);

    new MediaGroupBuilder(media: [
      new InputMediaPhoto(media: 'photo_id'),
      new InputMediaAudio(media: 'audio_id'),
    ]);
  }

  // ---------------------------------------------------------------------------
  // Max size
  // ---------------------------------------------------------------------------

  public function testMaxGroupSizeEnforced(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessageMatches('/10 elements/');

    $builder = new MediaGroupBuilder();

    for ($i = 0; $i < MediaGroupBuilder::MAX_MEDIA_GROUP_SIZE + 1; $i++) {
      $builder->addPhoto("photo_{$i}");
    }
  }

  // ---------------------------------------------------------------------------
  // Chaining
  // ---------------------------------------------------------------------------

  public function testFluentChainingWorks(): void
  {
    $result = (new MediaGroupBuilder())
      ->addPhoto('p1')
      ->addPhoto('p2')
      ->addVideo('v1')
      ->build();

    self::assertCount(3, $result);
  }

  // ---------------------------------------------------------------------------
  // BotDefault sentinel preservation
  // ---------------------------------------------------------------------------

  public function testAddPhotoForwardsBotDefaultParseMode(): void
  {
    // When addPhoto() is called without an explicit parseMode the resulting
    // InputMediaPhoto must carry the BotDefault sentinel, NOT null.
    $items = (new MediaGroupBuilder())->addPhoto('photo_id')->build();

    $item = $items[0];
    self::assertInstanceOf(InputMediaPhoto::class, $item);
    self::assertInstanceOf(BotDefault::class, $item->parseMode);
    self::assertSame('parse_mode', $item->parseMode->name);
  }

  public function testAddPhotoForwardsBotDefaultShowCaptionAboveMedia(): void
  {
    $items = (new MediaGroupBuilder())->addPhoto('photo_id')->build();

    $item = $items[0];
    self::assertInstanceOf(InputMediaPhoto::class, $item);
    self::assertInstanceOf(BotDefault::class, $item->showCaptionAboveMedia);
    self::assertSame('show_caption_above_media', $item->showCaptionAboveMedia->name);
  }

  // ---------------------------------------------------------------------------
  // Build independence (builder reuse)
  // ---------------------------------------------------------------------------

  public function testBuildReturnsNewInstancesNotOriginals(): void
  {
    $builder = (new MediaGroupBuilder())->addPhoto('photo_id');

    $first = $builder->build();
    $second = $builder->build();

    // Each build call returns distinct PHP objects.
    self::assertNotSame($first[0], $second[0]);
  }
}
