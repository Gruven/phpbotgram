<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\Text;

use Generator;
use Gruven\PhpBotGram\Enums\MessageEntityType;
use Gruven\PhpBotGram\Types\MessageEntity;

/**
 * Abstract base for text decoration strategies (HTML, Markdown V2).
 *
 * Port of upstream `aiogram/utils/text_decorations.py` — `TextDecoration`.
 *
 * Telegram entity offsets/lengths are expressed in UTF-16 code units.
 * PHP strings are byte sequences, so we convert the input to a UTF-16LE
 * byte string and multiply all offsets/lengths by 2 to get byte offsets.
 * After slicing we convert back to UTF-8.
 */
abstract class TextDecoration
{
  abstract protected function bold(string $value): string;

  abstract protected function italic(string $value): string;

  abstract protected function underline(string $value): string;

  abstract protected function strikethrough(string $value): string;

  abstract protected function spoiler(string $value): string;

  abstract protected function blockquote(string $value): string;

  abstract protected function expandableBlockquote(string $value): string;

  abstract protected function code(string $value): string;

  abstract protected function pre(string $value): string;

  abstract protected function preLanguage(string $value, string $language): string;

  abstract protected function link(string $value, string $link): string;

  abstract protected function customEmoji(string $value, string $customEmojiId): string;

  abstract protected function dateTime(string $value, int $unixTime, ?string $dateTimeFormat): string;

  /**
   * Escape plain text for safe embedding in the target markup format.
   */
  abstract public function quote(string $value): string;

  /**
   * Apply the decoration corresponding to a single MessageEntity to $text.
   *
   * $text is already the inner (possibly recursively-decorated) substring.
   */
  final public function applyEntity(MessageEntity $entity, string $text): string
  {
    $type = MessageEntityType::from($entity->type);

    return match (true) {
      // Passthrough types — return text as-is (no escaping). Upstream parity.
      // Upstream `aiogram/utils/text_decorations.py:46-56` returns `text` unchanged
      // for these entity types; escaping corrupts URLs/usernames/hashtags in MD V2.
      $type === MessageEntityType::BotCommand,
      $type === MessageEntityType::Url,
      $type === MessageEntityType::Mention,
      $type === MessageEntityType::PhoneNumber,
      $type === MessageEntityType::Hashtag,
      $type === MessageEntityType::Cashtag,
      $type === MessageEntityType::Email => $text,

      $type === MessageEntityType::Bold => $this->bold($text),
      $type === MessageEntityType::Italic => $this->italic($text),
      $type === MessageEntityType::Underline => $this->underline($text),
      $type === MessageEntityType::Strikethrough => $this->strikethrough($text),
      $type === MessageEntityType::Spoiler => $this->spoiler($text),
      $type === MessageEntityType::Blockquote => $this->blockquote($text),
      $type === MessageEntityType::ExpandableBlockquote => $this->expandableBlockquote($text),
      $type === MessageEntityType::Code => $this->code($text),
      $type === MessageEntityType::Pre => $entity->language !== null
          ? $this->preLanguage($text, $entity->language)
          : $this->pre($text),
      $type === MessageEntityType::TextLink => $this->link($text, $entity->url ?? ''),
      $type === MessageEntityType::TextMention => $this->link(
        $text,
        'tg://user?id=' . ($entity->user !== null ? $entity->user->id : 0),
      ),
      $type === MessageEntityType::CustomEmoji => $this->customEmoji($text, $entity->customEmojiId ?? ''),
      $type === MessageEntityType::DateTime => $this->dateTime($text, $entity->unixTime ?? 0, $entity->dateTimeFormat),
      default => $this->quote($text),
    };
  }

  /**
   * Reconstruct a decorated string from $text and its entity list.
   *
   * Mirrors upstream `TextDecoration.unparse()`.
   *
   * @param null|list<MessageEntity> $entities
   */
  final public function unparse(string $text, ?array $entities = null): string
  {
    if ($entities === null || $entities === []) {
      return $this->quote($text);
    }

    $utf16Bytes = self::addSurrogates($text);
    $utf16Len = strlen($utf16Bytes) >> 1; // number of UTF-16 code units

    usort($entities, static fn(MessageEntity $a, MessageEntity $b): int => $a->offset <=> $b->offset);

    return implode('', iterator_to_array(
      $this->unparseEntities($utf16Bytes, $entities, 0, $utf16Len),
      false,
    ));
  }

  /**
   * Recursively walk entities within [$offset, $offset+$length) and yield
   * decorated text segments.
   *
   * @param list<MessageEntity> $entities
   *
   * @return Generator<int, string, mixed, void>
   */
  private function unparseEntities(
    string $utf16Bytes,
    array $entities,
    int $offset,
    int $length,
  ): Generator {
    $end = $offset + $length;
    $pos = $offset;

    foreach ($entities as $i => $entity) {
      if ($entity->offset >= $end) {
        break;
      }

      if ($entity->offset < $pos) {
        // Already consumed by a parent or previous entity; skip.
        continue;
      }

      // Yield undecorated gap before this entity.
      if ($entity->offset > $pos) {
        yield $this->quote(self::removeSurrogates(
          substr($utf16Bytes, $pos * 2, ($entity->offset - $pos) * 2),
        ));
      }

      $entityEnd = $entity->offset + $entity->length;
      $entityEnd = min($entityEnd, $end);

      // Collect nested entities (those fully inside this entity's range).
      $nested = [];

      foreach (array_slice($entities, $i + 1) as $candidate) {
        if ($candidate->offset >= $entityEnd) {
          break;
        }
        $nested[] = $candidate;
      }

      // Recursively decorate the inner text.
      $innerParts = iterator_to_array(
        $this->unparseEntities($utf16Bytes, $nested, $entity->offset, $entity->length),
        false,
      );
      $innerText = implode('', $innerParts);

      yield $this->applyEntity($entity, $innerText);

      $pos = $entityEnd;
    }

    // Yield trailing undecorated text after all entities.
    if ($pos < $end) {
      yield $this->quote(self::removeSurrogates(
        substr($utf16Bytes, $pos * 2, ($end - $pos) * 2),
      ));
    }
  }

  /**
   * Convert a UTF-8 string to a UTF-16LE byte string (add surrogates).
   */
  private static function addSurrogates(string $text): string
  {
    /** @var false|string $result */
    $result = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');

    return $result !== false ? $result : '';
  }

  /**
   * Convert a UTF-16LE byte string back to UTF-8 (remove surrogates).
   */
  private static function removeSurrogates(string $bytes): string
  {
    /** @var false|string $result */
    $result = mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');

    return $result !== false ? $result : '';
  }
}
