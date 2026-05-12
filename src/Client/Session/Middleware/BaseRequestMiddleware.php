<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client\Session\Middleware;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\TelegramMethod;

abstract class BaseRequestMiddleware
{
  /**
   * @param Closure(Bot, TelegramMethod<mixed>, ?int): mixed $next
   * @param TelegramMethod<mixed> $method
   */
  abstract public function __invoke(Closure $next, Bot $bot, TelegramMethod $method, ?int $timeout = null): mixed;
}
