<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\MagicFilter;

use Closure;
use Error;
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\ParamsConflict;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\RejectOperations;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAll;
use Gruven\PhpBotGram\Utils\MagicFilter\Exception\SwitchModeToAny;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\AsFilterResultOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\BaseOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\CallOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\CastOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\CombinationOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ComparatorOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ExtractOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\FunctionOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\GetAttributeOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\GetItemOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ImportantCombinationOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\ImportantFunctionOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\MethodCallOperation;
use Gruven\PhpBotGram\Utils\MagicFilter\Operation\SelectorOperation;
use Stringable;
use TypeError;

/**
 * Lazy, immutable chain of predicate operations that resolves against a
 * subject value when `resolve()` is called.
 *
 * Direct port of upstream `magic_filter.magic.MagicFilter`
 * (`magic_filter/magic.py:41-291`) plus aiogram's local `as_()` extension
 * from `aiogram/utils/magic_filter.py`.
 *
 * The class exposes three ways to extend a chain:
 *
 * 1. PHP `__get($name)` — `F->message->text` resolves to two
 *    `GetAttributeOperation`s on the chain.
 * 2. PHP `__call($name, $args)` — `F->message->text->casefold()` resolves
 *    to `[..., GetAttributeOperation('text'), GetAttributeOperation('casefold'),
 *    CallOperation([], [])]`. We add a `GetAttributeOperation` for the name
 *    followed by a `CallOperation` for the parentheses — same as Python's
 *    `__getattr__` then `__call__`.
 * 3. Named instance methods (`equals`, `gt`, `func`, `cast`, `regexp`,
 *    `in_`, `contains`, `as_`, …) — terminal-ish operations that append a
 *    purpose-built operation directly.
 *
 * Each operation-append returns a NEW `MagicFilter` instance — the chain
 * is immutable so `$f = F->text; $g = $f->lower();` doesn't mutate `$f`.
 *
 * Logical composition: PHP can't overload operators, so we expose method
 * forms — `$f->and_($g)` for AND, `$f->or_($g)` for OR, `$f->not()` for
 * negation. The static `Filter::all($f1, $f2)` and `Filter::any(…)` still
 * work for Filter-level composition; this layer is for MagicFilter-to-
 * MagicFilter composition mid-chain (the rules that ultimately get bridged
 * to a `Filter` via `asFilter()`).
 *
 * Resolution: `resolve($value)` walks the chain operation-by-operation.
 * Rejections raised by an operation (missing attribute, failed cast, type
 * mismatch) flip the resolver into a "skip-unless-important" state: the
 * value becomes `null` and only `important` operations (e.g. NOT, OR) run
 * until the chain ends or an important op rescues the verdict.
 *
 * @see /tmp/magic_filter/magic.py for the Python original
 */
final class MagicFilter
{
  /**
   * Sentinel for `F->items[MagicFilter::WILDCARD_ALL]` — fan-out + ALL
   * semantic. Matches Python's `F[:]` empty-slice case.
   */
  public const string WILDCARD_ALL = "\0magic_filter:wildcard_all\0";

  /**
   * Sentinel for `F->items[MagicFilter::WILDCARD_ANY]` — fan-out + ANY
   * semantic. Matches Python's `F[...]` Ellipsis case.
   */
  public const string WILDCARD_ANY = "\0magic_filter:wildcard_any\0";

  /** @var list<BaseOperation> */
  private array $operations;

  /**
   * Direct construction is private-by-convention: the only public seeds
   * are `MagicFilter::root()` (and the global `F` const). Users extend
   * the chain via `__get` / `__call` / named methods rather than building
   * a chain by hand.
   *
   * @param list<BaseOperation> $operations
   */
  public function __construct(array $operations = [])
  {
    $this->operations = $operations;
  }

  /**
   * Fresh, empty chain — the equivalent of upstream's bare `F`. Used by
   * typed-builder factories (`MessageF::text()` does
   * `new StringField(MagicFilter::root()->text)`).
   */
  public static function root(): self
  {
    return new self();
  }

  /**
   * Internal: append an operation and return a new instance. Never
   * mutates `$this`. Mirrors upstream `MagicFilter._extend` (`magic.py:63-64`).
   */
  private function extend(BaseOperation $operation): self
  {
    return new self([...$this->operations, $operation]);
  }

