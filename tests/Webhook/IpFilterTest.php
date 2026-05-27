<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Webhook;

use Gruven\PhpBotGram\Webhook\IpFilter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see IpFilter}.
 *
 * Mirrors upstream aiogram/tests/test_webhook/test_security.py and validates
 * the CIDR-based (perf-tuned) PHP implementation against the same contracts as
 * Python's host-set-based reference.
 *
 * Upstream `tests/test_webhook/test_security.py` cases deliberately not ported:
 *
 * - `TestSecurity::test_empty_init` — API divergence: Python asserts
 *   `not ip_filter._allowed_ips` (accessing private `_allowed_ips` set); PHP
 *   uses CIDR arrays internally and has no equivalent public accessor. Behavior
 *   (empty filter denies all) is covered by `testEmptyFilterDeniesEverything`.
 * - `TestSecurity::test_allow_ip` parametrize row `[42, set()]` — API
 *   divergence: Python accepts any type and raises `ValueError` for non-string/
 *   non-IP-object; PHP's `allow()` is typed `string` and would produce a type
 *   error at the call site rather than an exception from within the method. The
 *   equivalent invalid-string rejection is covered by `testInvalidInputThrowsInvalidArgumentException`.
 * - `TestSecurity::test_allow_ip` rows that assert exact `_allowed_ips` set
 *   equality (e.g., `ip_filter._allowed_ips == set(IPv4Network("91.108.4.0/22").hosts())`) —
 *   API divergence: Python expands CIDRs to host sets for O(1) lookup; PHP
 *   stores the CIDR strings directly and matches with `ip_in_cidr()`. The
 *   externally-observable contract (membership check) is verified by the
 *   existing `testCheck*` / `testDefaultFilter*` / `testAllow*` test methods.
 *
 * All other upstream cases are either ported below or covered behaviorally
 * by other test methods in this file.
 *
 * @internal
 *
 * @coversNothing
 */
final class IpFilterTest extends TestCase
{
  // =========================================================================
  // Empty filter
  // =========================================================================

  public function testEmptyFilterDeniesEverything(): void
  {
    $filter = new IpFilter();

    self::assertFalse($filter->check('1.2.3.4'));
    self::assertFalse($filter->check('0.0.0.0'));
    self::assertFalse($filter->check('255.255.255.255'));
  }

  // =========================================================================
  // Single-IP allowlist
  // =========================================================================

  public function testSingleIpAllowsExactAddress(): void
  {
    $filter = new IpFilter(['192.168.1.100']);

    self::assertTrue($filter->check('192.168.1.100'));
  }

  public function testSingleIpDeniesOtherAddresses(): void
  {
    $filter = new IpFilter(['192.168.1.100']);

    self::assertFalse($filter->check('192.168.1.101'));
    self::assertFalse($filter->check('192.168.1.99'));
    self::assertFalse($filter->check('10.0.0.1'));
  }

  // =========================================================================
  // CIDR range
  // =========================================================================

  public function testCidrRejectsNetworkAddress(): void
  {
    // For prefix 1-30 the network address (lowest) is excluded, matching
    // upstream IPv4Network.hosts() semantics.
    $filter = new IpFilter(['10.0.0.0/24']);

    self::assertFalse($filter->check('10.0.0.0'), 'network address must be rejected for /24');
  }

  public function testCidrRejectsBroadcastAddress(): void
  {
    // For prefix 1-30 the broadcast address (highest) is excluded.
    $filter = new IpFilter(['10.0.0.0/24']);

    self::assertFalse($filter->check('10.0.0.255'), 'broadcast address must be rejected for /24');
  }

  public function testCidrAllowsHostInMiddle(): void
  {
    $filter = new IpFilter(['10.0.0.0/24']);

    self::assertTrue($filter->check('10.0.0.128'));
  }

  public function testCidrDeniesAddressOutsideRange(): void
  {
    $filter = new IpFilter(['10.0.0.0/24']);

    self::assertFalse($filter->check('10.0.1.0'));
    self::assertFalse($filter->check('11.0.0.1'));
  }

  // =========================================================================
  // Default Telegram networks
  // =========================================================================

