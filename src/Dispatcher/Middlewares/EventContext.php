<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Dispatcher\Middlewares;

use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\User;

/**
 * Readonly DTO carrying the user/chat/thread context extracted from any
 * incoming update by `UserContextMiddleware` (mirror of
 * `aiogram.dispatcher.middlewares.user_context.EventContext`).
 *
 * Handlers and filters can declare an `EventContext $eventContext` parameter
 * and the `CallableObject` reflection step will bind it to the
 * `event_context` kwarg the middleware injects.
 *
 * Legacy aliases `event_from_user`, `event_chat`, and `event_thread_id` are
 * written alongside the full context for backward compatibility — see
 * `UserContextMiddleware::__invoke`.
 *
 * The `businessConnectionId` field is intentionally **not** exposed as a
 * top-level kwarg: upstream's deprecated `event_business_connection_id`
 * TypedDict key is documentation residue, not populated by the live
 * middleware. Callers must reach into `$eventContext->businessConnectionId`
 * to read it.
 */
final readonly class EventContext
{
  public function __construct(
    public ?Chat $chat = null,
    public ?User $user = null,
    public ?int $threadId = null,
    public ?string $businessConnectionId = null,
  ) {}

  public function userId(): ?int
  {
    return $this->user?->id;
  }

  public function chatId(): ?int
  {
    return $this->chat?->id;
  }
}
