<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter\Operation;

/**
 * `CombinationOperation` flavour that bypasses the resolver's reject
 * short-circuit. Used by OR composition so a left-hand rejection (missing
 * attribute, failed cast) doesn't blank the verdict before the right-hand
 * alternative gets to vote.
 *
 * Mirrors upstream `magic_filter.operations.combination.ImportantCombinationOperation`
 * (`magic_filter/operations/combination.py:21-22`).
 */
final class ImportantCombinationOperation extends CombinationOperation
{
  public function important(): bool
  {
    return true;
  }
}
