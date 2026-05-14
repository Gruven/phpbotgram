<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\Text;

/**
 * HTML decoration strategy.
 *
 * Port of upstream `aiogram/utils/text_decorations.py` — `HtmlDecoration`.
 *
 * Special characters in plain text are HTML-escaped via `quote()`.
 * Each entity type wraps its inner text in the appropriate HTML tag.
 */
class HtmlDecoration extends TextDecoration
{
  private static ?self $instance = null;

  public static function instance(): self
  {
    return self::$instance ??= new self();
  }

  public function quote(string $value): string
  {
    return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
  }

  protected function bold(string $value): string
  {
    return "<b>{$value}</b>";
  }

  protected function italic(string $value): string
  {
    return "<i>{$value}</i>";
  }

  protected function underline(string $value): string
  {
    return "<u>{$value}</u>";
  }

  protected function strikethrough(string $value): string
  {
    return "<s>{$value}</s>";
  }

  protected function spoiler(string $value): string
  {
    return "<tg-spoiler>{$value}</tg-spoiler>";
  }

  protected function blockquote(string $value): string
  {
    return "<blockquote>{$value}</blockquote>";
  }

  protected function expandableBlockquote(string $value): string
  {
    return "<blockquote expandable>{$value}</blockquote>";
  }

  protected function code(string $value): string
  {
    return "<code>{$value}</code>";
  }

  protected function pre(string $value): string
  {
    return "<pre>{$value}</pre>";
  }

  protected function preLanguage(string $value, string $language): string
  {
    return "<pre><code class=\"language-{$language}\">{$value}</code></pre>";
  }

  protected function link(string $value, string $link): string
  {
    return "<a href=\"{$link}\">{$value}</a>";
  }

  protected function customEmoji(string $value, string $customEmojiId): string
  {
    return "<tg-emoji emoji-id=\"{$customEmojiId}\">{$value}</tg-emoji>";
  }

  protected function dateTime(string $value, int $unixTime, ?string $dateTimeFormat): string
  {
    $format = $dateTimeFormat !== null ? " format=\"{$dateTimeFormat}\"" : '';

    return "<tg-datetime unix-time=\"{$unixTime}\"{$format}>{$value}</tg-datetime>";
  }
}