  /**
   * Internal: drop the trailing operation. Used by `__invert()` to fold
   * `~~F` back to `F` (matches upstream `magic.py:69-70`).
   */
  private function excludeLast(): self
  {
    $ops = $this->operations;
    array_pop($ops);

    return new self($ops);
  }

  /**
   * Walk the chain against a subject value and return the final value.
   * `null` propagates through non-important operations after a rejection.
   *
   * Mirrors upstream `MagicFilter.resolve` (`magic.py:96-97`) which
   * delegates to `_resolve`.
   */
  public function resolve(mixed $value): mixed
  {
    return $this->resolveInner($value, $this->operations);
  }

  /**
   * @param list<BaseOperation> $operations
   */
  private function resolveInner(mixed $initialValue, array $operations): mixed
  {
    $value = $initialValue;
    $rejected = false;
    $count = count($operations);

    for ($index = 0; $index < $count; $index++) {
      $operation = $operations[$index];

      if ($rejected && !$operation->important()) {
        continue;
      }

      try {
        $value = $operation->resolve($value, $initialValue);
      } catch (SwitchModeToAll) {
        // Iterate the remaining chain over each element of $value; accept
        // only if all elements accept. Matches upstream `magic.py:82-83`.
        if (!is_iterable($value)) {
          // Iterator already exhausted upstream — replicate "all over empty = true".
          return true;
        }
        $remaining = array_slice($operations, $index + 1);

        foreach ($value as $item) {
          if (!$this->resolveInner($item, $remaining)) {
            return false;
          }
        }

        return true;
      } catch (SwitchModeToAny) {
        if (!is_iterable($value)) {
          return false;
        }
        $remaining = array_slice($operations, $index + 1);

        foreach ($value as $item) {
          if ($this->resolveInner($item, $remaining)) {
            return true;
          }
        }

        return false;
      } catch (RejectOperations) {
        $rejected = true;
        $value = null;

        continue;
      }
      $rejected = false;
    }

    return $value;
  }

  // ===========================================================================
  // Fluent attribute / method-call access
  // ===========================================================================

  /**
   * Append a `GetAttributeOperation` for `$name`. Equivalent of Python's
   * `__getattr__` (`magic.py:99-103`); names starting with an underscore
   * are reserved for PHP internals and raise rather than become chain
   * operations.
   *
   * Both `$f->message` (magic dispatch) and `$f->attr('message')`
   * (explicit accessor) reach this code path — call sites that want
   * to keep PHPStan happy without bespoke property declarations should
   * prefer `attr()`.
   */
  public function __get(string $name): self
  {
    return $this->attr($name);
  }

  /**
   * Explicit accessor equivalent to `$this->{$name}` — append a
   * `GetAttributeOperation`. The magic-dispatch alias is the more
   * ergonomic form for hand-written DSL chains; this entry point is
   * for codegen and call sites that prefer static type safety.
   *
   * Upstream exposes this as `MagicFilter.attr_` (`magic.py:104`).
   */
  public function attr(string $name): self
  {
    if (str_starts_with($name, '_')) {
      throw new Error(self::class . " has no attribute '{$name}'.");
    }

    return $this->extend(new GetAttributeOperation($name));
  }

  /**
   * Honour `isset($magic->foo)` — without it PHP emits a warning when
   * `??` / null-checks probe a chain attribute. Always returns true so
   * `__get` runs and produces the chain operation.
   */
  public function __isset(string $name): bool
  {
    return true;
  }

  /**
   * Append a `MethodCallOperation` — PHP doesn't expose bound methods as
   * first-class values the way Python does, so we collapse the upstream
   * `__getattr__` then `__call__` pair into a single op that does
   * `$value->{$name}(...$args)` at resolve time.
   *
   * Mirrors upstream `magic_filter.magic.MagicFilter.__call__`
   * (`magic.py:141-142`) — except the pair of operations becomes one
   * MethodCallOperation here.
   *
   * @param array<int|string, mixed> $arguments
   */
  public function __call(string $name, array $arguments): self
  {
    [$args, $kwargs] = $this->splitArgs($arguments);

    return $this->extend(new MethodCallOperation($name, $args, $kwargs));
  }

