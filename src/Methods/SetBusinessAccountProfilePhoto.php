<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputProfilePhoto;

/**
 * Changes the profile photo of a managed business account. Requires the can_edit_profile_photo business bot right. Returns True on success.
 *
 * Source: https://core.telegram.org/bots/api#setbusinessaccountprofilephoto
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetBusinessAccountProfilePhoto extends TelegramMethod
{
  public const string ApiMethod = 'setBusinessAccountProfilePhoto';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $businessConnectionId,
    public readonly InputProfilePhoto $photo,
    public readonly ?bool $isPublic = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
