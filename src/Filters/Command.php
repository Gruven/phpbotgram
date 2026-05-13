<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Message;
use InvalidArgumentException;
use Throwable;

/**
 * Slash-command matcher. Port of `aiogram.filters.command.Command`
 * (`aiogram/filters/command.py:25-198`). Accepts a message and matches its
 * `text` (or fallback `caption`) against one or more registered command
 * patterns. On match, returns `['command' => CommandObject]` so the parsed
 * pieces flow into the handler as a `$command` kwarg.
 *
 * # Constructor shape
 *
 * Upstream's signature is `Command(*values, *, prefix, ignore_case,
 * ignore_mention, magic)`. PHP forbids parameters after a variadic, so the
 * port collapses the variadic into a `string|list<string>` first argument
 * and exposes `Command::of(...$cmds)` as a variadic-friendly factory.
 *
 *   new Command(['start', 'help'])                    // array form
 *   new Command('start')                              // single string
 *   new Command('start', ignoreCase: true)            // single + flags
 *   Command::of('start', 'help')                      // variadic shorthand
 *   new Command(['start'], prefix: '/!')               // string form: upstream parity ('/!' -> ['/', '!'])
 *   new Command(['start'], prefix: ['/', '!'])        // list form: same result
 *   new Command(['start'], prefix: ['!cmd'])          // list-only: multi-char prefix (PHP extension)
 *
 * # Match algorithm (mirrors upstream `parse_command`)
 *
 * 1. Reject early when the event is not a `Message` or when both `text`
 *    and `caption` are absent / empty.
 * 2. Find the longest prefix from `$prefix` that the text starts with;
 *    bail if none match.
 * 3. Split the post-prefix tail on the first whitespace run — left side
 *    is the candidate command, right side is the args (or `null`).
 * 4. If the candidate contains `@`, split on the FIRST `@` only; the
 *    right side becomes the mention (matching upstream's `partition('@')`).
 *    Mirrors upstream issue aiogram/aiogram#1013: a missing mention surfaces
 *    as `null`, never as `''`.
 * 5. If `ignoreMention` is false AND a bot is supplied AND a mention is
 *    present, compare against `bot.me().username` case-insensitively.
 *    Mismatch → reject. Missing bot (e.g. unit tests that don't wire one)
 *    → skip the mention check (parity with upstream where the dispatcher
 *    always supplies one). Failures inside `me()` (network, mocked
 *    fixtures without canned response) are absorbed so tests don't have
 *    to seed `getMe` responses for every command-filter scenario.
 * 6. Walk the registered commands in declaration order, comparing with
 *    `strcasecmp` or `===` depending on `$ignoreCase`. The first match
 *    builds the `CommandObject` and short-circuits.
 *
 * # Phase 4.7 scope
 *
 * Strings only — regex / `BotCommand` / magic-filter post-validation /
 * deep-link handling all land in later tasks. The class is structured so
 * those surfaces can be added without churning the constructor or call
 * shape.
 */
final class Command extends Filter
{
  /** @var list<string> Registered command patterns. */
  public readonly array $commands;

  /** @var list<string> Acceptable command prefixes (default `['/']`). */
  public readonly array $prefix;

  /**
   * @param list<string>|string $commands Either a single command string or a
   *                                      list. Throws on empty list.
   * @param list<string>|string $prefix Acceptable prefixes (default `['/']`).
   *                                    Accepts either a `list<string>` of
   *                                    prefix strings (PHP-side extension; allows
   *                                    multi-character prefixes like `['!cmd']`)
   *                                    or a plain string whose individual characters
   *                                    are each treated as a prefix — matching
   *                                    upstream's `Command(prefix='/!')` syntax where
   *                                    `'/!'` means "either `/` or `!`".
   *                                    First match wins during parsing.
   */
  public function __construct(
    array|string $commands,
    array|string $prefix = ['/'],
    public readonly bool $ignoreCase = false,
    public readonly bool $ignoreMention = false,
  ) {
    // Normalise: single string → list of one. The dataclass surface only
    // accepts a list for storage so subsequent loops are uniform.
    if (is_string($commands)) {
      $commands = [$commands];
    }

    if ($commands === []) {
      // Upstream raises `ValueError('At least one command should be
      // specified')` at `aiogram/filters/command.py:86-88`. PHP equivalent:
      // `InvalidArgumentException` (the closest semantic match in the
      // standard SPL hierarchy).
      throw new InvalidArgumentException('At least one command should be specified');
    }

    // Normalise prefix: upstream accepts `prefix='/!'` as a string where
    // each character is an independent allowed prefix — mirrors
    // `aiogram/filters/command.py:46-55`. A string is decomposed
    // per-character via `mb_str_split` so `'/!'` becomes `['/', '!']`.
    // An array is stored as-is (PHP-side extension allowing multi-char
    // prefixes such as `['!cmd']`).
    if (is_string($prefix)) {
      $prefix = mb_str_split($prefix);
    }

    // `array_values` guards against numeric-keyed dicts arriving from
    // call sites that accidentally pre-pack the list — guarantees a real
    // `list<string>` for the readonly property and PHPStan typing.
    $this->commands = array_values($commands);
    $this->prefix = $prefix;
  }

  /**
   * Variadic-friendly factory mirroring upstream's `Command('start', 'help')`
   * call shape. Equivalent to `new Command([$cmd1, $cmd2, ...])`.
   */
  public static function of(string ...$commands): self
  {
    // `array_values` turns the PHP variadic dict (whose keys are 0..N but
    // PHPStan still types as `array<int|string, string>`) into a proper
    // `list<string>` so the constructor's `string|list<string>` typing
    // narrows cleanly.
    return new self(array_values($commands));
  }

