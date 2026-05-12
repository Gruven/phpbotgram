<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

final class LinkPreviewOptions extends TelegramObject
{
    public function __construct(
        public readonly ?bool $isDisabled = null,
        public readonly ?string $url = null,
        public readonly ?bool $preferSmallMedia = null,
        public readonly ?bool $preferLargeMedia = null,
        public readonly ?bool $showAboveText = null,
        ?Bot $bot = null,
    ) {
        parent::__construct($bot);
    }
}
