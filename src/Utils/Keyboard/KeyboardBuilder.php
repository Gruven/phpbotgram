<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\Keyboard;

use Generator;
use Gruven\PhpBotGram\Types\InlineKeyboardMarkup;
use Gruven\PhpBotGram\Types\ReplyKeyboardMarkup;
use InvalidArgumentException;

/**
 * Abstract builder that manages a two-dimensional button grid.
 *
 * Port of upstream `aiogram/utils/keyboard.py` — `KeyboardBuilder` class.
 *
 * The concrete subclasses bind `T` via a `@extends` PHPDoc tag on the subclass
 * itself (e.g. `@extends KeyboardBuilder<InlineKeyboardButton>`).
 *
 * @template T of object
 */
abstract class KeyboardBuilder
{
  /**
   * Maximum number of buttons per row. 0 means no row-width limit.
   */
  public const int MAX_WIDTH = 0;

  /**
   * Minimum acceptable row width for `adjust()` size validation.
   */
  public const int MIN_WIDTH = 1;

  /**
   * Maximum total number of buttons. 0 means no cap.
   */
  public const int MAX_BUTTONS = 0;

  /**
   * Internal two-dimensional markup storage.
   *
   * @var list<list<T>>
   */
  protected array $markup = [];

  /**
   * @param class-string<T> $buttonType
   * @param null|list<list<T>> $markup Pre-existing rows (deep-cloned on write).
   */
  public function __construct(
    protected readonly string $buttonType,
    ?array $markup = null,
  ) {
    if ($markup !== null) {
      $this->validateMarkup($markup);
      $this->markup = $markup;
    }
  }

  // -------------------------------------------------------------------------
  // Abstract surface
  // -------------------------------------------------------------------------

  /**
   * Wrap the internal markup in the concrete Markup DTO.
   *
   * Subclasses narrow this return type to the specific Markup they produce:
   * `InlineKeyboardMarkup` or `ReplyKeyboardMarkup`.
   *
   * @return InlineKeyboardMarkup|ReplyKeyboardMarkup
   */
  abstract public function asMarkup(): InlineKeyboardMarkup|ReplyKeyboardMarkup;

  // -------------------------------------------------------------------------
  // Public builder API
  // -------------------------------------------------------------------------

  /**
   * Flatten all buttons into a single iterator.
   *
   * @return Generator<int, T>
   */
  public function buttons(): Generator
  {
    foreach ($this->markup as $row) {
      yield from $row;
    }
  }

  /**
   * Return a deep copy of the internal markup.
   *
   * @return list<list<T>>
   */
  public function export(): array
  {
    $result = [];

    foreach ($this->markup as $row) {
      $clonedRow = [];

      foreach ($row as $button) {
        $clonedRow[] = clone $button;
      }

      $result[] = $clonedRow;
    }

    return $result;
  }

  /**
   * Flow buttons into rows. When `MAX_WIDTH` is 0, each call appends a new
   * row containing all the given buttons (no row-fill; matches upstream
   * `keyboard.py` behaviour for `max_width == 0`). When `MAX_WIDTH` is
   * positive, the last incomplete row is filled first, then remaining buttons
   * are chunked into full rows of that width.
   *
   * @param T ...$buttons
   *
   * @return static
   */
  public function add(object ...$buttons): static
  {
    foreach ($buttons as $button) {
      $this->validateButton($button);
    }

    if ($buttons === []) {
      return $this;
    }

    $maxWidth = static::MAX_WIDTH;

    if ($maxWidth === 0) {
      // No width constraint — append all buttons as a new row (mirrors
      // upstream `keyboard.py` where `max_width == 0` appends a new row
      // rather than filling the last one).
      $this->markup[] = array_values($buttons);

      return $this;
    }

    // Fill the last row first.
    $queue = array_values($buttons);

    if ($this->markup !== []) {
      $lastIdx = count($this->markup) - 1;
      $free = $maxWidth - count($this->markup[$lastIdx]);

      if ($free > 0) {
        $fill = array_splice($queue, 0, $free);

        foreach ($fill as $b) {
          $this->markup[$lastIdx][] = $b;
        }
      }
    }

    // Remaining buttons: chunk into full rows.
    while ($queue !== []) {
      $chunk = array_splice($queue, 0, $maxWidth);
      $this->markup[] = $chunk;
    }

    return $this;
  }

