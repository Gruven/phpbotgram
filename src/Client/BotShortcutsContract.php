<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Client;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Downloadable;
use Gruven\PhpBotGram\Types\File;
use Gruven\PhpBotGram\Types\User;

interface BotShortcutsContract
{
  /**
   * Bot ID extracted from the token. Named `getId()` (not `id()`) because upstream `bot.id`
   * is a Python @property — grep-translating aiogram code would see PHP return a method
   * handle instead of the int. The explicit `getId()` makes the difference loud.
   */
  public function getId(): int;

  /**
   * Returns a closure-based "with"-block: runs the body, then closes the bot session on exit.
   * Mirrors upstream Bot.context(auto_close=True) at client/bot.py:357-369.
   */
  public function context(bool $autoClose = true): Closure;

  /** Fiber-local "current bot" accessor; ported from aiogram/utils/mixins.py ContextInstanceMixin. */
  public static function current(): ?Bot;

  public static function setCurrent(?Bot $bot): void;

  public function me(): User;

  public function downloadFile(File|string $fileOrPath, mixed $destination = null, int $chunkSize = 65536): ?string;

  public function download(Downloadable|string $object, mixed $destination = null, int $chunkSize = 65536): ?string;
}