  /**
   * Honour `MagicFilter::root()->foo` — without this `__getStatic` PHP
   * still works because `__get` is instance-side; this is documented for
   * completeness only.
   *
   * Split a flat `__call` argument bag into positional vs named pieces.
   * PHP gives us a numerically-indexed array for purely positional calls
   * and string-keyed entries for any named-argument call. We preserve
   * insertion order so `func($a, b: 1, $c)` is reassembled correctly.
   *
   * @param array<int|string, mixed> $arguments
   *
   * @return array{0: list<mixed>, 1: array<string, mixed>}
   */
  private function splitArgs(array $arguments): array
  {
    $args = [];
    $kwargs = [];

    foreach ($arguments as $key => $value) {
      if (is_int($key)) {
        $args[] = $value;
      } else {
        $kwargs[$key] = $value;
      }
    }

    return [$args, $kwargs];
  }

  // ===========================================================================
  // Subscript access
  // ===========================================================================

  /**
   * Append a `GetItemOperation`: `$f->item('key')` is the PHP analogue of
   * `F['key']` in Python (PHP doesn't have user-overloadable subscript
   * on instances outside `ArrayAccess`, and we don't want to commit
   * `MagicFilter` to `ArrayAccess` because the dispatcher distinguishes
   * iterables-vs-MagicFilter via instance checks).
   *
   * When `$key` is a `MagicFilter` we treat the call as the "select where
   * inner accepts" semantic — matches `F[F.text == 'hi']` upstream.
   */
  public function item(mixed $key): self
  {
    if ($key instanceof self) {
      return $this->extend(new SelectorOperation($key));
    }

    return $this->extend(new GetItemOperation($key));
  }

  /**
   * Fan-out + ALL: shorthand for `$f->item(MagicFilter::WILDCARD_ALL)`.
   * Equivalent of upstream `F[:]`. Use mid-chain to apply the rest of
   * the chain to every element of an iterable.
   */
  public function all(): self
  {
    return $this->item(self::WILDCARD_ALL);
  }

  /**
   * Fan-out + ANY: shorthand for `$f->item(MagicFilter::WILDCARD_ANY)`.
   * Equivalent of upstream `F[...]`.
   */
  public function any(): self
  {
    return $this->item(self::WILDCARD_ANY);
  }

  // ===========================================================================
  // Comparison operators (PHP can't overload, so each gets a named method)
  // ===========================================================================

