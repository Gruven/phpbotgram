<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Exceptions\TokenValidationException;
use Gruven\PhpBotGram\Utils\Token;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TokenTest extends TestCase
{
  public function testValidate(): void
  {
    Token::validate('42:TEST');
    Token::validate('12345:abcdef-XYZ');
    $this->expectNotToPerformAssertions();
  }

  /**
   * @return iterable<string, array{string}>
   */
  public static function invalidTokens(): iterable
  {
    yield 'empty' => [''];

    yield 'no colon' => ['12345'];

    yield 'left non-digit' => ['abc:TEST'];

    yield 'right empty' => ['42:'];

    yield 'trailing newline' => ["42:TEST\n"];

    yield 'leading space' => [' 42:TEST'];

    yield 'embedded tab' => ["42:\tTEST"];

    yield 'embedded space' => ['42:TE ST'];

    yield 'path traversal' => ['42:foo/../bar'];

    yield 'forward slash' => ['42:abc/xyz'];

    yield 'percent encoded' => ['42:abc%2F'];
  }

  #[DataProvider('invalidTokens')]
  public function testValidateRejects(string $token): void
  {
    $this->expectException(TokenValidationException::class);
    Token::validate($token);
  }

  public function testExtractBotId(): void
  {
    self::assertSame(42, Token::extractBotId('42:TEST'));
    self::assertSame(123456789, Token::extractBotId('123456789:secret'));
  }
}
