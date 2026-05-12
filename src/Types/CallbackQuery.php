<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\AnswerCallbackQuery;

/**
 * This object represents an incoming callback query from a callback button in an inline keyboard. If the button that originated the query was attached to a message sent by the bot, the field message will be present. If the button was attached to a message sent via the bot (in inline mode), the field inline_message_id will be present. Exactly one of the fields data or game_short_name will be present.
 * NOTE: After the user presses a callback button, Telegram clients will display a progress bar until you call answerCallbackQuery. It is, therefore, necessary to react by calling answerCallbackQuery even if no notification to the user is needed (e.g., without specifying any of the optional parameters).
 *
 * Source: https://core.telegram.org/bots/api#callbackquery
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class CallbackQuery extends TelegramObject
{
  /** @var array<string, string> */
  public const array WireNames = [
    'fromUser' => 'from',
  ];

  public function __construct(
    public readonly string $id,
    public readonly User $fromUser,
    public readonly string $chatInstance,
    public readonly ?MaybeInaccessibleMessage $message = null,
    public readonly ?string $inlineMessageId = null,
    public readonly ?string $data = null,
    public readonly ?string $gameShortName = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
  public function answer(
    ?string $text = null,
    ?bool $showAlert = null,
    ?string $url = null,
    ?int $cacheTime = null,
  ): AnswerCallbackQuery {
    return new AnswerCallbackQuery(
      callbackQueryId: $this->id,
      text: $text,
      showAlert: $showAlert,
      url: $url,
      cacheTime: $cacheTime,
      bot: $this->bot,
    );
  }
}
