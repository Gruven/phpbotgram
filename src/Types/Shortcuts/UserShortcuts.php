<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types\Shortcuts;

/**
 * Hand-authored shortcut helpers for `User`.
 *
 * Loaded by `HandAuthoredShortcutsIntegrator` (Phase 2 codegen stage 8)
 * and stitched into the regenerated `User` class via a
 * `use UserShortcuts;` directive.
 *
 * Mirrors aiogram's `User.full_name`, `User.mention_html()`, and
 * `User.mention_markdown()` accessors. The PHP-side helpers expose them
 * as ordinary methods (no `@property` magic) so PHPStan can resolve
 * them through the trait's signature.
 *
 * The `name` parameter on the mention helpers lets the caller override
 * the display label without losing the underlying `tg://user?id=…`
 * link — useful when surfacing a "mention this user as …" prompt in a
 * bot conversation. Pass `null` (the default) to fall back to the
 * user's `fullName()`.
 *
 * @property int $id promoted property on the using class
 * @property string $firstName promoted property on the using class
 * @property null|string $lastName promoted property on the using class
 */
trait UserShortcuts
{
  /**
   * The user's full display name: `firstName lastName` for a user with
   * both names, otherwise just `firstName`. Whitespace is normalised so
   * a missing `lastName` doesn't leak a trailing space.
   */
  public function fullName(): string
  {
    if ($this->lastName !== null && $this->lastName !== '') {
      return $this->firstName . ' ' . $this->lastName;
    }

    return $this->firstName;
  }

  /**
   * HTML-formatted user mention: an `<a href="tg://user?id=…">name</a>`
   * link Telegram clients render as a tappable mention.
   *
   * The display label is HTML-escaped with `htmlspecialchars` (default
   * ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5 flags) so a maliciously
   * crafted name can't break out of the `<a>` element. Pre-escape:
   * passing `Foo<Bar>` as the label produces `Foo&lt;Bar&gt;` inside
   * the anchor text.
   */
  public function mentionHtml(?string $name = null): string
  {
    $label = $name ?? $this->fullName();
    $escaped = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

    return \sprintf('<a href="tg://user?id=%d">%s</a>', $this->id, $escaped);
  }

  /**
   * Markdown-formatted user mention: `[name](tg://user?id=…)`.
   *
   * The label is passed through verbatim — Telegram's Markdown parser
   * tolerates a wider character set inside `[…]` than HTML does inside
   * `<a>`, and aiogram's upstream helper takes the same approach. A
   * caller passing untrusted input should escape the few reserved
   * Markdown characters (`[`, `]`, `\`) themselves.
   */
  public function mentionMarkdown(?string $name = null): string
  {
    $label = $name ?? $this->fullName();

    return \sprintf('[%s](tg://user?id=%d)', $label, $this->id);
  }
}
