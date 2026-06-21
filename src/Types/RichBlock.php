<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;

/**
 * This object represents a block in a rich formatted message. Currently, it can be any of the following types:
 *  - RichBlockParagraph
 *  - RichBlockSectionHeading
 *  - RichBlockPreformatted
 *  - RichBlockFooter
 *  - RichBlockDivider
 *  - RichBlockMathematicalExpression
 *  - RichBlockAnchor
 *  - RichBlockList
 *  - RichBlockBlockQuotation
 *  - RichBlockPullQuotation
 *  - RichBlockCollage
 *  - RichBlockSlideshow
 *  - RichBlockTable
 *  - RichBlockDetails
 *  - RichBlockMap
 *  - RichBlockAnimation
 *  - RichBlockAudio
 *  - RichBlockPhoto
 *  - RichBlockVideo
 *  - RichBlockVoiceNote
 *  - RichBlockThinking
 *
 * Source: https://core.telegram.org/bots/api#richblock
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
abstract class RichBlock extends TelegramObject
{
  public function __construct(
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
