<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Methods\SendPoll;
use Gruven\PhpBotGram\Tests\Support\MockedBot;
use Gruven\PhpBotGram\Types\InputMedia;
use Gruven\PhpBotGram\Types\InputMediaAudio;
use Gruven\PhpBotGram\Types\InputMediaLocation;
use Gruven\PhpBotGram\Types\InputMediaPhoto;
use Gruven\PhpBotGram\Types\InputMediaSticker;
use Gruven\PhpBotGram\Types\InputPollMediaInterface;
use Gruven\PhpBotGram\Types\InputPollOption;
use Gruven\PhpBotGram\Types\InputPollOptionMediaInterface;
use Gruven\PhpBotGram\Types\InputPollOptionMediaUnion;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Cycle 3 review fix: a child of multiple union parents must implement
 * every parent's marker interface so that PHP's single-inheritance
 * doesn't lose union-membership beyond the canonical `extends` parent.
 *
 * Concrete cases in the vendored 10.0 schema:
 *   - `InputMediaPhoto` belongs to `InputMedia` ∪ `InputPollMedia` ∪
 *     `InputPollOptionMedia`. PHP can only `extends InputMedia` (the
 *     canonical parent by name-prefix); the other two memberships are
 *     declared via `implements InputPollMediaInterface,
 *     InputPollOptionMediaInterface`.
 *   - `InputMediaLocation` belongs to `InputPollMedia` ∪
 *     `InputPollOptionMedia`. Extends `InputPollMedia`; implements
 *     `InputPollOptionMediaInterface`.
 *   - `InputMediaAudio` belongs to `InputMedia` ∪ `InputPollMedia`.
 *     Extends `InputMedia`; implements `InputPollMediaInterface`.
 *   - `InputMediaSticker` belongs to `InputPollOptionMedia` only —
 *     the interface comes via the abstract `InputPollOptionMedia`
 *     parent's own `implements` declaration.
 *
 * The downstream effect: every property typed `?InputPollOptionMedia`,
 * `?InputPollMedia`, `?InputMedia` must instead be typed as the
 * matching `*Interface` so all union members are accepted at
 * construction time.
 *
 * @internal
 *
 * @coversNothing
 */
final class MultiParentUnionTest extends TestCase
{
  /**
   * `InputMediaPhoto`'s canonical PHP parent is `InputMedia` (longest
   * name-prefix); the other two memberships flow through the marker
   * interfaces. `InputMedia` itself has no shadow members so it gets no
   * interface — membership is satisfied by the `extends InputMedia` chain.
   */
  public function testInputMediaPhotoImplementsBothShadowUnionInterfaces(): void
  {
    $impl = class_implements(InputMediaPhoto::class);
    self::assertIsArray($impl);

    self::assertContains(InputPollMediaInterface::class, $impl);
    self::assertContains(InputPollOptionMediaInterface::class, $impl);

    // The canonical-parent chain still works: extends InputMedia.
    self::assertInstanceOf(InputMedia::class, new InputMediaPhoto(media: 'p://x'));
  }

  public function testInputMediaLocationImplementsBothShadowUnionInterfaces(): void
  {
    $impl = class_implements(InputMediaLocation::class);
    self::assertIsArray($impl);

    // Canonical parent is `InputPollMedia`; the additional membership is
    // `InputPollOptionMedia`. Both are shadow unions, so both surface as
    // marker interfaces.
    self::assertContains(InputPollMediaInterface::class, $impl);
    self::assertContains(InputPollOptionMediaInterface::class, $impl);
  }

  public function testInputMediaAudioImplementsShadowUnionInterface(): void
  {
    $impl = class_implements(InputMediaAudio::class);
    self::assertIsArray($impl);

    // Canonical parent is `InputMedia` (no interface since no shadow
    // members); the additional membership is `InputPollMedia` (shadow).
    self::assertContains(InputPollMediaInterface::class, $impl);
    self::assertInstanceOf(InputMedia::class, new InputMediaAudio(media: 'a://x'));
  }

  /**
   * Single-parent union members get their interface via the abstract
   * class's own `implements` declaration — the renderer never needs
   * to re-declare it on the child.
   */
  public function testInputMediaStickerImplementsItsSoleUnionInterface(): void
  {
    $impl = class_implements(InputMediaSticker::class);
    self::assertIsArray($impl);

    self::assertContains(InputPollOptionMediaInterface::class, $impl);
  }

