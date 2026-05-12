<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use InvalidArgumentException;
use LogicException;

/**
 * Stage 3 of the codegen pipeline.
 *
 * Bridges Telegram's wire-naming convention (snake_case property names,
 * camelCase method names, PascalCase type/enum names) to PHP-side
 * identifiers, applying the rename policy that escapes PHP-reserved
 * keywords.
 *
 * The mapper is stateless: every transformation is a pure function of its
 * input plus the rename table and the reserved-keyword set. The single
 * known forward rename today is `from -> fromUser` (PHP forbids `from` as
 * a property name in normal contexts; aiogram's canonical Python port pins
 * the renamed accessor to `from_user`, and we mirror that choice).
 *
 * The mapper fails closed when it encounters a wire name that would map
 * onto a PHP-reserved keyword with no rename defined, rather than silently
 * emitting source that won't parse. New collisions surfaced by future
 * schema versions should be addressed by adding to `RENAMES` here.
 */
final class NameMapper
{
  /**
   * Forward rename table: wire snake_case name -> PHP camelCase identifier.
   *
   * Keyed by the lowercased wire name. The value is the final PHP identifier
   * (no further transformation is applied).
   */
  private const array RENAMES = [
    'from' => 'fromUser',
  ];

  /**
   * PHP reserved keywords that are illegal as property/method names.
   *
   * Sourced from the official PHP keyword list. Built-in type names
   * (`int`, `string`, `bool`, `float`, `array`, `object`, `iterable`,
   * `mixed`, `void`, `never`, `null`, `false`, `true`) are NOT included
   * here — they are type names, not keywords, and PHP allows them as
   * property names.
   */
  private const array RESERVED = [
    'abstract' => true,
    'and' => true,
    'array' => true,
    'as' => true,
    'break' => true,
    'callable' => true,
    'case' => true,
    'catch' => true,
    'class' => true,
    'clone' => true,
    'const' => true,
    'continue' => true,
    'declare' => true,
    'default' => true,
    'die' => true,
    'do' => true,
    'echo' => true,
    'else' => true,
    'elseif' => true,
    'empty' => true,
    'enddeclare' => true,
    'endfor' => true,
    'endforeach' => true,
    'endif' => true,
    'endswitch' => true,
    'endwhile' => true,
    'enum' => true,
    'eval' => true,
    'exit' => true,
    'extends' => true,
    'final' => true,
    'finally' => true,
    'fn' => true,
    'for' => true,
    'foreach' => true,
    'from' => true,
    'function' => true,
    'global' => true,
    'goto' => true,
    'if' => true,
    'implements' => true,
    'include' => true,
    'include_once' => true,
    'instanceof' => true,
    'insteadof' => true,
    'interface' => true,
    'isset' => true,
    'list' => true,
    'match' => true,
    'namespace' => true,
    'new' => true,
    'or' => true,
    'print' => true,
    'private' => true,
    'protected' => true,
    'public' => true,
    'readonly' => true,
    'require' => true,
    'require_once' => true,
    'return' => true,
    'static' => true,
    'switch' => true,
    'throw' => true,
    'trait' => true,
    'try' => true,
    'unset' => true,
    'use' => true,
    'var' => true,
    'while' => true,
    'xor' => true,
    'yield' => true,
  ];

  /**
   * Map a wire snake_case property name to a PHP camelCase property name.
   *
   * Applies the forward rename table for names that collide with PHP
   * reserved keywords. Throws when the input would land on a reserved
   * keyword that has no rename entry — never silently emit broken PHP.
   *
   * @throws InvalidArgumentException when `$wireName` is empty
   * @throws LogicException when the camelCased result is a PHP reserved
   *                        keyword and no rename is defined
   */
  public function property(string $wireName): string
  {
    if ($wireName === '') {
      throw new InvalidArgumentException('Wire property name must not be empty');
    }

    // Strip rare-but-legal trailing underscores before transforming.
    $normalised = rtrim($wireName, '_');

    if ($normalised === '') {
      throw new InvalidArgumentException('Wire property name must not be empty after stripping underscores');
    }

    if (isset(self::RENAMES[$normalised])) {
      return self::RENAMES[$normalised];
    }

    $php = $this->snakeToCamel($normalised);

    if (isset(self::RESERVED[$php])) {
      throw new LogicException("Reserved keyword '{$php}' has no rename defined");
    }

    return $php;
  }

  /**
   * Pass-through for camelCase wire method names. Throws on reserved-keyword
   * collisions so a future schema change cannot quietly emit broken source.
   *
   * @throws InvalidArgumentException when `$wireName` is empty
   * @throws LogicException when `$wireName` is a PHP reserved keyword
   */
  public function method(string $wireName): string
  {
    if ($wireName === '') {
      throw new InvalidArgumentException('Wire method name must not be empty');
    }

    if (isset(self::RESERVED[$wireName])) {
      throw new LogicException("Reserved keyword '{$wireName}' has no rename defined");
    }

    return $wireName;
  }

  /**
   * Pass-through for PascalCase wire type/enum names.
   *
   * @throws InvalidArgumentException when `$wireName` is empty
   */
  public function type(string $wireName): string
  {
    if ($wireName === '') {
      throw new InvalidArgumentException('Wire type name must not be empty');
    }

    return $wireName;
  }

  /**
   * Inverse mapping — given a PHP camelCase property name, return the wire
   * snake_case name. Honours the rename table in reverse so the canonical
   * `fromUser -> from` round-trip works.
   *
   * Used by the renderer when emitting per-class `WireNames` constants
   * (so the serializer can convert the PHP-side property back to the wire
   * name without re-running the mapper at runtime).
   *
   * @throws InvalidArgumentException when `$phpName` is empty
   */
  public function wirePropertyName(string $phpName): string
  {
    if ($phpName === '') {
      throw new InvalidArgumentException('PHP property name must not be empty');
    }

    $inverse = array_flip(self::RENAMES);

    if (isset($inverse[$phpName])) {
      return $inverse[$phpName];
    }

    return $this->camelToSnake($phpName);
  }

  /**
   * Convert a snake_case (or already-camelCase) token to camelCase.
   *
   * Splits on `_`, lowercases the first segment, capitalizes the first
   * character of subsequent segments. Numbers stay attached to whichever
   * segment they appear in (`thumb_url_64` -> `thumbUrl64`).
   */
  private function snakeToCamel(string $input): string
  {
    if (!str_contains($input, '_')) {
      // Already camelCase / single-word: pass through unchanged so we don't
      // mangle the defensive nested-Union member case.
      return $input;
    }

    $parts = explode('_', $input);

    /** @var list<string> $nonEmpty */
    $nonEmpty = [];

    foreach ($parts as $p) {
      if ($p !== '') {
        $nonEmpty[] = $p;
      }
    }

    if ($nonEmpty === []) {
      return '';
    }

    $first = strtolower($nonEmpty[0]);
    $rest = '';

    for ($i = 1, $n = \count($nonEmpty); $i < $n; ++$i) {
      $segment = strtolower($nonEmpty[$i]);
      $rest .= ucfirst($segment);
    }

    return $first . $rest;
  }

  /**
   * Convert a camelCase token to snake_case. Inserts `_` before each capital
   * letter (except at position 0), then lowercases the whole string.
   * Numbers stay attached to the segment they appear in.
   */
  private function camelToSnake(string $input): string
  {
    if ($input === '') {
      return '';
    }

    $out = preg_replace('/(?<!^)([A-Z])/', '_$1', $input);

    if ($out === null) {
      return strtolower($input);
    }

    return strtolower($out);
  }
}
