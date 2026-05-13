<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Filters;

/**
 * Convenience filter that matches the `/start` command and (optionally)
 * enforces deep-link semantics. Port of
 * `aiogram.filters.command.CommandStart` (`aiogram/filters/command.py:240-303`).
 *
 * # Why a separate class?
 *
 * Upstream `CommandStart` is a subclass of `Command` that hardcodes the
 * command name to `start` and adds two extra fields: `deep_link` (gate on
 * args presence/absence) and `deep_link_encoded` (Base64-decode the args).
 *
 * The PHP port keeps the same surface but composes rather than inherits.
 * Subclassing `Command` would force us to re-declare the same readonly
 * properties via PHP's "promoted property inheritance" dance and would
 * inherit `Command::of` as a static factory that builds wrong-shaped
 * objects. Composition with a private inner `Command` (rebuilt per call)
 * avoids both pitfalls — the inner filter handles all the parsing work
 * and `CommandStart` adds only the deep-link gate.
 *
 * # Phase 4.7 scope
 *
 * `deep_link_encoded` (Base64 payload decoding via
 * `aiogram.utils.deep_linking.decode_payload`) is NOT implemented in this
 * task — that surface lands alongside the deep-linking utility port. The
 * flag itself is therefore not exposed; users who need encoded payloads
 * can decode the raw `args` in the handler for now.
 *
 * # `deepLink` tri-state
 *
 * Mirrors upstream's `Optional[bool]`:
 *   - `null` (default): no gate — args are tolerated but not required.
 *     Behaves exactly like `Command::of('start')`.
 *   - `true`: args MUST be present. `/start payload` matches, `/start`
 *     alone rejects. Matches `validate_deeplink` when `deep_link is True`.
 *   - `false`: args MUST be absent. `/start` matches, `/start payload`
 *     rejects. Matches `validate_deeplink` when `deep_link is False`.
 */
final class CommandStart extends Filter
{
  public function __construct(
    public readonly ?bool $deepLink = null,
    public readonly bool $ignoreCase = false,
    public readonly bool $ignoreMention = false,
  ) {}

  /**
   * @return array<string, mixed>|false
   */
  public function __invoke(object $event, mixed ...$kwargs): array|bool
  {
    // Delegate all parsing to a fresh `Command` instance. Building per
    // call (rather than caching) keeps `CommandStart` immutable and
    // sidesteps any reference-cycle concerns; the construction cost is
    // a couple of property assignments — negligible against the parse
    // and (potentially) the `bot.me()` round-trip the inner filter
    // already performs.
    $inner = new Command(
      'start',
      ignoreCase: $this->ignoreCase,
      ignoreMention: $this->ignoreMention,
    );

    $result = $inner($event, ...$kwargs);

    if ($result === false) {
      return false;
    }

    // The inner Command always returns `['command' => CommandObject]` on
    // accept; we know `$result['command']` exists and is the right type.
    // The intermediate variable narrows it for PHPStan's eye without
    // forcing an extra runtime assertion.
    $command = $result['command'];
    assert($command instanceof CommandObject);

    // Deep-link gate. `null` → no gate. The tri-state mirrors upstream's
    // `if self.deep_link is None: return command` short-circuit.
    if ($this->deepLink === null) {
      return $result;
    }

    $hasArgs = $command->args !== null && $command->args !== '';

    if ($this->deepLink === true && !$hasArgs) {
      // Upstream raises `CommandException('Deep-link was missing')` here
      // and the outer `__call__` collapses it to False; PHP port returns
      // false directly.
      return false;
    }

    if ($this->deepLink === false && $hasArgs) {
      // Upstream raises `CommandException('Deep-link was not expected')`
      // when args are present despite `deep_link is False`. Same collapse.
      return false;
    }

    return $result;
  }
}
