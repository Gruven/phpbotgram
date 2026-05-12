<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * Color of the topic icon in RGB format.
 *
 * Source: https://github.com/telegramdesktop/tdesktop/blob/991fe491c5ae62705d77aa8fdd44a79caf639c45/Telegram/SourceFiles/data/data_forum_topic.cpp#L51-L56
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum TopicIconColor: int
{
  case Blue = 0x6FB9F0;
  case Yellow = 0xFFD67E;
  case Violet = 0xCB86DB;
  case Green = 0x8EEE98;
  case Rose = 0xFF93B2;
  case Red = 0xFB6F5F;
}
