<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\Text;

/**
 * Markdown V2 decoration strategy.
 *
 * Port of upstream `aiogram/utils/text_decorations.py` — `MarkdownDecoration`.
 *
 * Plain text is escaped via `quote()` which escapes all Markdown V2 special
 * characters: `_*[]()~` >#+-=|{}.!`
 * Each entity type applies the corresponding Markdown V2 syntax.
 */
class MarkdownDecoration extends TextDecoration
{
  /**
   * All characters that must be escaped in Markdown V2 plain text.
   */
  private const SPECIAL_CHARS = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

  private static ?self $instance = null;

  public static function instance(): self
  {
    return self::$instance ??= new self();
  }

  public function quote(string $value): string
  {
    return str_replace(
      self::SPECIAL_CHARS,
      array_map(static fn(string $c): string => '\\' . $c, self::SPECIAL_CHARS),
      $value,
    );
  }

  protected function bold(string $value): string
  {
    return "*{$value}*";
  }

  protected function italic(string $value): string
  {
    // The \r separator prevents adjacent italic runs from merging.
    return "_\r{$value}_\r";
  }

  protected function underline(string $value): string
  {
    return "__\r{$value}__\r";
  }

  protected function strikethrough(string $value): string
  {
    return "~{$value}~";
  }

  protected function spoiler(string $value): string
  {
    return "||{$value}||";
  }

  protected function blockquote(string $value): string
  {
    // Use PCRE \R to match all universal newline sequences (\n, \r\n, \r, etc.)
    // — mirrors Python's str.splitlines() used in the upstream implementation.
    $lines = preg_split('/\R/u', $value) ?: [$value];
    $quoted = array_map(static fn(string $line): string => '>' . $line, $lines);

    return implode("\n", $quoted);
  }

  protected function expandableBlockquote(string $value): string
  {
    // Use PCRE \R to match all universal newline sequences (\n, \r\n, \r, etc.)
    // — mirrors Python's str.splitlines() used in the upstream implementation.
    $lines = preg_split('/\R/u', $value) ?: [$value];
    $quoted = array_map(static fn(string $line): string => '>' . $line, $lines);
    $quoted[] = '**';

    return implode("\n", $quoted);
  }

  protected function code(string $value): string
  {
    return "`{$value}`";
  }

  protected function pre(string $value): string
  {
    return "```\n{$value}\n```";
  }

  protected function preLanguage(string $value, string $language): string
  {
    return "```{$language}\n{$value}\n```";
  }

  protected function link(string $value, string $link): string
  {
    return "[{$value}]({$link})";
  }

  protected function customEmoji(string $value, string $customEmojiId): string
  {
    return "![{$value}](tg://emoji?id={$customEmojiId})";
  }

  protected function dateTime(string $value, int $unixTime, ?string $dateTimeFormat): string
  {
    $format = $dateTimeFormat !== null ? "&format={$dateTimeFormat}" : '';

    return "[{$value}](tg://datetime?unix_time={$unixTime}{$format})";
  }
}
