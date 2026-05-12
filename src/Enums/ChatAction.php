<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Enums;

/**
 * This object represents bot actions.
 *
 * Choose one, depending on what the user is about to receive:
 *
 * - typing for text messages,
 * - upload_photo for photos,
 * - record_video or upload_video for videos,
 * - record_voice or upload_voice for voice notes,
 * - upload_document for general files,
 * - choose_sticker for stickers,
 * - find_location for location data,
 * - record_video_note or upload_video_note for video notes.
 *
 * Source: https://core.telegram.org/bots/api#sendchataction
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
enum ChatAction: string
{
  case Typing = 'typing';
  case UploadPhoto = 'upload_photo';
  case RecordVideo = 'record_video';
  case UploadVideo = 'upload_video';
  case RecordVoice = 'record_voice';
  case UploadVoice = 'upload_voice';
  case UploadDocument = 'upload_document';
  case ChooseSticker = 'choose_sticker';
  case FindLocation = 'find_location';
  case RecordVideoNote = 'record_video_note';
  case UploadVideoNote = 'upload_video_note';
}
