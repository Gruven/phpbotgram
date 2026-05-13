<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

/**
 * Readonly DTO carrying the parsed pieces of a Telegram slash-command
 * (`/cmd@mention args`). Produced by {@see Command}::__invoke and injected
 * into the handler kwargs under the `command` key.
 *
 * Mirrors upstream `aiogram.filters.command.CommandObject`
 * (`aiogram/filters/command.py:202-237`). Field order matches the upstream
 * `@dataclass` declaration: `prefix`, `command`, `mention`, `args`,
 * `regexp_match`, `magic_result`.
 *
 * Field role summary:
 *   - `prefix`: leading character(s) before the command name (`/`, `!`, …).
 *     A `Command` filter can register multiple prefixes; the matched one
 *     ends up here.
 *   - `command`: command name without prefix or `@mention`.
 *   - `mention`: optional `@`-suffixed bot mention, normalised (the leading
 *     `@` is stripped) — `null` when the input had no `@suffix`. Upstream
 *     issue aiogram/aiogram#1013 makes the absence explicit; we mirror that
 *     in the parser by storing `null` (not `''`) when no mention was
 *     present.
 *   - `args`: post-command remainder verbatim; `null` if the user wrote no
 *     args at all. Upstream's `text.split(maxsplit=1)` is mirrored by
 *     `preg_split('/\s+/', $rest, 2)` in `Command::parseCommand`, which
 *     consumes the first run of whitespace and preserves the tail.
 *   - `regexpMatch`: the regex match payload if the command was matched via
 *     a regex pattern. Surface only — populated in Phase 4.5+ when the
 *     `Command(string|Regex|BotCommand ...)` variants land.
 *   - `magicResult`: result of a `MagicFilter`-based post-validation. Also
 *     Phase 4.5+; surface today so a follow-up patch can drop the field in
 *     without churning the DTO shape.
 *
 * Both `regexpMatch`/`magicResult` mirror upstream's `repr=False` semantic
 * by simply staying out of any `__toString`-style helper — there's no
 * stable PHP convention for excluding fields from `var_dump`, but no
 * production code path inspects them anyway.
 */
final readonly class CommandObject
{
  /**
   * @param ?array<int|string, mixed> $regexpMatch Capture groups from a regex
   *                                               match, when the matching command pattern was a regex (Phase 4.5+).
   *                                               Null otherwise.
   * @param mixed $magicResult Result of a magic-filter
   *                           post-validation (Phase 4.5+). Null otherwise.
   */
  public function __construct(
    public string $prefix = '/',
    public string $command = '',
    public ?string $mention = null,
    public ?string $args = null,
    public ?array $regexpMatch = null,
    public mixed $magicResult = null,
  ) {}

  /**
   * Whether the command carried an `@bot_username` mention. Mirrors upstream's
   * derived `mentioned` property at `aiogram/filters/command.py:220-225`.
   *
   * Implementation note: Python uses `bool(self.mention)` which collapses
   * empty strings to false. We mirror by combining the null check with an
   * explicit emptiness guard so `mention: ''` (defensive callers) does not
   * suddenly count as mentioned.
   */
  public function mentioned(): bool
  {
    return $this->mention !== null && $this->mention !== '';
  }

  /**
   * Mention with any leading `@` stripped, or `null` when no mention is
   * stored. Matches upstream's `mention_without_prefix` convenience used by
   * `Command.validate_mention` when comparing against `bot.me().username`.
   *
   * The current parser already strips the `@` before storing, but this
   * helper is the canonical accessor so user-land code can recover the
   * unprefixed form from a CommandObject built by hand (tests, fixtures).
   */
  public function mentionWithoutPrefix(): ?string
  {
    if ($this->mention === null) {
      return null;
    }

    return ltrim($this->mention, '@');
  }

  /**
   * Reassemble the original textual command from the parsed parts. Mirrors
   * upstream's `text` property (`aiogram/filters/command.py:228-237`) and
   * provides a round-trip for logging / display purposes.
   */
  public function text(): string
  {
    $line = $this->prefix . $this->command;

    if ($this->mention !== null && $this->mention !== '') {
      $line .= '@' . $this->mention;
    }

    if ($this->args !== null && $this->args !== '') {
      $line .= ' ' . $this->args;
    }

    return $line;
  }
}
