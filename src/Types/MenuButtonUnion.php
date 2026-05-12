<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see MenuButton} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class MenuButtonUnion
{
  /**
   * @return list<class-string<MenuButton>>
   */
  public static function members(): array
  {
    return [
      MenuButtonCommands::class,
      MenuButtonWebApp::class,
      MenuButtonDefault::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): MenuButton
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'commands' => Serializer::load(MenuButtonCommands::class, $payload, $bot),
      'web_app' => Serializer::load(MenuButtonWebApp::class, $payload, $bot),
      'default' => Serializer::load(MenuButtonDefault::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown MenuButton type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
