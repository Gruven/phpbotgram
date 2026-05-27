<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils;

/**
 * Telegram deep-link types.
 *
 * Deviation from upstream: upstream `deep_linking.py` accepts an arbitrary
 * `link_type: str` parameter.  This port uses a backed enum to make the
 * parameter type-safe at the call site and to document the supported values.
 */
enum DeepLinkType: string
{
  case Start = 'start';
  case StartGroup = 'startgroup';
  case StartApp = 'startapp';
}
