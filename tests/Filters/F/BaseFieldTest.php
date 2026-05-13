<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\BaseField;
use Gruven\PhpBotGram\Filters\F\StringField;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilterAsFilter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Coverage for `BaseField` — the hand-written runtime primitive that wraps
 * a `MagicFilter` chain and exposes type-narrow helpers via concrete
 * subclasses (`StringField`, `IntField`, `BoolField`, …).
 *
 * The base only owns the chain handle and a single `asFilter()` shortcut
 * for terminal use cases that don't need a typed comparator (existence
 * check, raw chain bridge). Subclass-specific behaviour is covered by the
 * sibling test files.
 */
final class BaseFieldTest extends TestCase
{
  public function testBaseFieldIsAbstract(): void
  {
    // The base only exists to be extended by typed subclasses — directly
    // instantiating it would leak the un-narrowed chain into user code.
    $reflection = new ReflectionClass(BaseField::class);

    self::assertTrue($reflection->isAbstract(), 'BaseField must be abstract.');
  }

  public function testChainHandleIsPubliclyReadable(): void
  {
    // The wrapped chain is exposed as a `public readonly` property so
    // codegen and tests can introspect / extend it without going through
    // an accessor. The instance must accept any `MagicFilter` and surface
    // the same reference back.
    $chain = MagicFilter::root()->id;
    $field = new StringField($chain);

    self::assertSame($chain, $field->chain);
  }

  public function testAsFilterReturnsMagicFilterAsFilterBridge(): void
  {
    // `BaseField::asFilter()` is the terminal escape hatch: it wraps the
    // chain as a dispatcher-consumable `Filter` without forcing the user
    // through a typed comparator. The bridge instance is the same one
    // `MagicFilter::asFilter()` produces, so we only need to verify the
    // resulting class — the bridge behaviour itself is covered in
    // `MagicFilterAsFilterTest`.
    $field = new StringField(MagicFilter::root()->id);
    $filter = $field->asFilter();

    self::assertInstanceOf(Filter::class, $filter);
    self::assertInstanceOf(MagicFilterAsFilter::class, $filter);
  }

  public function testAsFilterResolvesAgainstEvent(): void
  {
    // End-to-end sanity: the bridge produced by `asFilter()` actually
    // walks the wrapped chain against an event. `F->id` against a Chat
    // with id=42 surfaces a truthy int, so the bridge accepts.
    $field = new StringField(MagicFilter::root()->id);
    $filter = $field->asFilter();

    self::assertTrue($filter(new Chat(id: 42, type: 'private')));
  }
}
