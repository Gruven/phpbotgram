<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\PhotoSize;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_photo_size.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a).
 *
 * @internal
 */
final class PhotoSizeTypeTest extends TestCase
{
  public function testRequiredArgsOnly(): void
  {
    $ps = new PhotoSize(fileId: 'AgAC', fileUniqueId: 'uni1', width: 100, height: 200);
    self::assertSame('AgAC', $ps->fileId);
    self::assertSame('uni1', $ps->fileUniqueId);
    self::assertSame(100, $ps->width);
    self::assertSame(200, $ps->height);
    self::assertNull($ps->fileSize);
  }

  public function testWithFileSize(): void
  {
    $ps = new PhotoSize(fileId: 'AgAC', fileUniqueId: 'uni2', width: 800, height: 600, fileSize: 51200);
    self::assertSame(51200, $ps->fileSize);
  }

  public function testLargePhoto(): void
  {
    $ps = new PhotoSize(fileId: 'large_id', fileUniqueId: 'large_u', width: 2560, height: 1440, fileSize: 2_097_152);
    self::assertSame(2560, $ps->width);
    self::assertSame(1440, $ps->height);
    self::assertSame(2_097_152, $ps->fileSize);
  }
}