  public function testDefaultFilterAllowsTelegramRange1(): void
  {
    // 149.154.160.0/20 covers 149.154.160.0 – 149.154.175.255.
    // Network (149.154.160.0) and broadcast (149.154.175.255) are excluded per
    // upstream IPv4Network.hosts() semantics; only interior hosts are allowed.
    $filter = IpFilter::default();

    self::assertFalse($filter->check('149.154.160.0'), 'network address of /20 must be rejected');
    self::assertTrue($filter->check('149.154.170.1'), 'mid-range host in /20 must be allowed');
    self::assertFalse($filter->check('149.154.175.255'), 'broadcast of /20 must be rejected');
  }

  public function testDefaultFilterAllowsTelegramRange2(): void
  {
    // 91.108.4.0/22 covers 91.108.4.0 – 91.108.7.255.
    // Network and broadcast excluded per upstream IPv4Network.hosts() semantics.
    $filter = IpFilter::default();

    self::assertFalse($filter->check('91.108.4.0'), 'network address of /22 must be rejected');
    self::assertTrue($filter->check('91.108.6.100'), 'mid-range host in /22 must be allowed');
    self::assertFalse($filter->check('91.108.7.255'), 'broadcast of /22 must be rejected');
  }

  public function testDefaultFilterDeniesAddressOutsideTelegramRanges(): void
  {
    $filter = IpFilter::default();

    self::assertFalse($filter->check('149.154.176.0'), 'just past /20 upper bound');
    self::assertFalse($filter->check('149.154.159.255'), 'just before /20 lower bound');
    self::assertFalse($filter->check('91.108.8.0'), 'just past /22 upper bound');
    self::assertFalse($filter->check('91.108.3.255'), 'just before /22 lower bound');
    self::assertFalse($filter->check('8.8.8.8'));
  }

  // =========================================================================
  // Multiple networks — union semantics
  // =========================================================================

  public function testMultipleNetworksUnionSemantics(): void
  {
    $filter = new IpFilter(['10.0.0.0/24', '192.168.1.0/24']);

    self::assertTrue($filter->check('10.0.0.50'), 'first network');
    self::assertTrue($filter->check('192.168.1.200'), 'second network');
    self::assertFalse($filter->check('172.16.0.1'), 'neither network');
  }

  // =========================================================================
  // allow() variadic method
  // =========================================================================

  public function testAllowMethodAddsToExistingFilter(): void
  {
    $filter = new IpFilter();
    $filter->allow('10.0.0.1');
    $filter->allow('10.0.0.2', '10.0.0.3');

    self::assertTrue($filter->check('10.0.0.1'));
    self::assertTrue($filter->check('10.0.0.2'));
    self::assertTrue($filter->check('10.0.0.3'));
    self::assertFalse($filter->check('10.0.0.4'));
  }

  // =========================================================================
  // Boundary / edge cases for prefix lengths
  // =========================================================================

  public function testPrefixLength32MatchesSingleIp(): void
  {
    $filter = new IpFilter(['172.16.0.1/32']);

    self::assertTrue($filter->check('172.16.0.1'));
    self::assertFalse($filter->check('172.16.0.0'));
    self::assertFalse($filter->check('172.16.0.2'));
  }

  public function testPrefixLength0MatchesInteriorIpv4(): void
  {
    // /0 covers the entire IPv4 space but excludes the network address
    // (0.0.0.0) and broadcast address (255.255.255.255), matching upstream
    // IPv4Network.hosts() semantics applied consistently for all non-/31/32
    // prefix lengths.
    $filter = new IpFilter(['0.0.0.0/0']);

    self::assertFalse($filter->check('0.0.0.0'), 'network address must be rejected for /0');
    self::assertTrue($filter->check('1.2.3.4'), 'interior address must be allowed for /0');
    self::assertFalse($filter->check('255.255.255.255'), 'broadcast address must be rejected for /0');
  }

  // =========================================================================
  // Invalid input — InvalidArgumentException
  // =========================================================================

