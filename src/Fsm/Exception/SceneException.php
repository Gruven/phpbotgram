<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Exception;

use RuntimeException;

/**
 * Thrown when a scene operation is attempted on an uninitialised wizard.
 *
 * Mirrors `SceneException` (`aiogram/fsm/scene.py`). The most common cause
 * is calling `SceneWizard::onAction()` before `$wizard->scene` has been
 * populated post-construction.
 */
final class SceneException extends RuntimeException {}
