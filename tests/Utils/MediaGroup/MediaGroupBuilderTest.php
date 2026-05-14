<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils\MediaGroup;

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

  public function testBuilderCaptionEntitiesSetOnFirstItem(): void
  {
    $entity = new MessageEntity(type: 'bold', offset: 0, length: 5);
    $items = (new MediaGroupBuilder(captionEntities: [$entity]))
      ->addPhoto('photo_1')
      ->addPhoto('photo_2')
      ->build();

    self::assertCount(2, $items);
    self::assertSame([$entity], $items[0]->captionEntities);
    self::assertNull($items[0]->parseMode); // parse_mode cleared when entities provided
    self::assertNull($items[1]->captionEntities);
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
