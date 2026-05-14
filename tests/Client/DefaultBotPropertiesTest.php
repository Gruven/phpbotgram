<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Enums\ParseMode;
use Gruven\PhpBotGram\Types\LinkPreviewOptions;
use PHPUnit\Framework\TestCase;

/**
 * Upstream: tests/test_api/test_client/test_default.py — class TestDefaultBotProperties
 *
 * Upstream skips:
 *   - test_dataclass_creation_3_10_plus — API divergence (a): Python-specific
 *     `dataclass.__dataclass_params__` introspection, no PHP equivalent.
 */
final class DefaultBotPropertiesTest extends TestCase
{
  /** Upstream: test_post_init_empty — empty constructor leaves link_preview null. */
  public function testGetReturnsNullWhenUnset(): void
  {
    $d = new DefaultBotProperties();
    self::assertNull($d->get('parse_mode'));
    self::assertNull($d->get('link_preview'));
  }

  /** Upstream: test_post_init_auto_fill_link_preview — flat flags synthesise LinkPreviewOptions. */
  public function testAutoFillLinkPreviewFromFlatFlags(): void
  {
    $d = new DefaultBotProperties(
      linkPreviewIsDisabled: true,
      linkPreviewPreferSmallMedia: true,
      linkPreviewPreferLargeMedia: true,
      linkPreviewShowAboveText: true,
    );
    $lp = $d->get('link_preview');
    self::assertInstanceOf(LinkPreviewOptions::class, $lp);
    self::assertTrue($lp->isDisabled);
    self::assertTrue($lp->preferSmallMedia);
    self::assertTrue($lp->preferLargeMedia);
    self::assertTrue($lp->showAboveText);
  }

  /** Upstream: test_getitem — __get / offsetGet returns individual properties. */
  public function testGetReturnsValue(): void
  {
    $d = new DefaultBotProperties(parseMode: 'HTML');
    self::assertSame('HTML', $d->get('parse_mode'));
    self::assertSame('HTML', $d['parse_mode']);
  }

  /** Upstream: test_getitem — enum parse_mode stored as string, flat flags accessible. */
  public function testGetItemWithEnumParseMode(): void
  {
    $d = new DefaultBotProperties(
      parseMode: ParseMode::Html->value,
      linkPreviewIsDisabled: true,
      linkPreviewPreferSmallMedia: true,
      linkPreviewPreferLargeMedia: true,
      linkPreviewShowAboveText: true,
    );
    self::assertSame(ParseMode::Html->value, $d['parse_mode']);
    self::assertTrue($d['link_preview_is_disabled']);
    self::assertTrue($d['link_preview_prefer_small_media']);
    self::assertTrue($d['link_preview_prefer_large_media']);
    self::assertTrue($d['link_preview_show_above_text']);
  }

  public function testLinkPreviewAggregation(): void
  {
    $d = new DefaultBotProperties(linkPreviewIsDisabled: true);
    $lp = $d->get('link_preview');
    self::assertInstanceOf(LinkPreviewOptions::class, $lp);
    self::assertTrue($lp->isDisabled);
  }

  public function testGetReturnsNullForUnknownKey(): void
  {
    // The `match (..) default => null` arm in `get()` covers
    // keys outside the closed set — defensive but reachable when callers
    // misspell or probe optional features.
    $d = new DefaultBotProperties(parseMode: 'HTML');

    self::assertNull($d->get('totally_unknown_key'));
  }

  public function testOffsetExistsReportsPresenceForKnownAndUnknownKeys(): void
  {
    // ArrayAccess::offsetExists must return `true` only for string keys
    // whose `get()` lookup is non-null. Exercises both branches of the
    // `is_string && get !== null` guard.
    $d = new DefaultBotProperties(parseMode: 'HTML');

    self::assertTrue(isset($d['parse_mode']));
    self::assertFalse(isset($d['disable_notification']));
    self::assertFalse(isset($d[0]));
  }

  public function testOffsetGetReturnsNullForNonStringOffset(): void
  {
    // Defensive: a non-string offset (e.g. `$d[0]`) returns null without
    // throwing — mirrors PHP's loose `ArrayAccess` semantics for ints.
    $d = new DefaultBotProperties(parseMode: 'HTML');

    self::assertNull($d[0]);
  }

  public function testOffsetSetThrowsLogicExceptionDueToImmutability(): void
  {
    // `DefaultBotProperties` is read-only after construction; writes must
    // fail loudly so callers don't silently lose state.
    $d = new DefaultBotProperties();

    $this->expectException(\LogicException::class);
    $d['parse_mode'] = 'HTML';
  }

  public function testOffsetUnsetThrowsLogicExceptionDueToImmutability(): void
  {
    // Symmetric to `offsetSet` — deleting a property is rejected.
    $d = new DefaultBotProperties(parseMode: 'HTML');

    $this->expectException(\LogicException::class);
    unset($d['parse_mode']);
  }
}