  /** `F.text == 'hi'`. */
  public function equals(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a == $b,
    ));
  }

  /** Alias for `equals` so call sites can read more like Python `eq`. */
  public function eq(mixed $other): self
  {
    return $this->equals($other);
  }

  /** `F.text != 'hi'`. */
  public function notEquals(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a != $b,
    ));
  }

  public function ne(mixed $other): self
  {
    return $this->notEquals($other);
  }

  public function lt(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a < $b,
    ));
  }

  public function lte(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a <= $b,
    ));
  }

  public function gt(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a > $b,
    ));
  }

  public function gte(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a >= $b,
    ));
  }

  /**
   * Strict identity comparison (`===`). PHP equivalent of Python's `is`.
   * Not in upstream as a chain method (upstream uses `F.is_()` with the
   * underlying `operator.is_` callable) — added here for parity with
   * Python's `is` operator semantics.
   */
  public function is(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a === $b,
    ));
  }

  public function isNot(mixed $other): self
  {
    return $this->extend(new ComparatorOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => $a !== $b,
    ));
  }

  // ===========================================================================
  // Membership / containment
  // ===========================================================================

  /**
   * `F.status.in_({'admin', 'mod'})` → `F->status->in_(['admin', 'mod'])`.
   * Returns true iff the running value is `==`-equal to one of `$haystack`'s
   * entries.
   *
   * @param iterable<mixed>|MagicFilter $haystack
   */
  public function in_(iterable|self $haystack): self
  {
    return $this->extend(new FunctionOperation(
      static function (iterable $hay, mixed $needle): bool {
        foreach ($hay as $candidate) {
          if ($candidate == $needle) {
            return true;
          }
        }

        return false;
      },
      [$haystack],
    ));
  }

  /**
   * Inverse of `in_`. Matches upstream `F.not_in(...)` (`magic.py:241-242`).
   *
   * @param iterable<mixed>|MagicFilter $haystack
   */
  public function notIn(iterable|self $haystack): self
  {
    return $this->extend(new FunctionOperation(
      static function (iterable $hay, mixed $needle): bool {
        foreach ($hay as $candidate) {
          if ($candidate == $needle) {
            return false;
          }
        }

        return true;
      },
      [$haystack],
    ));
  }

  /**
   * `F.text.contains('hello')` — substring (for strings) / containment
   * (for iterables / `ArrayAccess`) predicate on the running value.
   *
   * Mirrors upstream `MagicFilter.contains` (`magic.py:244-245`) which
   * itself wraps the polymorphic `contains_op` from `util.py:18-22`.
   */
  public function contains(mixed $needle): self
  {
    return $this->extend(new FunctionOperation(
      static function (mixed $needleVal, mixed $haystack): bool {
        if (is_string($haystack) && is_string($needleVal)) {
          return str_contains($haystack, $needleVal);
        }

        if (is_iterable($haystack)) {
          foreach ($haystack as $candidate) {
            if ($candidate == $needleVal) {
              return true;
            }
          }

          return false;
        }

        return false;
      },
      [$needle],
    ));
  }

  /** Inverse of `contains`. */
  public function notContains(mixed $needle): self
  {
    return $this->extend(new FunctionOperation(
      static function (mixed $needleVal, mixed $haystack): bool {
        if (is_string($haystack) && is_string($needleVal)) {
          return !str_contains($haystack, $needleVal);
        }

        if (is_iterable($haystack)) {
          foreach ($haystack as $candidate) {
            if ($candidate == $needleVal) {
              return false;
            }
          }

          return true;
        }

        return true;
      },
      [$needle],
    ));
  }

  /**
   * `F.text.startswith('/')` — string-prefix predicate. Rejects when the
   * value isn't a string (the resolver collapses non-string subjects to
   * a chain rejection via `FunctionOperation`'s catch-all).
   */
  public function startsWith(string $prefix): self
  {
    return $this->extend(new FunctionOperation(
      static function (string $needle, mixed $haystack): bool {
        if (!is_string($haystack)) {
          return false;
        }

        return str_starts_with($haystack, $needle);
      },
      [$prefix],
    ));
  }

  /** `F.text.endswith('!')` — string-suffix predicate. */
  public function endsWith(string $suffix): self
  {
    return $this->extend(new FunctionOperation(
      static function (string $needle, mixed $haystack): bool {
        if (!is_string($haystack)) {
          return false;
        }

        return str_ends_with($haystack, $needle);
      },
      [$suffix],
    ));
  }

  // ===========================================================================
  // Transformations
  // ===========================================================================

  /**
   * Apply a unary transformation. Used by users for arbitrary projections:
   * `F->count->cast(intval(...))` to coerce a string to int before a
   * subsequent comparison.
   *
   * Mirrors upstream `MagicFilter.cast` (`magic.py:286-287`).
   */
  public function cast(callable $func): self
  {
    return $this->extend(new CastOperation(Closure::fromCallable($func)));
  }

  /**
   * String length / collection size. Mirrors upstream `MagicFilter.len`
   * (`magic.py:250-251`) which wraps `len(value)`. PHP equivalents are
   * `strlen` for strings, `count` for arrays / Countable. The dispatch
   * table picks the right one at resolve time.
   */
  public function len(): self
  {
    return $this->extend(new FunctionOperation(
      static function (mixed $val): int {
        if (is_string($val)) {
          return strlen($val);
        }

        if (is_countable($val)) {
          return count($val);
        }

        if (is_iterable($val)) {
          $n = 0;

          foreach ($val as $_) {
            $n++;
          }

          return $n;
        }

        throw new TypeError('len() expects a string, array, Countable, or iterable.');
      },
    ));
  }

  /**
   * Lowercase. Equivalent of upstream `F.text.lower()` / `F.text.casefold()`.
   * In PHP `mb_strtolower` handles UTF-8 correctly; we use it instead of
   * `strtolower` for the same Unicode-correctness guarantee Python gives.
   */
  public function lower(): self
  {
    return $this->extend(new FunctionOperation(
      static function (mixed $val): string {
        if (!is_scalar($val) && !($val instanceof Stringable)) {
          throw new TypeError('lower() requires a stringable value.');
        }

        return mb_strtolower((string)$val);
      },
    ));
  }

  /** Alias for `lower` (Python `casefold` is roughly equivalent for ASCII). */
  public function casefold(): self
  {
    return $this->lower();
  }

  /** Uppercase. UTF-8 aware via `mb_strtoupper`. */
  public function upper(): self
  {
    return $this->extend(new FunctionOperation(
      static function (mixed $val): string {
        if (!is_scalar($val) && !($val instanceof Stringable)) {
          throw new TypeError('upper() requires a stringable value.');
        }

        return mb_strtoupper((string)$val);
      },
    ));
  }

  /**
   * `F.text.regexp('^/(?<cmd>\\w+)')` — anchor / search / fullmatch the
   * pattern against the running value. The resulting chain value is
   * either a match object (a `string[]` for `MATCH`/`SEARCH`/`FULLMATCH`,
   * a `list<string[]>` for `FINDALL`/`FINDITER`) or `null` on no match —
   * which the resolver collapses to `false` at the chain's terminus.
   *
   * Port of upstream `MagicFilter.regexp` (`magic.py:253-281`). The
   * deprecated `search` boolean (kept upstream for backwards compat) is
   * NOT carried over — modern callers should use `mode` instead.
   *
   * @param string $pattern A raw PCRE pattern WITHOUT delimiters. The
   *                        operation builds the final delimited form
   *                        ('/.../u') internally to handle anchoring per
   *                        mode.
   */
  public function regexp(string $pattern, ?RegexpMode $mode = null, ?bool $search = null): self
  {
    if ($search !== null) {
      // Upstream emits DeprecationWarning. PHP-land mostly hand-converts
      // these so we just reject mutual exclusion strictly and otherwise
      // honour the legacy flag.
      if ($mode !== null) {
        throw new ParamsConflict("Can't pass both 'search' and 'mode' params.");
      }
      $mode = $search ? RegexpMode::SEARCH : RegexpMode::MATCH;
    }

    $mode ??= RegexpMode::MATCH;

    return $this->extend(new FunctionOperation(
      static function (string $rawPattern, RegexpMode $modeArg, mixed $value): ?array {
        if (!is_string($value)) {
          return null;
        }

        // Use `#` as the delimiter (rather than `/`) so user patterns
        // that include literal slashes — `'^/(?<cmd>\\w+)'`, classic
        // command parsing — don't need pre-escaping. We still escape
        // any user `#` inside the pattern to keep the wrapper safe.
        $escaped = str_replace('#', '\\#', $rawPattern);
        $delimited = match ($modeArg) {
          RegexpMode::MATCH => "#\\A{$escaped}#u",
          RegexpMode::FULLMATCH => "#\\A(?:{$escaped})\\z#u",
          RegexpMode::SEARCH => "#{$escaped}#u",
          RegexpMode::FINDALL, RegexpMode::FINDITER => "#{$escaped}#u",
        };

        if ($modeArg === RegexpMode::FINDALL || $modeArg === RegexpMode::FINDITER) {
          $matches = [];
          $count = @preg_match_all($delimited, $value, $matches);

          if ($count === false || $count === 0) {
            return null;
          }

          // Return the full-match list (group 0) for closest-to-Python parity.
          /** @var list<string> $allMatches */
          $allMatches = $matches[0] ?? [];

          return $allMatches;
        }

        $matches = [];
        $result = @preg_match($delimited, $value, $matches);

        if ($result !== 1) {
          return null;
        }

        return $matches;
      },
      [$pattern, $mode],
    ));
  }

  /**
   * Apply an arbitrary callable to the running value. Extra args/kwargs
   * are passed BEFORE the value to match upstream `magic.py:283-284`:
   * `function(*args, value, **kwargs)`.
   *
   * @param mixed ...$args Positional args resolved against the chain
   *                       root if any are `MagicFilter` instances.
   */
  public function func(callable $function, mixed ...$args): self
  {
    [$positional, $named] = $this->splitArgs($args);

    return $this->extend(new FunctionOperation(
      Closure::fromCallable($function),
      $positional,
      $named,
    ));
  }

  /**
   * Internal-ish: append a generic operation to the chain. Used by the
   * F-DSL builders in `Filters\F\*` and by call sites that need a custom
   * `BaseOperation` subclass beyond the built-ins.
   *
   * NOT a substitute for the named methods — `equals`, `func`, etc. are
   * the public surface; this is the trapdoor for advanced users.
   */
  public function extendWith(BaseOperation $operation): self
  {
    return $this->extend($operation);
  }

  /**
   * Filter an iterable subject by a sub-filter. Mirrors upstream
   * `MagicFilter.extract` (`magic.py:289-290`).
   */
  public function extract(self $magic): self
  {
    return $this->extend(new ExtractOperation($magic));
  }

  // ===========================================================================
  // Logical composition
  // ===========================================================================

  /**
   * AND across chains: `$f->and_($g)` succeeds when both accept. Right-
   * hand operand can be either a `MagicFilter` (resolved against the chain
   * root) or a literal (truthy-AND).
   *
   * `and` is a reserved word in PHP, hence the trailing underscore.
   */
  public function and_(mixed $other): self
  {
    return $this->extend(new CombinationOperation(
      $other,
      // Python `a and b` returns `b` if `a` is truthy, else `a`. We
      // mimic that "value-preserving AND" so a chained pipeline can
      // still surface the right-hand value as the running running value
      // (rather than collapsing to a stripped bool).
      static fn(mixed $a, mixed $b): mixed => $a ? $b : $a,
    ));
  }

  /**
   * OR across chains. The OR combinator is "important" so a left-hand
   * rejection still gives the right-hand a chance to vote. Mirrors
   * upstream `magic.py:152-155`.
   */
  public function or_(mixed $other): self
  {
    return $this->extend(new ImportantCombinationOperation(
      $other,
      static fn(mixed $a, mixed $b): mixed => $a ?: $b,
    ));
  }

  /**
   * Negate the chain so far: `$f->not()` flips the verdict. Implemented
   * via an `ImportantFunctionOperation` wrapping logical NOT so even a
   * rejected chain (null value) inverts to `true`.
   *
   * `$f->not()->not()` folds back to `$f` — mirrors upstream
   * `__invert__` (`magic.py:132-139`).
   *
   * Named `not_` (with trailing underscore) is the user-visible name; PHP
   * forbids a method literally named `not` because of the precedence /
   * spacing rules around `not`. We expose `not_()` and the more readable
   * alias `negate()`.
   */
  public function not_(): self
  {
    // Fold `~~F` back to `F` — same micro-optimisation upstream applies.
    $tail = end($this->operations);

    if (
      $tail instanceof ImportantFunctionOperation
      && $tail->args === []
      && $tail->kwargs === []
    ) {
      // We can't peek inside the Closure to confirm it's "not"; rely on
      // the structural marker (no args, important) which is unique to the
      // negation slot we constructed below.
      return $this->excludeLast();
    }

    return $this->extend(new ImportantFunctionOperation(
      static fn(mixed $val): bool => !$val,
    ));
  }

  /** Alias for `not_()` to read naturally in user code. */
  public function negate(): self
  {
    return $this->not_();
  }

  /**
   * Boolean XOR.
   */
  public function xor_(mixed $other): self
  {
    return $this->extend(new CombinationOperation(
      $other,
      static fn(mixed $a, mixed $b): bool => (bool)$a xor (bool)$b,
    ));
  }

  // ===========================================================================
  // .as_(name) terminal — wraps the chain's final value as a kwarg map
  // ===========================================================================

  /**
   * Append the terminal `AsFilterResultOperation` that converts the
   * chain's final value into a kwarg map (`[$name => $value]`) when
   * accepted, or `null` when rejected. Used downstream by
   * `MagicFilterAsFilter` to thread the value into the dispatcher's
   * kwargs bag.
   *
   * 1-for-1 port of aiogram's `MagicFilter.as_` (`aiogram/utils/magic_filter.py:21-22`).
   *
   * Named `as_` (with trailing underscore) because `as` is a PHP keyword.
   */
  public function as_(string $name): self
  {
    return $this->extend(new AsFilterResultOperation($name));
  }

  // ===========================================================================
  // Filter bridge
  // ===========================================================================

  /**
   * Wrap this chain in a `Filter` instance so the dispatcher can consume
   * it directly. The bridge's `__invoke` runs the chain against the
   * incoming event and converts the result into a `bool|array` filter
   * verdict — see `MagicFilterAsFilter::__invoke` for the exact rules.
   */
  public function asFilter(): Filter
  {
    return new MagicFilterAsFilter($this);
  }

  /**
   * Truthy check — always `true`. PHP would coerce a `MagicFilter` to
   * `bool` via `is_object($f)` (always truthy) anyway, but we declare
   * the magic method explicitly so `if ($f)` works deterministically
   * and PHPStan can reason about it. Matches upstream's `__bool__`
   * (`magic.py:93-94`).
   */
  public function asBool(): bool
  {
    return true;
  }
}