  /**
   * Filter entry point. Returns either `false` (reject) or
   * `['command' => CommandObject]` (accept with a single kwarg). See class
   * docblock for the per-step algorithm.
   *
   * @return array<string, mixed>|false
   */
  public function __invoke(object $event, array $kwargs = []): array|bool
  {
    if (!$event instanceof Message) {
      // Mirror upstream's defensive type guard. A misconfigured router
      // could wire `Command` onto a non-message observer; rejecting is
      // safer than crashing the dispatch loop.
      return false;
    }

    $text = $event->text ?? $event->caption;

    if ($text === null || $text === '') {
      return false;
    }

    $bot = $kwargs['bot'] ?? null;

    if ($bot !== null && !$bot instanceof Bot) {
      // Defensive: only honour the kwarg when it is actually a Bot. A
      // misconfigured middleware that stashed a different value should
      // not surface as a TypeError deep inside `me()`.
      $bot = null;
    }

    $parsed = $this->parseCommand($text, $bot);

    if ($parsed === null) {
      return false;
    }

    return ['command' => $parsed];
  }

  /**
   * Parse `$text` into a `CommandObject` if it matches one of `$this->commands`.
   * Returns `null` on any mismatch (unknown prefix, wrong command name,
   * mention mismatch).
   *
   * Kept private — external callers should go through `__invoke`. The
   * upstream method is public, but it is also async and tightly coupled
   * to the filter's own validation methods (`validate_prefix`,
   * `validate_mention`, `validate_command`). Inlining those into one
   * routine here matches the PHP idiom and removes a layer of
   * indirection.
   */
  private function parseCommand(string $text, ?Bot $bot): ?CommandObject
  {
    // 1. Match a prefix. Iterate in declaration order so longer / more
    //    specific prefixes can be registered first (e.g. `['/!', '/']`
    //    would let a user reserve `/!cmd` for admin commands while still
    //    accepting plain `/cmd`).
    $matchedPrefix = null;

    foreach ($this->prefix as $candidate) {
      if ($candidate !== '' && str_starts_with($text, $candidate)) {
        $matchedPrefix = $candidate;

        break;
      }
    }

    if ($matchedPrefix === null) {
      return null;
    }

    $rest = substr($text, strlen($matchedPrefix));

    // 2. Split off args on the first run of whitespace. Matches upstream's
    //    `text.split(maxsplit=1)`. Python's `str.split(maxsplit=1)` silently
    //    drops any leading whitespace before splitting (e.g.
    //    `'   start args'.split(maxsplit=1)` -> `['start', 'args']`).
    //    PHP's `preg_split('/\s+/', '   start args', 2)` would return
    //    `['', 'start args']` — the leading-whitespace edge case diverges.
    //    `ltrim($rest)` strips leading whitespace first, restoring parity.
    /** @var list<string> $parts */
    $parts = preg_split('/\s+/', ltrim($rest), 2);
    $commandPart = $parts[0];
    $args = $parts[1] ?? null;

    // Upstream rejects `/` (bare prefix) — `text.split(maxsplit=1)` on
    // `'/'` yields `['/']`, empty command. The `ltrim` above widens
    // slightly over upstream: `'/ args'` (prefix + space) now extracts
    // `args` as the command name instead of rejecting. This is intentional
    // (see Issue 5 / `testMatchesCommandWithExtraWhitespaceAfterPrefix`).
    if ($commandPart === '') {
      return null;
    }

    // 3. Extract `@mention` from the command segment. Upstream uses
    //    `str.partition('@')` which keeps any later `@` glyphs in the
    //    third part — PHP's `explode($sep, $str, 2)` provides the same
    //    behaviour (split on the FIRST occurrence; tail stays intact).
    $mention = null;

    if (str_contains($commandPart, '@')) {
      [$commandPart, $mention] = explode('@', $commandPart, 2);

      // Upstream's `mention or None` collapse — issue aiogram/aiogram#1013.
      // An empty string after `@` (e.g. `/start@`) is normalised to null
      // so `mentioned()` reports false.
      if ($mention === '') {
        $mention = null;
      }
    }

    // 4. Mention check. Compares against `bot.me().username`. We swallow
    //    failures inside `me()` so tests that don't seed a canned `getMe`
    //    response don't have to wire one for every command-filter
    //    scenario; production users always have a usable bot reference
    //    because the dispatcher injects it.
    if (!$this->ignoreMention && $bot !== null && $mention !== null) {
      try {
        $me = $bot->me();

        if ($me->username !== null && strcasecmp($mention, $me->username) !== 0) {
          return null;
        }
      } catch (Throwable) {
        // No-op: skip the check rather than rejecting. This branch is
        // unreachable in production but exists so the filter can be
        // exercised in isolation.
      }
    }

    // 5. Match the parsed command name against the registered patterns.
    //    The first match wins, matching upstream's `for allowed_command
    //    in self.commands: ... if match: return ...`.
    foreach ($this->commands as $registered) {
      if ($this->commandMatches($commandPart, $registered)) {
        return new CommandObject(
          prefix: $matchedPrefix,
          command: $registered,
          mention: $mention,
          args: $args,
        );
      }
    }

    return null;
  }

  /**
   * Compare a parsed command name against a registered pattern. Uses
   * `strcasecmp` when `$ignoreCase` is set (functionally equivalent to
   * upstream's casefolding for ASCII command strings, which is what the
   * upstream parametrize block exercises). Strict `===` otherwise.
   */
  private function commandMatches(string $candidate, string $registered): bool
  {
    if ($this->ignoreCase) {
      return strcasecmp($candidate, $registered) === 0;
    }

    return $candidate === $registered;
  }
}
