# Send a media group (album)

## When to use this

Telegram caps an album at 10 photos/videos/audio/documents. Build the list incrementally with `MediaGroupBuilder` to get homogeneity checks and a fluent API; then send it via `sendMediaGroup`.

## Solution

```php
use Gruven\PhpBotGram\Types\FsInputFile;
use Gruven\PhpBotGram\Utils\MediaGroup\MediaGroupBuilder;

$builder = new MediaGroupBuilder(caption: 'Vacation 2026');
$builder
    ->addPhoto(new FsInputFile('/tmp/p1.jpg'))
    ->addPhoto(new FsInputFile('/tmp/p2.jpg'), caption: 'Sunset')
    ->addVideo(new FsInputFile('/tmp/clip.mp4'));

$bot->sendMediaGroup(
    chatId: $chatId,
    media: $builder->build(),
);
```

[`MediaGroupBuilder`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MediaGroup-MediaGroupBuilder.html) exposes `addPhoto`, `addVideo`, `addAudio`, `addDocument`. The builder-level `caption` is injected into the first item when `build()` runs; per-item captions take effect on items 2-10. The validator enforces Telegram's homogeneity rules: photos and videos can mix, audio and document groups must be homogeneous.

## Pitfalls

- The cap is hard at [`MediaGroupBuilder::MAX_MEDIA_GROUP_SIZE`](https://api.phpbotgram.local/Gruven-PhpBotGram-Utils-MediaGroup-MediaGroupBuilder.html) (10). The 11th `add*` throws `InvalidArgumentException`.
- Mixing types throws too — `addAudio` after `addPhoto` is rejected. Build separate groups and send them sequentially.
- `build()` returns immutable copies; calling it twice is safe and produces independent lists. The builder itself can be reused after build.
- See [Serialization](../concepts/serialization.md) for how each `InputMedia*` flattens to the wire payload.
