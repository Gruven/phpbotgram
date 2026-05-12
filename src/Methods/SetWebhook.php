<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Methods;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Types\InputFile;

/**
 * Use this method to specify a URL and receive incoming updates via an outgoing webhook. Whenever there is an update for the bot, we will send an HTTPS POST request to the specified URL, containing a JSON-serialized Update. In case of an unsuccessful request (a request with response HTTP status code different from 2XY), we will repeat the request and give up after a reasonable amount of attempts. Returns True on success.
 * If you'd like to make sure that the webhook was set by you, you can specify secret data in the parameter secret_token. If specified, the request will contain a header 'X-Telegram-Bot-Api-Secret-Token' with the secret token as content.
 * Notes
 * 1. You will not be able to receive updates using getUpdates for as long as an outgoing webhook is set up.
 * 2. To use a self-signed certificate, you need to upload your public key certificate using certificate parameter. Please upload as InputFile, sending a String will not work.
 * 3. Ports currently supported for webhooks: 443, 80, 88, 8443.
 * If you're having any trouble setting up webhooks, please check out this amazing guide to webhooks.
 *
 * Source: https://core.telegram.org/bots/api#setwebhook
 *
 * @generated do not edit; regenerate via `make regenerate`
 *
 * @extends TelegramMethod<bool>
 */
final class SetWebhook extends TelegramMethod
{
  public const string ApiMethod = 'setWebhook';
  public const string ReturnsType = 'bool';

  public function __construct(
    public readonly string $url,
    public readonly ?InputFile $certificate = null,
    public readonly ?string $ipAddress = null,
    public readonly ?int $maxConnections = null,
    /** @var list<string> */
    public readonly ?array $allowedUpdates = null,
    public readonly ?bool $dropPendingUpdates = null,
    public readonly ?string $secretToken = null,
    ?Bot $bot = null,
  ) {
    parent::__construct($bot);
  }
}
