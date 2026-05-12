<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * One Telegram API method as resolved from `.butcher/schema/schema.json`
 * plus per-method patches (`default.yml`, `replace.yml`).
 *
 * - `$returns` holds the raw return-type sentence as it appears in the
 *   method's description; downstream `TypeResolver` normalises it.
 *   Empty string when the description has no explicit "returns" phrase.
 * - `$parsedReturning` carries the structured `returning.parsed_type` block
 *   from `replace.yml` (the authoritative override). Downstream resolvers
 *   prefer this over heuristically parsing `$returns` whenever non-null.
 * - `$defaults` is the raw `wire_param_name => bot_default_field_name`
 *   mapping from `default.yml` (or `[]` if absent). Threading these into
 *   `new BotDefault(...)` calls is Task 2.8's job.
 */
final readonly class MethodEntity
{
  /**
   * @param list<AnnotationEntity> $annotations
   * @param array<string, string> $defaults
   * @param null|array<string, mixed> $parsedReturning
   */
  public function __construct(
    public string $name,
    public string $description,
    public array $annotations,
    public string $returns,
    public array $defaults = [],
    public ?array $parsedReturning = null,
  ) {}
}
