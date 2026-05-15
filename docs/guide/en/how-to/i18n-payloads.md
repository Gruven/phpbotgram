# Internationalise message payloads

## When to use this

Bots that serve users in multiple locales need per-update translation
of replies, buttons, and dates. Telegram delivers the user's
`language_code`; the framework leaves locale selection to your code,
but `ext-intl` and `mbstring` make the implementation straightforward.

## Solution

```php
use Gruven\PhpBotGram\Filters\Filter;
use Gruven\PhpBotGram\Types\Message;

final class LocaleFilter extends Filter
{
    /** @param array<string, array<string, string>> $catalog */
    public function __construct(private readonly array $catalog) {}

    public function __invoke(object $event, mixed ...$kwargs): array|bool
    {
        if (!$event instanceof Message || $event->fromUser === null) {
            return false;
        }
        $lang = $event->fromUser->languageCode ?? 'en';
        $strings = $this->catalog[$lang] ?? $this->catalog['en'];
        return ['locale' => $lang, 'strings' => $strings];
    }
}

$dispatcher->message->register(
    static function (Message $event, string $locale, array $strings): void {
        $when = (new IntlDateFormatter(
            $locale,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::SHORT,
        ))->format(new DateTimeImmutable());

        $event->answer("{$strings['greet']} ({$when})")->emit();
    },
    filters: [new LocaleFilter(['en' => ['greet' => 'Hello'], 'fr' => ['greet' => 'Bonjour']])],
);
```

A locale filter that returns `['locale' => …, 'strings' => …]`
injects both keys into every handler — see
[`CallableObject`](https://api.phpbotgram.local/Gruven-PhpBotGram-Dispatcher-Event-CallableObject.html)
for the binding rules. Use `ext-intl`'s `IntlDateFormatter` and
`MessageFormatter` for plural/format-correct strings, and `mbstring`
(`mb_strtoupper`, `mb_substr`) when Latin-1 functions would mangle
Unicode.

## Pitfalls

- Telegram's `language_code` follows BCP 47 (`en`, `pt-br`,
  `zh-hans`). Strip the region (`explode('-', $lang)[0]`) before
  catalog lookup if your translations are language-only.
- `mbstring` defaults to internal encoding — set it explicitly with
  `mb_internal_encoding('UTF-8')` in your bootstrap to avoid
  surprises on hosts where `ini` defaults differ.
- Reply markup buttons aren't translated automatically — pass the
  localised label when building the `InlineKeyboardButton`. See
  [Keyboards](../concepts/keyboards.md) for the builder pattern.