  /**
   * `InputPollOption::$media` is typed `?InputPollOptionMediaInterface`
   * so every member of the InputPollOptionMedia union (including the
   * shadow members `InputMediaPhoto`/`InputMediaAnimation`/… whose PHP
   * `extends` parent is `InputMedia`) can be passed without TypeError.
   */
  public function testInputPollOptionMediaPropertyTypedAsInterface(): void
  {
    $prop = new ReflectionProperty(InputPollOption::class, 'media');
    $type = $prop->getType();
    self::assertInstanceOf(ReflectionNamedType::class, $type);
    self::assertTrue($type->allowsNull());
    self::assertSame(InputPollOptionMediaInterface::class, $type->getName());
  }

  /**
   * `SendPoll::$media` and `SendPoll::$explanationMedia` are typed
   * `?InputPollMediaInterface`. Without the interface widening,
   * `new SendPoll(..., media: new InputMediaAudio(...))` would fail
   * with TypeError because `InputMediaAudio` extends `InputMedia`
   * (canonical) not `InputPollMedia`.
   */
  public function testSendPollMediaPropertiesTypedAsInterface(): void
  {
    foreach (['media', 'explanationMedia'] as $name) {
      $prop = new ReflectionProperty(SendPoll::class, $name);
      $type = $prop->getType();
      self::assertInstanceOf(ReflectionNamedType::class, $type, "{$name}: must be a single nullable type");
      self::assertTrue($type->allowsNull(), "{$name}: must allow null");
      self::assertSame(InputPollMediaInterface::class, $type->getName(), "{$name}: declared type must be InputPollMediaInterface");
    }
  }

  /**
   * Construction smoke test: `InputPollOption` accepts an `InputMediaPhoto`
   * for its `media` parameter without TypeError.
   */
  public function testInputPollOptionAcceptsInputMediaPhoto(): void
  {
    $option = new InputPollOption(
      text: 'q',
      media: new InputMediaPhoto(media: 'https://example.com/p.jpg'),
    );

    self::assertInstanceOf(InputMediaPhoto::class, $option->media);
  }

  /**
   * Construction smoke test: `SendPoll` accepts an `InputMediaAudio` for
   * its `media` parameter without TypeError.
   */
  public function testSendPollAcceptsInputMediaAudio(): void
  {
    $send = new SendPoll(
      chatId: 1,
      question: 'q',
      options: [new InputPollOption(text: 'a')],
      media: new InputMediaAudio(media: 'audio://x'),
    );

    self::assertInstanceOf(InputMediaAudio::class, $send->media);
  }

  /**
   * `InputPollOptionMediaUnion::resolve()` returns the interface
   * (`InputPollOptionMediaInterface`) so callers receive a value that
   * statically admits every union member, including the multi-parent
   * ones whose `extends` chain points elsewhere.
   */
  public function testInputPollOptionMediaUnionResolveReturnsInterface(): void
  {
    $r = new ReflectionMethod(InputPollOptionMediaUnion::class, 'resolve');
    $rt = $r->getReturnType();
    self::assertInstanceOf(ReflectionNamedType::class, $rt);
    self::assertSame(InputPollOptionMediaInterface::class, $rt->getName());
  }

  /**
   * `InputPollOptionMediaUnion::resolve()` against a `photo`-discriminated
   * wire payload produces an `InputMediaPhoto`, statically typed against
   * the interface — `Serializer::load` flows transparently through the
   * union helper because the interface is what the union resolver returns.
   */
  public function testInputPollOptionMediaUnionResolvesPhotoPayload(): void
  {
    $bot = new MockedBot();
    $resolved = InputPollOptionMediaUnion::resolve(
      ['type' => 'photo', 'media' => 'https://example.com/p.jpg'],
      $bot,
    );

    self::assertInstanceOf(InputPollOptionMediaInterface::class, $resolved);
    self::assertInstanceOf(InputMediaPhoto::class, $resolved);
  }

  /**
   * The Serializer round-trip preserves the multi-parent interface
   * membership: dumping an `InputMediaPhoto` produces the wire shape;
   * loading it back through `InputPollOptionMediaUnion::resolve()`
   * reconstructs an instance that satisfies every union interface.
   */
  public function testSerializerRoundTripsMultiParentMember(): void
  {
    $photo = new InputMediaPhoto(media: 'https://example.com/p.jpg');
    $dumped = Serializer::dump($photo);
    self::assertSame('photo', $dumped['type']);
    self::assertSame('https://example.com/p.jpg', $dumped['media']);

    $bot = new MockedBot();
    $reloaded = InputPollOptionMediaUnion::resolve($dumped, $bot);

    self::assertInstanceOf(InputMediaPhoto::class, $reloaded);
    self::assertInstanceOf(InputPollOptionMediaInterface::class, $reloaded);
    self::assertInstanceOf(InputPollMediaInterface::class, $reloaded);
    self::assertInstanceOf(InputMedia::class, $reloaded);
  }
}