  /**
   * @return list<array{0: string, 1: string}>
   */
  public static function invalidIpProvider(): array
  {
    return [
      ['not-an-ip', 'garbage string'],
      ['999.999.999.999', 'out-of-range octets'],
      ['1.2.3.4.5', 'too many octets'],
      ['1.2.3.4/33', 'prefix > 32'],
      ['1.2.3.4/abc', 'non-numeric prefix'],
    ];
  }

  /**
   * @param string $ip The invalid IP or CIDR string.
   * @param string $label Human-readable description for assertion messages.
   */
  #[DataProvider('invalidIpProvider')]
  public function testInvalidInputThrowsInvalidArgumentException(
    string $ip,
    string $label,
  ): void {
    $this->expectException(InvalidArgumentException::class);

    $filter = new IpFilter();
    $filter->allow($ip);
  }

  // =========================================================================
  // IPv6 handling
  // =========================================================================

  public function testIpv6InAllowThrowsInvalidArgumentException(): void
  {
    $this->expectException(InvalidArgumentException::class);

    $filter = new IpFilter();
    $filter->allow('::1');
  }

  public function testIpv6InCheckReturnsFalseWithoutThrowing(): void
  {
    // check() is lenient — an IPv6 address on the wire simply doesn't match.
    $filter = IpFilter::default();

    self::assertFalse($filter->check('::1'));
    self::assertFalse($filter->check('2001:db8::1'));
  }

  // =========================================================================
  // Constructor IPs forwarded to allow()
  // =========================================================================

  public function testConstructorForwardsIpsToAllow(): void
  {
    $filter = new IpFilter(['10.0.0.0/8', '192.168.100.1']);

    self::assertTrue($filter->check('10.1.2.3'));
    self::assertTrue($filter->check('192.168.100.1'));
    self::assertFalse($filter->check('172.16.0.1'));
  }

  public function testConstructorWithEmptyArrayCreatesEmptyFilter(): void
  {
    $filter = new IpFilter([]);

    self::assertFalse($filter->check('1.2.3.4'));
  }

  // =========================================================================
  // Static default() factory
  // =========================================================================

  public function testDefaultFactoryReturnsIpFilterInstance(): void
  {
    self::assertInstanceOf(IpFilter::class, IpFilter::default());
  }

  public function testDefaultFactoryCreatesIndependentInstances(): void
  {
    $a = IpFilter::default();
    $b = IpFilter::default();

    // Adding to one must not affect the other.
    $a->allow('10.0.0.0/8');

    self::assertFalse($b->check('10.1.2.3'));
  }

  // =========================================================================
  // Upstream-parity: network/broadcast exclusion (#2 fix)
  // =========================================================================

  public function testDefaultFilterRejectsNetworkAddress(): void
  {
    // Upstream: IPv4Address('149.154.160.0') in IPFilter.default() → False
    $filter = IpFilter::default();

    self::assertFalse(
      $filter->check('149.154.160.0'),
      'network address 149.154.160.0 of /20 must be rejected (upstream parity)',
    );
  }

  public function testDefaultFilterRejectsBroadcastAddress(): void
  {
    // Upstream: IPv4Address('149.154.175.255') in IPFilter.default() → False
    $filter = IpFilter::default();

    self::assertFalse(
      $filter->check('149.154.175.255'),
      'broadcast address 149.154.175.255 of /20 must be rejected (upstream parity)',
    );
  }

  public function testSlash31AcceptsBothAddresses(): void
  {
    // /31: RFC 3021 — both addresses are usable hosts. Python hosts() returns
    // both, so both must be accepted.
    $filter = new IpFilter(['1.2.3.0/31']);

    self::assertTrue($filter->check('1.2.3.0'), '/31 lower address must be accepted');
    self::assertTrue($filter->check('1.2.3.1'), '/31 upper address must be accepted');
  }

  public function testSlash32AcceptsItsOnlyAddress(): void
  {
    // /32: single-host range. Python hosts() returns the one address.
    $filter = new IpFilter(['1.2.3.4/32']);

    self::assertTrue($filter->check('1.2.3.4'), '/32 address must be accepted');
    self::assertFalse($filter->check('1.2.3.3'), 'address outside /32 must be rejected');
    self::assertFalse($filter->check('1.2.3.5'), 'address outside /32 must be rejected');
  }
}
