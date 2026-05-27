<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm;

use Closure;
use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Dispatcher\Middlewares\EventContext;
use Gruven\PhpBotGram\Dispatcher\Middlewares\UserContextMiddleware;
use Gruven\PhpBotGram\Fsm\Storage\BaseEventIsolation;
use Gruven\PhpBotGram\Fsm\Storage\BaseStorage;
use Gruven\PhpBotGram\Fsm\Storage\StorageKey;

/**
 * Dispatcher-side middleware that resolves the FSM context for each incoming
 * update and injects `state`, `raw_state`, and `fsm_storage` into the kwarg
 * bag so that handlers and filters can access FSM state via reflection binding.
 *
 * Mirrors `aiogram.fsm.middleware.FSMContextMiddleware`
 * (`aiogram/fsm/middleware.py`).
 *
 * Upstream bugfix (https://github.com/aiogram/aiogram/issues/1317): the raw
 * state is loaded **after** the event-isolation lock is acquired, not before.
 * This prevents two concurrent fibers from reading a stale state value before
 * the other fiber has finished updating it.
 *
 * Key conventions (snake_case matching upstream wire keys):
 *   - `STATE_KEY`       = `'state'`
 *   - `RAW_STATE_KEY`   = `'raw_state'`
 *   - `FSM_STORAGE_KEY` = `'fsm_storage'`
 */
final class FsmContextMiddleware extends BaseMiddleware
{
  public const string STATE_KEY = 'state';
  public const string RAW_STATE_KEY = 'raw_state';
  public const string FSM_STORAGE_KEY = 'fsm_storage';

  public function __construct(
    public readonly BaseStorage $storage,
    private readonly BaseEventIsolation $eventsIsolation,
    private readonly FsmStrategy $strategy = FsmStrategy::UserInChat,
  ) {}

  /**
   * @param Closure(object, array<string, mixed>): mixed $handler
   * @param array<string, mixed> $data
   */
  public function __invoke(Closure $handler, object $event, array $data): mixed
  {
    /** @var Bot $bot */
    $bot = $data['bot'];

    $context = $this->resolveEventContext($bot, $data);

    // fsm_storage is always injected — even when there is no FSM context.
    $data[self::FSM_STORAGE_KEY] = $this->storage;

    if ($context !== null) {
      // Bugfix: acquire the lock BEFORE reading the state so that concurrent
      // fibers sharing the same key cannot observe a race on getState().
      $lock = $this->eventsIsolation->lock($context->key);

      try {
        $data[self::STATE_KEY] = $context;
        $data[self::RAW_STATE_KEY] = $context->getState();

        return $handler($event, $data);
      } finally {
        $lock->release();
      }
    }

    return $handler($event, $data);
  }

  /**
   * Derive the FsmContext for the current update by reading the EventContext
   * that `UserContextMiddleware` has already placed in `$data`.
   *
   * Falls through to `resolveContext` with the appropriate ids.
   *
   * @param array<string, mixed> $data
   */
  public function resolveEventContext(
    Bot $bot,
    array $data,
    string $destiny = StorageKey::DEFAULT_DESTINY,
  ): ?FsmContext {
    /** @var null|EventContext $eventContext */
    $eventContext = $data[UserContextMiddleware::EVENT_CONTEXT_KEY] ?? null;

    $chatId = $eventContext?->chatId();
    $userId = $eventContext?->userId();
    $threadId = $eventContext?->threadId;
    $businessConnectionId = $eventContext?->businessConnectionId;

    return $this->resolveContext(
      bot: $bot,
      chatId: $chatId,
      userId: $userId,
      threadId: $threadId,
      businessConnectionId: $businessConnectionId,
      destiny: $destiny,
    );
  }

  /**
   * Build an `FsmContext` from raw ids, applying the configured strategy.
   *
   * Mirrors `FSMContextMiddleware.resolve_context` (`middleware.py:62-92`).
   *
   * Fallback rules (upstream parity):
   * 1. If `$chatId` is null, use `$userId` as a substitute.
   * 2. If `$userId` is null and the strategy is `Chat` or `ChatTopic`,
   *    fall back to `$chatId` for the user slot.
   * 3. If both are null after fallbacks, return `null`.
   *
   * @param string $destiny Destiny tag; defaults to `StorageKey::DEFAULT_DESTINY`.
   */
  public function resolveContext(
    Bot $bot,
    ?int $chatId,
    ?int $userId,
    ?int $threadId = null,
    ?string $businessConnectionId = null,
    string $destiny = StorageKey::DEFAULT_DESTINY,
  ): ?FsmContext {
    if ($chatId === null) {
      $chatId = $userId;
    } elseif ($userId === null && ($this->strategy === FsmStrategy::Chat || $this->strategy === FsmStrategy::ChatTopic)) {
      // Chat-scoped strategies can mirror chatId into userId when the update
      // has no sender (e.g. channel posts).
      $userId = $chatId;
    }

    if ($chatId !== null && $userId !== null) {
      $applied = $this->strategy->apply($chatId, $userId, $threadId);

      return $this->getContext(
        bot: $bot,
        chatId: $applied['chatId'],
        userId: $applied['userId'],
        threadId: $applied['threadId'],
        businessConnectionId: $businessConnectionId,
        destiny: $destiny,
      );
    }

    return null;
  }

  /**
   * Construct a fresh `FsmContext` bound to an explicit `StorageKey`.
   *
   * Mirrors `FSMContextMiddleware.get_context` (`middleware.py:94-113`).
   *
   * @param string $destiny Destiny tag; defaults to `StorageKey::DEFAULT_DESTINY`.
   */
  public function getContext(
    Bot $bot,
    int $chatId,
    int $userId,
    ?int $threadId = null,
    ?string $businessConnectionId = null,
    string $destiny = StorageKey::DEFAULT_DESTINY,
  ): FsmContext {
    return new FsmContext(
      storage: $this->storage,
      key: new StorageKey(
        botId: $bot->getId(),
        chatId: $chatId,
        userId: $userId,
        threadId: $threadId,
        businessConnectionId: $businessConnectionId,
        destiny: $destiny,
      ),
    );
  }

  /**
   * Release all resources held by both the storage and the event-isolation
   * strategy.
   *
   * Mirrors `FSMContextMiddleware.close` (`middleware.py:115-117`).
   */
  public function close(): void
  {
    $this->storage->close();
    $this->eventsIsolation->close();
  }
}
