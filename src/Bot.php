<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram;

use Gruven\PhpBotGram\Client\DefaultBotProperties;

/**
 * Phase 0 stub. Phase 1.6 regenerates with the full 176-method facade and the
 * real constructor (string $token, ?BaseSession $session = null, ?DefaultBotProperties $defaultProperties = null).
 */
class Bot
{
  public function __construct() {}

  /** Phase 1.3 shim — returns an empty DefaultBotProperties. Phase 1.6 wires the real field. */
  public function getDefaultProperties(): DefaultBotProperties
  {
    return new DefaultBotProperties();
  }
}
