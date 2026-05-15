# Test bots with the MockedSession

## When to use this

Unit tests should not call the real Telegram API. `MockedSession`
captures every outgoing `TelegramMethod`, plays back canned responses,
and lets you assert on call order, payload, and timeout — all without
a network. Pair it with `RecordingDispatcher` to verify dispatcher
fall-through paths.

## Solution

```php
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\SendMessage;
use Gruven\PhpBotGram\Tests\Support\MockedSession;
use Gruven\PhpBotGram\Types\User;

$session = new MockedSession();
$session->addResult(new Response(ok: true, result: new User(
    id: 1, isBot: true, firstName: 'TestBot', username: 'testbot',
)));

$bot = new Bot('123:abc', session: $session);

$bot->sendMessage(chatId: 100, text: 'hi');

$request = $session->getRequest();
assert($request instanceof SendMessage);
assert($request->chatId === 100);
```

[`MockedSession`](https://api.phpbotgram.local/Gruven-PhpBotGram-Client-Session-BaseSession.html)
extends
`BaseSession` and records every `makeRequest` call into a FIFO queue;
`getRequest()` pops the next captured method in dispatch order.
Queue canned responses with `addResult()` before the call site; if
`ok: false`, the session routes through `checkResponse` so typed
exception mapping is exercised.

## Pitfalls

- Responses are FIFO. Queueing two responses then calling once leaves
  one unconsumed for the next test — reset the session between tests.
- `Response::result` is typed `mixed`, but the production `BaseSession`
  builds typed instances. Cast carefully in assertions; helpers like
  `assertInstanceOf` are safer than `===`.
- `streamContent` returns the canned bytes registered in
  `$cannedStreamBodies` keyed by URL. Forgetting the entry returns an
  empty `ReadableBuffer`. See
  [Bot and Session](../concepts/bot-and-session.md) for the seam.
