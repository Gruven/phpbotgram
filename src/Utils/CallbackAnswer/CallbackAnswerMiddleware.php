<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\CallbackAnswer;

use Closure;
use Gruven\PhpBotGram\Dispatcher\Event\HandlerObject;
use Gruven\PhpBotGram\Dispatcher\Middlewares\BaseMiddleware;
use Gruven\PhpBotGram\Types\CallbackQuery;

/**
 * Dispatcher-side middleware that automatically answers callback queries.
 *
 * Mirrors the `CallbackAnswerMiddleware` class from upstream
 * `aiogram/utils/callback_answer.py`.
 *
 * For every incoming {@see CallbackQuery} event the middleware builds a
 * {@see CallbackAnswer} DTO from its own constructor defaults, optionally
 * overridden by per-handler flags (`flags: ['callback_answer' => [...]]`),
 * and injects it into `$data['callback_answer']` so handlers can further
 * customise the response.
 *
 * Two modes are available:
 *
 * - **Post mode** (default, `pre = false`): the `answerCallbackQuery` API
 *   call is made *after* the handler returns, in a `finally` block. This
 *   ensures the query is always answered even if the handler throws.
 * - **Pre mode** (`pre = true`): the API call is made *before* the handler
 *   runs, giving the user instant feedback on slow handlers.
 *
 * Setting `$callbackAnswer->disabled = true` (or calling `->disable()`) inside
 * the handler suppresses the automatic answer entirely — the handler then owns
 * the responsibility of calling `answerCallbackQuery` manually.
 *
 * Handler-flag schema:
 *
 * ```php
 * $router->callbackQuery->register($handler, flags: [
 *     'callback_answer' => [
 *         'pre'        => true,
 *         'disabled'   => false,
 *         'text'       => 'Processing…',
 *         'show_alert' => false,
 *         'url'        => null,
 *         'cache_time' => 0,
 *     ],
 * ]);
 * ```
 *
 * Any key may be omitted; absent keys fall back to the middleware defaults.
 *
 * Non-CallbackQuery events are passed through unchanged.
 */
final class CallbackAnswerMiddleware extends BaseMiddleware
{
  public const string FLAG_NAME = 'callback_answer';

  public function __construct(
    private readonly bool $pre = false,
    private readonly ?string $text = null,
    private readonly ?bool $showAlert = null,
    private readonly ?string $url = null,
    private readonly ?int $cacheTime = null,
  ) {}

  /**
   * @param Closure(object, array<string, mixed>): mixed $handler
   * @param array<string, mixed> $data
   */
  public function __invoke(Closure $handler, object $event, array $data): mixed
  {
    if (!$event instanceof CallbackQuery) {
      return $handler($event, $data);
    }

    $properties = $this->resolveFlag($data);
    $callbackAnswer = $this->constructCallbackAnswer($properties);
    $data[self::FLAG_NAME] = $callbackAnswer;

    if (!$callbackAnswer->disabled && $callbackAnswer->isAnswered()) {
      // Pre-mode: answer BEFORE the handler runs.
      $this->sendAnswer($event, $callbackAnswer);
    }

    try {
      return $handler($event, $data);
    } finally {
      if (!$callbackAnswer->disabled && !$callbackAnswer->isAnswered()) {
        // Post-mode: answer AFTER the handler returns (or throws).
        $this->sendAnswer($event, $callbackAnswer);
      }
    }
  }

  // -------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------

  /**
   * Returns the raw `callback_answer` flag value from the handler object, or
   * `null` when the flag is absent / the handler object is not present.
   *
   * @param array<string, mixed> $data
   *
   * @return null|mixed
   */
  private function resolveFlag(array $data): mixed
  {
    $handler = $data['handler'] ?? null;

    if (!$handler instanceof HandlerObject) {
      return null;
    }

    return $handler->flags[self::FLAG_NAME] ?? null;
  }

  /**
   * Build a {@see CallbackAnswer} by merging middleware defaults with any
   * per-handler overrides supplied via the `callback_answer` flag.
   *
   * @param mixed $properties Raw flag value (expected to be an associative
   *                          array when set, otherwise ignored).
   */
  private function constructCallbackAnswer(mixed $properties): CallbackAnswer
  {
    $pre = $this->pre;
    $disabled = false;
    $text = $this->text;
    $showAlert = $this->showAlert;
    $url = $this->url;
    $cacheTime = $this->cacheTime;

    if (is_array($properties)) {
      $pre = is_bool($properties['pre'] ?? null) ? $properties['pre'] : $pre;
      $disabled = is_bool($properties['disabled'] ?? null) ? $properties['disabled'] : $disabled;
      $text = is_string($properties['text'] ?? null) ? $properties['text'] : $text;
      $showAlert = is_bool($properties['show_alert'] ?? null) ? $properties['show_alert'] : $showAlert;
      $url = is_string($properties['url'] ?? null) ? $properties['url'] : $url;
      $cacheTime = is_int($properties['cache_time'] ?? null) ? $properties['cache_time'] : $cacheTime;
    }

    // When $pre is true the DTO is constructed with answered=true. The
    // pre-answer branch in __invoke then sends the call; the finally-block
    // sees isAnswered()=true and correctly skips a second send.
    return new CallbackAnswer(
      answered: $pre,
      disabled: $disabled,
      text: $text,
      showAlert: $showAlert,
      url: $url,
      cacheTime: $cacheTime,
    );
  }

  /**
   * Calls `answerCallbackQuery` on the event and marks the DTO as answered.
   */
  private function sendAnswer(CallbackQuery $event, CallbackAnswer $callbackAnswer): void
  {
    $event->answer(
      text: $callbackAnswer->text,
      showAlert: $callbackAnswer->showAlert,
      url: $callbackAnswer->url,
      cacheTime: $callbackAnswer->cacheTime,
    )->emit();
    $callbackAnswer->markAnswered();
  }
}