  /**
   * Append buttons as one or more new rows.
   *
   * When `$width` is `null`, the effective width defaults to `MAX_WIDTH`
   * (upstream `keyboard.py` parity: `width = self.max_width`). When
   * `MAX_WIDTH` is 0 and `$width` is `null`, all buttons go into a single
   * row (no width limit). An explicit `$width > 0` overrides the default
   * but is capped to `MAX_WIDTH` when that constant is positive.
   *
   * @param list<T> $buttons
   *
   * @return static
   */
  public function row(array $buttons, ?int $width = null): static
  {
    foreach ($buttons as $button) {
      $this->validateButton($button);
    }

    if ($buttons === []) {
      return $this;
    }

    $maxWidth = static::MAX_WIDTH;

    // Resolve effective width: null → MAX_WIDTH (upstream default).
    $effectiveWidth = $width ?? ($maxWidth > 0 ? $maxWidth : null);

    // Cap explicit width to MAX_WIDTH when positive.
    if ($effectiveWidth !== null && $maxWidth > 0 && $effectiveWidth > $maxWidth) {
      $effectiveWidth = $maxWidth;
    }

    if ($effectiveWidth === null || $effectiveWidth <= 0) {
      // No width limit — single row with all buttons.
      $this->markup[] = array_values($buttons);

      return $this;
    }

    $queue = array_values($buttons);

    while ($queue !== []) {
      $chunk = array_splice($queue, 0, $effectiveWidth);
      $this->markup[] = $chunk;
    }

    return $this;
  }

  /**
   * Reshape all buttons into rows of the given widths. The last size is
   * repeated for any remaining buttons (upstream `repeat=False` default).
   *
   * Pass no arguments to default to `[MAX_WIDTH]`.
   *
   * @return static
   */
  public function adjust(int ...$sizes): static
  {
    return $this->doAdjust(false, ...$sizes);
  }

  /**
   * Reshape all buttons into rows of the given widths, cycling through the
   * size list (upstream `repeat=True` variant).
   *
   * @return static
   */
  public function adjustRepeating(int ...$sizes): static
  {
    return $this->doAdjust(true, ...$sizes);
  }

  /**
   * Concatenate another builder's markup onto this one.
   *
   * Both builders must have the same `$buttonType` — a mismatch throws
   * `InvalidArgumentException` to mirror upstream's type validation.
   *
   * @param KeyboardBuilder<T> $other
   *
   * @return static
   *
   * @throws InvalidArgumentException When the button types do not match.
   */
  public function attach(KeyboardBuilder $other): static
  {
    if ($other->buttonType !== $this->buttonType) {
      throw new InvalidArgumentException(sprintf(
        'Cannot attach a %s builder to a %s builder',
        $other->buttonType,
        $this->buttonType,
      ));
    }

    foreach ($other->markup as $row) {
      $clonedRow = [];

      foreach ($row as $button) {
        $clonedRow[] = clone $button;
      }

      $this->markup[] = $clonedRow;
    }

    return $this;
  }

  // -------------------------------------------------------------------------
  // Validation helpers
  // -------------------------------------------------------------------------

  /**
   * Assert that a button is an instance of `$buttonType`.
   *
   * @param object $button
   *
   * @throws InvalidArgumentException
   */
  protected function validateButton(object $button): void
  {
    if (!$button instanceof $this->buttonType) {
      throw new InvalidArgumentException(sprintf(
        'Expected button of type %s, got %s',
        $this->buttonType,
        $button::class,
      ));
    }
  }

  /**
   * Assert that every row in a markup array is a list of valid buttons.
   *
   * @param list<list<T>> $markup
   *
   * @throws InvalidArgumentException
   */
  protected function validateMarkup(array $markup): void
  {
    foreach ($markup as $rowIndex => $row) {
      if (!is_array($row)) {
        throw new InvalidArgumentException(
          sprintf('Markup row %d must be an array, got %s', $rowIndex, get_debug_type($row)),
        );
      }

      foreach ($row as $button) {
        $this->validateButton($button);
      }
    }
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Shared implementation for `adjust()` and `adjustRepeating()`.
   *
   * @return static
   */
  private function doAdjust(bool $repeat, int ...$sizes): static
  {
    if ($sizes === []) {
      $maxWidth = static::MAX_WIDTH;
      $sizes = $maxWidth > 0 ? [$maxWidth] : [1];
    }

    $maxWidth = static::MAX_WIDTH;
    $minWidth = static::MIN_WIDTH;

    foreach ($sizes as $size) {
      if ($maxWidth > 0 && ($size < $minWidth || $size > $maxWidth)) {
        throw new InvalidArgumentException(sprintf(
          'Row size %d is out of range [%d, %d]',
          $size,
          $minWidth,
          $maxWidth,
        ));
      }
    }

    // Flatten all buttons.
    $all = [];

    foreach ($this->markup as $row) {
      foreach ($row as $button) {
        $all[] = $button;
      }
    }

    $this->markup = [];

    if ($all === []) {
      return $this;
    }

    $sizeCount = count($sizes);
    $sizeIdx = 0;
    $queue = $all;

    while ($queue !== []) {
      $currentSize = $sizes[$sizeIdx];
      $chunk = array_splice($queue, 0, $currentSize);
      $this->markup[] = $chunk;

      if ($repeat) {
        // Cycle through the sizes list.
        $sizeIdx = ($sizeIdx + 1) % $sizeCount;
      } elseif ($sizeIdx < $sizeCount - 1) {
        // Advance until the last size, then stick there.
        $sizeIdx++;
      }
    }

    return $this;
  }
}
