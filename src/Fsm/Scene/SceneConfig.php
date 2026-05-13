<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Scene;

/**
 * Immutable configuration record for a single scene class.
 *
 * Mirrors `SceneConfig` (`aiogram/fsm/scene.py:186-205`):
 *
 * ```python
 *
 * @dataclass
 * class SceneConfig:
 *     state: str | None
 *     handlers: list[HandlerContainer]
 *     actions: dict[SceneAction, dict[str, CallableObject]]
 *     reset_data_on_enter: bool | None = None
 *     reset_history_on_enter: bool | None = None
 *     callback_query_without_state: bool | None = None
 * ```
 *
 * The `attrs_resolver` field from upstream is a Python-specific class
 * introspection helper; it has no equivalent in the PHP port (the PHP
 * attribute system is used instead) and is therefore omitted.
 */
final readonly class SceneConfig
{
  /**
   * @param ?string $state The FSM state name for this scene, or `null`.
   * @param list<HandlerContainer> $handlers All registered handler containers.
   * @param array<string, array<string, callable>> $actions
   *                                                        Lifecycle action handlers keyed first by `SceneAction` case name
   *                                                        (e.g. `'Enter'`), then by the Telegram update-type string
   *                                                        (e.g. `'message'`), with a callable as the leaf value.
   * @param ?bool $resetDataOnEnter When `true`, FSM data is cleared on enter.
   * @param ?bool $resetHistoryOnEnter When `true`, scene history is cleared on enter.
   * @param ?bool $callbackQueryWithoutState Allow callback_query without state.
   */
  public function __construct(
    public ?string $state,
    /** @var list<HandlerContainer> */
    public array $handlers,
    /** @var array<string, array<string, callable>> */
    public array $actions,
    public ?bool $resetDataOnEnter = null,
    public ?bool $resetHistoryOnEnter = null,
    public ?bool $callbackQueryWithoutState = null,
  ) {}
}
