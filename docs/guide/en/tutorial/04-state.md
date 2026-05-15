# Adding state

Some flows need to remember where the user is. phpbotgram's
[`FsmContext`](https://api.phpbotgram.local/Gruven-PhpBotGram-Fsm-FsmContext.html)
provides per-user/per-chat key-value storage; the
[`StateFilter`](https://api.phpbotgram.local/Gruven-PhpBotGram-Filters-StateFilter.html)
gates handlers on the current state name.

This lesson builds a two-step "register" flow without using scenes
(those land in the [scene how-to](../how-to/scenes-wizard.md)).

## Inline FSM

```php
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Filters\StateFilter;
use Gruven\PhpBotGram\Fsm\FsmContext;
use Gruven\PhpBotGram\Types\Message;

$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $state->setState('register:name');
        $event->answer('What is your name?')->emit();
    },
    filters: [new Command('register'), new StateFilter(null)],
);

$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $state->updateData(['name' => $event->text ?? '']);
        $state->setState('register:age');
        $event->answer('How old are you?')->emit();
    },
    filters: [new StateFilter('register:name')],
);

$dispatcher->message->register(
    static function (Message $event, FsmContext $state): void {
        $data = $state->getData();
        $data['age'] = $event->text ?? '';
        $event->answer("Registered: {$data['name']}, age {$data['age']}.")->emit();
        $state->clear();
    },
    filters: [new StateFilter('register:age')],
);
```

The framework injects `FsmContext $state` automatically — the parameter
name `state` is the kwarg key the dispatcher binds. `setState(null)`
matches handlers gated on `StateFilter(null)` (i.e. "no active state").

## Next step

[Deploy to production →](05-deployment.md)
