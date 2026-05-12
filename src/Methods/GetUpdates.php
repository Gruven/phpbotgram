<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\Update;

/**
 * Use this method to receive incoming updates using long polling (wiki). Returns an Array of Update objects.
 * Notes
 * 1. This method will not work if an outgoing webhook is set up.
 * 2. In order to avoid getting duplicate updates, recalculate offset after each server response.
 *
 * Source: https://core.telegram.org/bots/api#getupdates
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<list<Update>>
 */
final class GetUpdates extends TelegramMethod
{
  public const string ApiMethod = 'getUpdates';
  public const string ReturnsType = 'list:Update';

  public function __construct(
    public readonly ?int $offset = null,
    public readonly ?int $limit = null,
    public readonly ?int $timeout = null,
    /** @var list<string> */
    public readonly ?array $allowedUpdates = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
