<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see RevenueWithdrawalState} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RevenueWithdrawalStateUnion
{
  /**
   * @return list<class-string<RevenueWithdrawalState>>
   */
  public static function members(): array
  {
    return [
      RevenueWithdrawalStatePending::class,
      RevenueWithdrawalStateSucceeded::class,
      RevenueWithdrawalStateFailed::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): RevenueWithdrawalState
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'pending' => Serializer::load(RevenueWithdrawalStatePending::class, $payload, $bot),
      'succeeded' => Serializer::load(RevenueWithdrawalStateSucceeded::class, $payload, $bot),
      'failed' => Serializer::load(RevenueWithdrawalStateFailed::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown RevenueWithdrawalState type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
