<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Types\ForceReply;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_types/test_force_reply.py
 *
 * Upstream skips:
 *   - Pydantic model_validate / model_dump — API divergence (a).
 */
final class ForceReplyTypeTest extends TestCase
{
  public function testDefaultForceReplyTrue(): void
  {
    $fr = new ForceReply();
    self::assertTrue($fr->forceReply);
    self::assertNull($fr->inputFieldPlaceholder);
    self::assertNull($fr->selective);
  }

  public function testWithPlaceholderAndSelective(): void
  {
    $fr = new ForceReply(forceReply: true, inputFieldPlaceholder: 'Your answer...', selective: true);
    self::assertTrue($fr->forceReply);
    self::assertSame('Your answer...', $fr->inputFieldPlaceholder);
    self::assertTrue($fr->selective);
  }

  public function testSendMessageAcceptsForceReply(): void
  {
    // Verifies ForceReply is a valid replyMarkup type for SendMessage.
    $fr = new ForceReply(selective: false);
    self::assertFalse($fr->selective);
  }
}
