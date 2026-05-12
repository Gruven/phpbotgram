<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Utils;

use Gruven\PhpBotGram\Exceptions\PhpBotGramException;
use Gruven\PhpBotGram\Utils\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testValidate(): void
    {
        Token::validate('42:TEST');
        Token::validate('12345:abcdef-XYZ');
        $this->expectNotToPerformAssertions();
    }

    public static function invalidTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'no colon' => ['12345'];
        yield 'left non-digit' => ['abc:TEST'];
        yield 'right empty' => ['42:'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTokens')]
    public function testValidateRejects(string $token): void
    {
        $this->expectException(PhpBotGramException::class);
        Token::validate($token);
    }

    public function testExtractBotId(): void
    {
        self::assertSame(42, Token::extractBotId('42:TEST'));
        self::assertSame(123456789, Token::extractBotId('123456789:secret'));
    }
}
