#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Telegram Stars payment — port of upstream `aiogram/examples/stars_invoice.py`.
 *
 * What this demonstrates:
 *   - Sending an invoice with `currency: 'XTR'` (Telegram Stars).
 *   - Answering `pre_checkout_query` to confirm the purchase.
 *   - Handling `successful_payment` inside a message update.
 *   - LabeledPrice with `amount` in the smallest currency unit (1 Star = 1 unit).
 *
 * Run:
 *   BOT_TOKEN=123:abc php examples/stars_invoice.php
 *
 * Note: the bot must have payments enabled via @BotFather.
 */

require __DIR__ . '/../vendor/autoload.php';

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Dispatcher\Dispatcher;
use Gruven\PhpBotGram\Dispatcher\PollingOptions;
use Gruven\PhpBotGram\Filters\Command;
use Gruven\PhpBotGram\Methods\AnswerPreCheckoutQuery;
use Gruven\PhpBotGram\Methods\SendInvoice;
use Gruven\PhpBotGram\Types\LabeledPrice;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\PreCheckoutQuery;

$token = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if ($token === '') {
  fwrite(STDERR, "BOT_TOKEN env var is required.\n");

  exit(1);
}

$bot = new Bot($token);
$dispatcher = new Dispatcher();

// /buy — send a Telegram Stars invoice.
$dispatcher->message->register(
  static function (Message $event, Bot $bot): void {
    $chatId = $event->chat->id;
    $bot(new SendInvoice(
      chatId: $chatId,
      title: 'Premium Feature',
      description: 'Unlock the premium feature for 1 Telegram Star.',
      payload: 'premium_feature_payload',
      currency: 'XTR',    // Telegram Stars
      prices: [
        new LabeledPrice(label: 'Premium Feature', amount: 1),
      ],
    ));
  },
  filters: [new Command('buy')],
);

// Pre-checkout query — must be answered within 10 seconds.
$dispatcher->preCheckoutQuery->register(
  static function (PreCheckoutQuery $event, Bot $bot): void {
    // Validate the payload and approve or reject.
    if ($event->invoicePayload === 'premium_feature_payload') {
      $bot(new AnswerPreCheckoutQuery(
        preCheckoutQueryId: $event->id,
        ok: true,
      ));
    } else {
      $bot(new AnswerPreCheckoutQuery(
        preCheckoutQueryId: $event->id,
        ok: false,
        errorMessage: 'Unknown product.',
      ));
    }
  },
);

// Successful payment — fires as a special message type.
$dispatcher->message->register(
  static function (Message $event): void {
    $payment = $event->successfulPayment;

    if ($payment === null) {
      return;
    }
    $stars = $payment->totalAmount;
    $event->answer(
      "Thank you! Payment of {$stars} Star(s) received. "
        . 'Your premium feature is now active.',
    )->emit();
  },
);

fwrite(STDOUT, "Stars invoice bot starting...\n");
$dispatcher->runPolling(new PollingOptions(), $bot);
