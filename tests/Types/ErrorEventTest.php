<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

use Error;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\ErrorEvent;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\Update;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/**
 * Ports `aiogram/types/error_event.py::ErrorEvent`.
 *
 * The dispatcher synthesises an `ErrorEvent` whenever a handler throws an
 * exception that isn't a `SkipHandler` / `CancelHandler` signalling marker.
 * The event carries the original `Update` so the `errors` observer can route
 * by update shape and the offending `Throwable` so the observer can inspect /
 * log it.
 *
 * Unlike the wire-shaped Telegram types, `ErrorEvent` is **never** emitted by
 * the codegen — it lives in `Types/` purely to mirror upstream's module
 * layout, and `FileEmitter::PROTECTED_PATHS` keeps `make regenerate` from
 * clobbering it.
 *
 * @internal
 *
 * @coversNothing
 */
final class ErrorEventTest extends TestCase
{
  public function testConstructionExposesUpdateAndException(): void
  {
    $chat = new Chat(id: 1, type: 'private');
    $message = new Message(messageId: 1, date: new DateTime('@0'), chat: $chat);
    $update = new Update(updateId: 42, message: $message);
    $exception = new RuntimeException('boom');

    $event = new ErrorEvent($update, $exception);

    self::assertSame($update, $event->update);
    self::assertSame($exception, $event->exception);
  }

  public function testAcceptsAnyThrowable(): void
  {
    // Upstream's type hint is `Exception`, but Throwable is broader and
    // safer for PHP — Errors (e.g. TypeError) thrown by a handler must
    // also flow through the errors observer.
    $update = new Update(updateId: 1);

    $throwable = new class ('explosion') extends Error {};

    $event = new ErrorEvent($update, $throwable);

    self::assertSame($throwable, $event->exception);
    self::assertInstanceOf(Throwable::class, $event->exception);
  }

  public function testPropertiesAreReadonly(): void
  {
    $reflection = new ReflectionClass(ErrorEvent::class);
    self::assertTrue(
      $reflection->isReadOnly(),
      'ErrorEvent must be a readonly class so update/exception are immutable post-construction.',
    );

    foreach (['update', 'exception'] as $name) {
      $prop = $reflection->getProperty($name);
      self::assertTrue(
        $prop->isReadOnly(),
        "Property {$name} must be readonly.",
      );
    }
  }

  public function testClassIsFinal(): void
  {
    $reflection = new ReflectionClass(ErrorEvent::class);
    self::assertTrue($reflection->isFinal(), 'ErrorEvent must be final to match the value-object shape.');
  }

  public function testPropertiesArePublic(): void
  {
    $reflection = new ReflectionClass(ErrorEvent::class);

    foreach (['update', 'exception'] as $name) {
      $prop = $reflection->getProperty($name);
      self::assertTrue(
        ($prop->getModifiers() & ReflectionProperty::IS_PUBLIC) !== 0,
        "Property {$name} must be public (value-object surface).",
      );
    }
  }
}
