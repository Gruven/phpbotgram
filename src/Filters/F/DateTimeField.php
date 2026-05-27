<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters\F;

use DateTimeInterface;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Filters\Logic\AndFilter;

/**
 * Typed F-DSL wrapper for `\DateTime`-valued Telegram fields
 * (`Message::$date`, `Message::$editDate`, …). The temporal helpers
 * delegate to MagicFilter's numeric comparators, which use PHP's native
 * `<`/`>`-on-DateTime semantics — DateTime instances compare by their
 * canonical timestamp.
 *
 * Mirrors the design spec § "Magic-filter runtime + F-DSL" DateTimeField
 * surface.
 */
final class DateTimeField extends BaseField
{
  /** Accept when the field's value is strictly before `$when`. */
  public function before(DateTimeInterface $when): Filter
  {
    return $this->chain->lt($when)->asFilter();
  }

  /** Accept when the field's value is strictly after `$when`. */
  public function after(DateTimeInterface $when): Filter
  {
    return $this->chain->gt($when)->asFilter();
  }

  /**
   * Inclusive temporal range: accept when `$from <= $value <= $to`.
   * Composes gte+lte under an `AndFilter` — same pattern as
   * `IntField::between`.
   */
  public function between(DateTimeInterface $from, DateTimeInterface $to): Filter
  {
    return new AndFilter(
      $this->chain->gte($from)->asFilter(),
      $this->chain->lte($to)->asFilter(),
    );
  }
}
