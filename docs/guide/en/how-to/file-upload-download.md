# Upload and download files

## When to use this

Send a photo from disk, a buffer, or a URL; pull a document the user sent back to local storage. The bot facade unifies the three upload modes and exposes `downloadFile`/`download` for the reverse trip.

## Solution

### Uploading files

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\BufferedInputFile;
use Gruven\PhpBotGram\Types\FsInputFile;
use Gruven\PhpBotGram\Types\UrlInputFile;

// Upload from disk.
$bot->sendPhoto(chatId: $chatId, photo: new FsInputFile('/tmp/cat.jpg'));

// Upload from memory.
$bot->sendDocument(
    chatId: $chatId,
    document: new BufferedInputFile($bytes, filename: 'report.pdf'),
);

// Upload from URL (Telegram fetches it).
$bot->sendPhoto(chatId: $chatId, photo: new UrlInputFile('https://example.com/x.jpg'));
```

### Downloading files

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Message;

// Download what the user sent.
$dispatcher->message->register(static function (Message $event, Bot $bot): void {
    if ($event->document !== null) {
        $bot->download($event->document->fileId, destination: '/tmp/in.bin');
    }
});
```

[`Bot::downloadFile`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-BotShortcuts.html) streams the response body — pass a path string to write to disk, a writable resource to fork the stream, or `null` to receive the full content as a string. `Bot::download` accepts a raw `file_id` and resolves it via `getFile` first.

The full runnable download version is [`examples/file_download.php`](https://github.com/Gruven/phpbotgram/blob/master/examples/file_download.php).

## Pitfalls

- `BufferedInputFile` holds the bytes in memory — fine for KB-scale reports, expensive for video. Prefer `FsInputFile` for anything over a few MB.
- `URLInputFile` makes Telegram fetch the URL; the server enforces a 5 MB limit for photos and 50 MB for documents. Larger files must be uploaded directly.
- Local file paths passed as a string to `downloadFile` go through `fopen('wb')` — the directory must exist and be writable, or the call throws `RuntimeException`.
- See [Serialization](../concepts/serialization.md) for how `InputFile` flavours map to multipart payloads.
