<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see TransactionPartner} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class TransactionPartnerUnion
{
  /**
   * @return list<class-string<TransactionPartner>>
   */
  public static function members(): array
  {
    return [
      TransactionPartnerUser::class,
      TransactionPartnerChat::class,
      TransactionPartnerAffiliateProgram::class,
      TransactionPartnerFragment::class,
      TransactionPartnerTelegramAds::class,
      TransactionPartnerTelegramApi::class,
      TransactionPartnerOther::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): TransactionPartner
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'user' => Serializer::load(TransactionPartnerUser::class, $payload, $bot),
      'chat' => Serializer::load(TransactionPartnerChat::class, $payload, $bot),
      'affiliate_program' => Serializer::load(TransactionPartnerAffiliateProgram::class, $payload, $bot),
      'fragment' => Serializer::load(TransactionPartnerFragment::class, $payload, $bot),
      'telegram_ads' => Serializer::load(TransactionPartnerTelegramAds::class, $payload, $bot),
      'telegram_api' => Serializer::load(TransactionPartnerTelegramApi::class, $payload, $bot),
      'other' => Serializer::load(TransactionPartnerOther::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown TransactionPartner type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
