<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\CallbackAnswer;

use Gruven\PhpBotGram\Exceptions\CallbackAnswerException;

/**
 * Mutable config DTO that carries the answer parameters for a single
 * {@see CallbackAnswerMiddleware} invocation.
 *
 * Mirrors the `CallbackAnswer` helper class from upstream
 * `aiogram/utils/callback_answer.py`.
 *
 * Once {@see markAnswered()} has been called the object becomes effectively
 * frozen — any subsequent setter call throws {@see CallbackAnswerException}.
 * This invariant prevents accidentally mutating answer parameters after the
 * `answerCallbackQuery` API call has already been sent.
 *
 * Typical usage inside a handler:
 *
 * ```php
 * public function myHandler(CallbackQuery $query, CallbackAnswer $callbackAnswer): void
 * {
 *     // Customise the answer text before the middleware sends it.
 *     $callbackAnswer->text = 'Done!';
 *     $callbackAnswer->showAlert = true;
 *
 *     // … handler logic …
 *
 *     // Optionally disable the auto-answer (middleware will skip it).
 *     $callbackAnswer->disable();
 * }
 * ```
 */
final class CallbackAnswer
{
  // -------------------------------------------------------------------
  // Backing fields for guarded properties
  // -------------------------------------------------------------------

  private bool $disabledValue;
  private ?string $textValue;
  private ?bool $showAlertValue;
  private ?string $urlValue;
  private ?int $cacheTimeValue;

  // -------------------------------------------------------------------
  // Guarded property hooks (PHP 8.4+)
  // -------------------------------------------------------------------

  /** Whether answering has been suppressed for this handler invocation. */
  public bool $disabled {
    get => $this->disabledValue;
    set {
      if ($this->answered) {
        throw new CallbackAnswerException("Can't change disabled state after answer");
      }

      $this->disabledValue = $value;
    }
  }

  /** Optional notification text shown to the user (≤ 200 chars). */
  public ?string $text {
    get => $this->textValue;
    set {
      if ($this->answered) {
        throw new CallbackAnswerException("Can't change text after answer");
      }

      $this->textValue = $value;
    }
  }

  /** When `true`, the answer is shown as an alert popup instead of a toast. */
  public ?bool $showAlert {
    get => $this->showAlertValue;
    set {
      if ($this->answered) {
        throw new CallbackAnswerException("Can't change showAlert after answer");
      }

      $this->showAlertValue = $value;
    }
  }

  /** Deep-link URL to open (for game callbacks). */
  public ?string $url {
    get => $this->urlValue;
    set {
      if ($this->answered) {
        throw new CallbackAnswerException("Can't change url after answer");
      }

      $this->urlValue = $value;
    }
  }

  /** Seconds the client should cache this answer (0–86400). */
  public ?int $cacheTime {
    get => $this->cacheTimeValue;
    set {
      if ($this->answered) {
        throw new CallbackAnswerException("Can't change cacheTime after answer");
      }

      $this->cacheTimeValue = $value;
    }
  }

  // -------------------------------------------------------------------
  // Constructor
  // -------------------------------------------------------------------

  /**
   * @param bool $answered Internal flag. The middleware passes `true`
   *                       here when `pre=true` so the pre-send counts as
   *                       "already answered" and the finally-block skips
   *                       a second send.
   * @param bool $disabled When `true`, the middleware skips answering.
   * @param ?string $text See {@see $text}.
   * @param ?bool $showAlert See {@see $showAlert}.
   * @param ?string $url See {@see $url}.
   * @param ?int $cacheTime See {@see $cacheTime}.
   */
  public function __construct(
    private bool $answered,
    bool $disabled = false,
    ?string $text = null,
    ?bool $showAlert = null,
    ?string $url = null,
    ?int $cacheTime = null,
  ) {
    $this->disabledValue = $disabled;
    $this->textValue = $text;
    $this->showAlertValue = $showAlert;
    $this->urlValue = $url;
    $this->cacheTimeValue = $cacheTime;
  }

  // -------------------------------------------------------------------
  // Public API
  // -------------------------------------------------------------------

  /**
   * Convenience helper — equivalent to `$this->disabled = true` but reads
   * more naturally from handler code.
   *
   * Guarded by the `$disabled` setter: throws if the answer was already sent.
   */
  public function disable(): void
  {
    $this->disabled = true;
  }

  /**
   * Returns `true` once the `answerCallbackQuery` API call has been made (or
   * when the DTO was constructed with `$answered = true` for pre-mode).
   */
  public function isAnswered(): bool
  {
    return $this->answered;
  }

  /**
   * Called by {@see CallbackAnswerMiddleware} after the API call completes.
   * Idempotent — subsequent calls are a no-op.
   *
   * After this point, all property setters will throw
   * {@see CallbackAnswerException}.
   */
  public function markAnswered(): void
  {
    $this->answered = true;
  }
}
