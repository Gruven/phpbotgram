<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Webhook;

use InvalidArgumentException;

/**
 * CIDR-based IP allowlist for incoming webhook requests.
 *
 * Mirrors upstream `aiogram.webhook.security.IPFilter` (aiogram/webhook/security.py).
 *
 * ## Perf-tuned divergence from upstream's hosts-set approach
 *
 * The Python upstream materialises every host address in each added network into
 * a `set[IPv4Address]` and performs O(1) set-membership checks.  For Telegram's
 * default ranges (/20 + /22 ≈ 5 K addresses) this is acceptable in Python but
 * wastes memory for no gain in PHP where the allowed network list is tiny (n=2
 * for the default config).
 *
 * This implementation stores each allowed range as a `(networkLong, prefix)`
 * tuple and performs an O(n) bitwise-mask check on each `check()` call:
 *
 *   ($ipLong & $mask) === $networkLong
 *
 * With n=2 the difference is immeasurable in practice, and the implementation
 * avoids allocating a ~5 K-entry array.  The public API and observable behaviour
 * (including error semantics) are identical to the upstream contract.
 *
 * @internal
 */
final class IpFilter
{
  /**
   * Telegram's documented IPv4 ranges from which webhook requests originate.
   *
   * Source: https://core.telegram.org/bots/webhooks#the-telegram-bot-api-server
   *
   * @var list<string>
   */
  public const array DEFAULT_TELEGRAM_NETWORKS = [
    '149.154.160.0/20',
    '91.108.4.0/22',
  ];

  /**
   * Stored as a list of [networkLong, prefix] pairs.
   *
   * `networkLong` is the 32-bit integer representation of the network address
   * (result of `ip2long()`), `prefix` is the CIDR prefix length (0–32).
   *
   * @var list<array{network: int, prefix: int}>
   */
  private array $networks = [];

  /**
   * @param list<string> $ips Zero or more CIDR ranges (`'1.2.3.0/24'`) or
   *                          individual IPv4 addresses (`'1.2.3.4'`).
   *                          Each entry is forwarded to {@see allow()}.
   */
  public function __construct(array $ips = [])
  {
    if ($ips !== []) {
      $this->allow(...$ips);
    }
  }

  /**
   * Add one or more IPv4 addresses or CIDR ranges to the allowlist.
   *
   * @param string $ips Each argument must be an IPv4 address (`'1.2.3.4'`)
   *                    or a CIDR notation string (`'1.2.3.0/24'`).
   *
   * @throws InvalidArgumentException When an argument is not a valid IPv4
   *                                  address or CIDR range, or when an IPv6
   *                                  address / prefix is supplied.
   */
  public function allow(string ...$ips): void
  {
    foreach ($ips as $ip) {
      $this->addEntry($ip);
    }
  }

  /**
   * Return `true` when `$ip` falls within any allowed network.
   *
   * Returns `false` without throwing when `$ip` is syntactically invalid or
   * IPv6-formatted, mirroring the upstream behaviour where an address that
   * cannot be compared simply does not match.
   *
   * @param string $ip An IPv4 address string to test (e.g. `'1.2.3.4'`).
   */
  public function check(string $ip): bool
  {
    $ipLong = ip2long($ip);

    if ($ipLong === false) {
      // IPv6 or garbage — never matches.
      return false;
    }

    // ip2long returns a signed int on 32-bit builds; force unsigned.
    $ipLong = $ipLong & 0xFFFFFFFF;

    foreach ($this->networks as ['network' => $networkLong, 'prefix' => $prefix]) {
      $mask = $prefix === 0
          ? 0
          : (~((1 << (32 - $prefix)) - 1)) & 0xFFFFFFFF;

      if (($ipLong & $mask) === $networkLong) {
        return true;
      }
    }

    return false;
  }

  /**
   * Return a new `IpFilter` pre-loaded with Telegram's documented IP ranges.
   *
   * Equivalent to `new self(self::DEFAULT_TELEGRAM_NETWORKS)`.
   */
  public static function default(): self
  {
    return new self(self::DEFAULT_TELEGRAM_NETWORKS);
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Parse a single CIDR or bare-IP string and append to `$this->networks`.
   *
   * @throws InvalidArgumentException on invalid input or IPv6 input.
   */
  private function addEntry(string $entry): void
  {
    if (str_contains($entry, '/')) {
      $this->addCidr($entry);
    } else {
      $this->addSingleIp($entry);
    }
  }

  /**
   * @throws InvalidArgumentException
   */
  private function addCidr(string $cidr): void
  {
    $parts = explode('/', $cidr, 2);

    if (count($parts) !== 2) {
      throw new InvalidArgumentException(
        "Invalid CIDR notation: '{$cidr}'.",
      );
    }

    [$address, $prefixStr] = $parts;

    if (str_contains($address, ':')) {
      throw new InvalidArgumentException(
        "IPv6 is not supported: '{$cidr}'.",
      );
    }

    if (!ctype_digit($prefixStr)) {
      throw new InvalidArgumentException(
        "Invalid prefix length in CIDR '{$cidr}': must be a non-negative integer.",
      );
    }

    $prefix = (int)$prefixStr;

    if ($prefix < 0 || $prefix > 32) {
      throw new InvalidArgumentException(
        "Prefix length must be between 0 and 32, got {$prefix} in '{$cidr}'.",
      );
    }

    $networkLong = ip2long($address);

    if ($networkLong === false) {
      throw new InvalidArgumentException(
        "Invalid network address in CIDR '{$cidr}'.",
      );
    }

    // Mask off host bits so the stored value is always the true network address.
    $mask = $prefix === 0
        ? 0
        : (~((1 << (32 - $prefix)) - 1)) & 0xFFFFFFFF;

    $networkLong = ($networkLong & 0xFFFFFFFF) & $mask;

    $this->networks[] = ['network' => $networkLong, 'prefix' => $prefix];
  }

  /**
   * @throws InvalidArgumentException
   */
  private function addSingleIp(string $ip): void
  {
    if (str_contains($ip, ':')) {
      throw new InvalidArgumentException(
        "IPv6 is not supported: '{$ip}'.",
      );
    }

    $long = ip2long($ip);

    if ($long === false) {
      throw new InvalidArgumentException(
        "Invalid IPv4 address: '{$ip}'.",
      );
    }

    $this->networks[] = ['network' => $long & 0xFFFFFFFF, 'prefix' => 32];
  }
}
