<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\BotDefault;

/**
 * Describes the options used for link preview generation.
 *
 * Source: https://core.telegram.org/bots/api#linkpreviewoptions
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class LinkPreviewOptions extends TelegramObject
{
  public function __construct(
    public readonly bool|BotDefault|null $isDisabled = new BotDefault('link_preview_is_disabled'),
    public readonly ?string $url = null,
    public readonly bool|BotDefault|null $preferSmallMedia = new BotDefault('link_preview_prefer_small_media'),
    public readonly bool|BotDefault|null $preferLargeMedia = new BotDefault('link_preview_prefer_large_media'),
    public readonly bool|BotDefault|null $showAboveText = new BotDefault('link_preview_show_above_text'),
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
