<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

abstract class BotContextController
{
    public function __construct(public readonly ?\Gruven\PhpBotGram\Bot $bot = null) {}
}
