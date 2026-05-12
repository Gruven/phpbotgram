<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Use this method to send answers to callback queries sent from inline keyboards. The answer will be displayed to the user as a notification at the top of the chat screen or as an alert. On success, True is returned.
 * Alternatively, the user can be redirected to the specified Game URL. For this option to work, you must first create a game for your bot via @BotFather and accept the terms. Otherwise, you may use links like t.me/your_bot?start=XXXX that open your bot with a parameter.
 *
 * Source: https://core.telegram.org/bots/api#answercallbackquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class AnswerCallbackQuery extends TelegramMethod
{
  public const string ApiMethod = 'answerCallbackQuery';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $callbackQueryId,
    public readonly ?string $text = null,
    public readonly ?bool $showAlert = null,
    public readonly ?string $url = null,
    public readonly ?int $cacheTime = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
