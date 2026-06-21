#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Download files sent by users.
 *
 * What this demonstrates:
 *   - Reading file_id from incoming document/photo messages.
 *   - Resolving a file_id through Bot::download().
 *   - Streaming the Telegram file response into a local destination path.
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/file_download.php
 *
 * Then send the bot a document or photo.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Types\Message;

/**
 * @return null|array{fileId: string, label: string, filename: string}
 */
function downloadableFromMessage(Message $event): ?array
{
  if ($event->document !== null) {
    return [
      'fileId' => $event->document->fileId,
      'label' => 'document',
      'filename' => $event->document->fileName ?? 'document.bin',
    ];
  }

  $photos = $event->photo ?? [];

  if ($photos !== []) {
    $photo = $photos[array_key_last($photos)];

    return [
      'fileId' => $photo->fileId,
      'label' => 'photo',
      'filename' => "photo_{$photo->fileUniqueId}.jpg",
    ];
  }

  return null;
}

function safeFilename(string $filename): string
{
  $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($filename));
  $safe = trim((string)$safe, '._-');

  return $safe === '' ? 'download.bin' : $safe;
}

function ensureDownloadDirectory(): string
{
  $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpbotgram-downloads';

  if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    throw new RuntimeException("Failed to create download directory: {$dir}");
  }

  return $dir;
}

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

$dispatcher->message->register(static function (Message $event, Bot $bot): void {
  $downloadable = downloadableFromMessage($event);

  if ($downloadable === null) {
    $event->answer('Send a document or photo, and I will download it locally.')->emit();

    return;
  }

  $target = ensureDownloadDirectory()
    . DIRECTORY_SEPARATOR
    . date('Ymd-His') . '-' . safeFilename($downloadable['filename']);

  $bot->download($downloadable['fileId'], destination: $target);

  $size = filesize($target);
  $sizeText = $size === false ? 'unknown size' : "{$size} bytes";

  $event->answer(
    "Downloaded {$downloadable['label']} to:\n{$target}\n{$sizeText}",
  )->emit();
});

fwrite(STDOUT, "File-download bot starting...\n");
$dispatcher->runPolling(new PollingOptions(
  allowedUpdates: ['message'],
), $bot);
