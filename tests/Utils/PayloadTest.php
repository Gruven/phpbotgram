<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Utils\Payload;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Payload}.
 *
 * Port of upstream `tests/test_utils/test_payload.py` equivalents.
 *
 * Upstream skips
 * --------------
 * - `test_custom_encode_decode` (from `test_deep_linking.py`): uses
 *   PyCryptodome AES encryption as a custom codec; no PHP equivalent library
 *   is bundled — test infrastructure divergence (c); the round-trip concept is
 *   covered by `testRoundTripWithCustomEncoderDecoder` using XOR.
 *
 * @internal
 */
final class PayloadTest extends TestCase
{
  // ---------------------------------------------------------------------------
  // encode
  // ---------------------------------------------------------------------------

  public function testEncodeKnownString(): void
  {
    // "foo" in standard base64 is "Zm9v" — same in URL-safe variant (no +//).
    self::assertSame('Zm9v', Payload::encode('foo'));
  }

  public function testEncodeStripsTrailingPadding(): void
  {
    // "f" encodes to "Zg==" in standard base64; padding must be stripped.
    self::assertSame('Zg', Payload::encode('f'));
  }

  public function testEncodeReplacesUrlUnsafeChars(): void
  {
    // "\xfb\xff" base64-encodes to "+//" (standard) → "-_8" (URL-safe).
    // We use a byte sequence that would produce "+" and "/" in standard base64.
    // Standard base64 of bytes 0xFB 0xFF: "  " → "+/8=" → URL-safe "-_8"
    $result = Payload::encode("\xfb\xff");
    self::assertStringNotContainsString('+', $result);
    self::assertStringNotContainsString('/', $result);
    self::assertStringNotContainsString('=', $result);
  }

  public function testEncodeWithCustomEncoder(): void
  {
    // Encoder reverses the bytes.
    $encoder = static fn(string $bytes): string => strrev($bytes);
    // "foo" reversed is "oof" → base64 "b29m"
    self::assertSame('b29m', Payload::encode('foo', $encoder));
  }

  // ---------------------------------------------------------------------------
  // decode
  // ---------------------------------------------------------------------------

  public function testDecodeKnownString(): void
  {
    self::assertSame('foo', Payload::decode('Zm9v'));
  }

  public function testDecodeRestoresMissingPadding(): void
  {
    // "Zg" has 2 chars — padding stripped; decode must re-pad to "Zg==" → "f".
    self::assertSame('f', Payload::decode('Zg'));
  }

  public function testDecodeHandlesOneMissingPadChar(): void
  {
    // "Zm8" → "Zm8=" → "fo"
    self::assertSame('fo', Payload::decode('Zm8'));
  }

  public function testDecodeUrlSafeChars(): void
  {
    // Encode first using the URL-safe variant, then decode.
    $encoded = Payload::encode("\xfb\xff");
    self::assertSame("\xfb\xff", Payload::decode($encoded));
  }

  public function testDecodeWithCustomDecoder(): void
  {
    $encoder = static fn(string $bytes): string => strrev($bytes);
    $decoder = static fn(string $bytes): string => strrev($bytes);
    $encoded = Payload::encode('foo', $encoder);
    self::assertSame('foo', Payload::decode($encoded, $decoder));
  }

  // ---------------------------------------------------------------------------
  // round-trip
  // ---------------------------------------------------------------------------

  public function testRoundTrip(): void
  {
    $original = 'Hello, World!';
    self::assertSame($original, Payload::decode(Payload::encode($original)));
  }

  public function testRoundTripWithCustomEncoderDecoder(): void
  {
    // Encoder XOR-masks every byte with 0x42; decoder applies the same mask.
    $xorMask = static function (string $bytes): string {
      $result = '';

      for ($i = 0; $i < strlen($bytes); ++$i) {
        $result .= chr((ord($bytes[$i]) ^ 0x42) & 0xFF);
      }

      return $result;
    };
    $original = 'round-trip test';
    self::assertSame($original, Payload::decode(Payload::encode($original, $xorMask), $xorMask));
  }

  // ---------------------------------------------------------------------------
  // invalid input
  // ---------------------------------------------------------------------------

  public function testDecodeThrowsOnGarbage(): void
  {
    $this->expectException(InvalidArgumentException::class);
    Payload::decode('!!!');
  }
}
