<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Filters\F;

use Gruven\PhpBotGram\Filters\F\RegexField;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\MagicFilter\MagicFilter;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for `RegexField` — typed wrapper that surfaces PCRE-pattern
 * matching over a string-valued chain. Direct port of the regex-filter
 * behaviour aiogram exposes via `F.text.regexp(...)`.
 *
 * @internal
 */
final class RegexFieldTest extends TestCase
{
  public function testMatchesAcceptsPatternHit(): void
  {
    // `matches('/^hello/')` accepts any string that starts with `'hello'`.
    // The PCRE delimiter convention follows MagicFilter's underlying
    // `regexp()` op — patterns are passed without delimiters.
    $filter = (new RegexField(MagicFilter::root()->firstName))
      ->matches('^hello');

    self::assertTrue($filter($this->user(firstName: 'hello world')));
    self::assertFalse($filter($this->user(firstName: 'goodbye world')));
  }

  public function testMatchesWithoutCaptureGroupsReturnsBoolVerdict(): void
  {
    // The default `captureGroups: false` mode returns a plain bool —
    // no kwarg injection into the dispatcher. Confirm the verdict is a
    // raw `true` (truthy chain result coerced to bool by the bridge).
    $filter = (new RegexField(MagicFilter::root()->firstName))
      ->matches('^hello');

    $verdict = $filter($this->user(firstName: 'hello world'));

    self::assertSame(true, $verdict);
  }

  public function testMatchesWithCaptureGroupsExposesMatchAndGroupsKwargs(): void
  {
    // With `captureGroups: true` the filter injects two kwargs into the
    // dispatcher: `regexp_match` (the full match) and `regexp_groups`
    // (the full preg_match output array including numbered captures).
    $filter = (new RegexField(MagicFilter::root()->firstName))
      ->matches('^(?<word>\w+) (\w+)$', captureGroups: true);

    $verdict = $filter($this->user(firstName: 'hello world'));

    self::assertIsArray($verdict);
    self::assertArrayHasKey('regexp_match', $verdict);
    self::assertArrayHasKey('regexp_groups', $verdict);
    self::assertSame('hello world', $verdict['regexp_match']);
    self::assertIsArray($verdict['regexp_groups']);
    self::assertContains('hello', $verdict['regexp_groups']);
    self::assertContains('world', $verdict['regexp_groups']);
  }

  public function testMatchesWithCaptureGroupsRejectsNonMatch(): void
  {
    // When the pattern doesn't match, the chain rejects — the bridge
    // surfaces `false` instead of a kwarg map.
    $filter = (new RegexField(MagicFilter::root()->firstName))
      ->matches('^(?<word>\w+) (\w+)$', captureGroups: true);

    self::assertFalse($filter($this->user(firstName: 'hello')));
  }

  private function user(string $firstName): User
  {
    return new User(id: 1, isBot: false, firstName: $firstName);
  }
}
