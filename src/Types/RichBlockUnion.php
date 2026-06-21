<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Client\Serializer;
use Gruven\PhpBotGram\Exceptions\ClientDecodeException;
use RuntimeException;

/**
 * Discriminator resolver for the {@see RichBlock} union.
 *
 * Wire discriminator: `type`.
 *
 * @generated do not edit; regenerate via `make regenerate`
 */
final class RichBlockUnion
{
  /**
   * @return list<class-string<RichBlock>>
   */
  public static function members(): array
  {
    return [
      RichBlockParagraph::class,
      RichBlockSectionHeading::class,
      RichBlockPreformatted::class,
      RichBlockFooter::class,
      RichBlockDivider::class,
      RichBlockMathematicalExpression::class,
      RichBlockAnchor::class,
      RichBlockList::class,
      RichBlockBlockQuotation::class,
      RichBlockPullQuotation::class,
      RichBlockCollage::class,
      RichBlockSlideshow::class,
      RichBlockTable::class,
      RichBlockDetails::class,
      RichBlockMap::class,
      RichBlockAnimation::class,
      RichBlockAudio::class,
      RichBlockPhoto::class,
      RichBlockVideo::class,
      RichBlockVoiceNote::class,
      RichBlockThinking::class,
    ];
  }

  /**
   * @param array<string, mixed> $payload
   */
  public static function resolve(array $payload, ?Bot $bot = null): RichBlock
  {
    $discriminator = $payload['type'] ?? null;
    $resolved = match (is_string($discriminator) ? $discriminator : null) {
      'paragraph' => Serializer::load(RichBlockParagraph::class, $payload, $bot),
      'heading' => Serializer::load(RichBlockSectionHeading::class, $payload, $bot),
      'pre' => Serializer::load(RichBlockPreformatted::class, $payload, $bot),
      'footer' => Serializer::load(RichBlockFooter::class, $payload, $bot),
      'divider' => Serializer::load(RichBlockDivider::class, $payload, $bot),
      'mathematical_expression' => Serializer::load(RichBlockMathematicalExpression::class, $payload, $bot),
      'anchor' => Serializer::load(RichBlockAnchor::class, $payload, $bot),
      'list' => Serializer::load(RichBlockList::class, $payload, $bot),
      'blockquote' => Serializer::load(RichBlockBlockQuotation::class, $payload, $bot),
      'pullquote' => Serializer::load(RichBlockPullQuotation::class, $payload, $bot),
      'collage' => Serializer::load(RichBlockCollage::class, $payload, $bot),
      'slideshow' => Serializer::load(RichBlockSlideshow::class, $payload, $bot),
      'table' => Serializer::load(RichBlockTable::class, $payload, $bot),
      'details' => Serializer::load(RichBlockDetails::class, $payload, $bot),
      'map' => Serializer::load(RichBlockMap::class, $payload, $bot),
      'animation' => Serializer::load(RichBlockAnimation::class, $payload, $bot),
      'audio' => Serializer::load(RichBlockAudio::class, $payload, $bot),
      'photo' => Serializer::load(RichBlockPhoto::class, $payload, $bot),
      'video' => Serializer::load(RichBlockVideo::class, $payload, $bot),
      'voice_note' => Serializer::load(RichBlockVoiceNote::class, $payload, $bot),
      'thinking' => Serializer::load(RichBlockThinking::class, $payload, $bot),
      default => throw new ClientDecodeException(
        sprintf('Unknown RichBlock type: %s', var_export($discriminator, true)),
        new RuntimeException('Discriminator value not recognised'),
        $payload,
      ),
    };

    return $resolved;
  }
}
