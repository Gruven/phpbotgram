<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;

/**
 * Removes the current profile photo of a managed business account. Requires the can_edit_profile_photo business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#removebusinessaccountprofilephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class RemoveBusinessAccountProfilePhoto extends TelegramMethod
{
  public const string ApiMethod = 'removeBusinessAccountProfilePhoto';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly ?bool $isPublic = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
