<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client\Session\Middleware;

use Gruven\PhpBotGram\Client\Session\Middleware\RequestMiddlewareManager;
use PHPUnit\Framework\TestCase;

final class RequestMiddlewareManagerTest extends TestCase
{
    public function testWrapWithoutMiddlewareReturnsTerminal(): void
    {
        $manager = new RequestMiddlewareManager();
        $terminal = static fn (int $n): int => $n * 2;
        $wrapped = $manager->wrap($terminal);
        self::assertSame(10, $wrapped(5));
    }

    public function testWrapChainsInRegistrationOrder(): void
    {
        $manager = new RequestMiddlewareManager();
        $log = [];

        $manager->register(new class ($log) extends \Gruven\PhpBotGram\Client\Session\Middleware\BaseRequestMiddleware {
            /** @param list<string> $log */
            public function __construct(public array &$log) {}
            public function __invoke(\Closure $next, \Gruven\PhpBotGram\Bot $bot, \Gruven\PhpBotGram\Methods\TelegramMethod $method, ?int $timeout = null): mixed {
                $this->log[] = 'A-before';
                $result = $next($bot, $method, $timeout);
                $this->log[] = 'A-after';
                return $result;
            }
        });

        $manager->register(new class ($log) extends \Gruven\PhpBotGram\Client\Session\Middleware\BaseRequestMiddleware {
            /** @param list<string> $log */
            public function __construct(public array &$log) {}
            public function __invoke(\Closure $next, \Gruven\PhpBotGram\Bot $bot, \Gruven\PhpBotGram\Methods\TelegramMethod $method, ?int $timeout = null): mixed {
                $this->log[] = 'B-before';
                $result = $next($bot, $method, $timeout);
                $this->log[] = 'B-after';
                return $result;
            }
        });

        $terminal = function (...$args) use (&$log) {
            $log[] = 'terminal';
            return 'done';
        };

        $wrapped = $manager->wrap($terminal);
        $result = $wrapped(
            new \Gruven\PhpBotGram\Bot(),
            new class extends \Gruven\PhpBotGram\Methods\TelegramMethod {
                public const string ApiMethod = 'x';
                public const string ReturnsType = \stdClass::class;
            },
        );

        self::assertSame('done', $result);
        self::assertSame(['A-before', 'B-before', 'terminal', 'B-after', 'A-after'], $log);
    }
}
