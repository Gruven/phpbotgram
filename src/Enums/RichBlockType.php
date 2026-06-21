<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents a block in a rich formatted message.
 *
 * Source: https://core.telegram.org/bots/api#richblock
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum RichBlockType: string
{
  case Paragraph = 'paragraph';
  case Heading = 'heading';
  case Pre = 'pre';
  case Footer = 'footer';
  case Divider = 'divider';
  case MathematicalExpression = 'mathematical_expression';
  case Anchor = 'anchor';
  case List = 'list';
  case Blockquote = 'blockquote';
  case Pullquote = 'pullquote';
  case Collage = 'collage';
  case Slideshow = 'slideshow';
  case Table = 'table';
  case Details = 'details';
  case Map = 'map';
  case Animation = 'animation';
  case Audio = 'audio';
  case Photo = 'photo';
  case Video = 'video';
  case VoiceNote = 'voice_note';
  case Thinking = 'thinking';
}
