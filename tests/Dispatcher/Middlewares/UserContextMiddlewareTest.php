<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Dispatcher\Middlewares;

use Gruven\PhpBotGram\Dispatcher\Middlewares\EventContext;
use Gruven\PhpBotGram\Dispatcher\Middlewares\UserContextMiddleware;
use Gruven\PhpBotGram\Types\BusinessConnection;
use Gruven\PhpBotGram\Types\BusinessMessagesDeleted;
use Gruven\PhpBotGram\Types\CallbackQuery;
use Gruven\PhpBotGram\Types\Chat;
use Gruven\PhpBotGram\Types\ChatBoost;
use Gruven\PhpBotGram\Types\ChatBoostRemoved;
use Gruven\PhpBotGram\Types\ChatBoostSourcePremium;
use Gruven\PhpBotGram\Types\ChatBoostUpdated;
use Gruven\PhpBotGram\Types\ChatJoinRequest;
use Gruven\PhpBotGram\Types\ChatMemberMember;
use Gruven\PhpBotGram\Types\ChatMemberUpdated;
use Gruven\PhpBotGram\Types\ChosenInlineResult;
use Gruven\PhpBotGram\Types\Custom\DateTime;
use Gruven\PhpBotGram\Types\InaccessibleMessage;
use Gruven\PhpBotGram\Types\InlineQuery;
use Gruven\PhpBotGram\Types\Message;
use Gruven\PhpBotGram\Types\MessageReactionCountUpdated;
use Gruven\PhpBotGram\Types\MessageReactionUpdated;
use Gruven\PhpBotGram\Types\PaidMediaPurchased;
use Gruven\PhpBotGram\Types\Poll;
use Gruven\PhpBotGram\Types\PollAnswer;
use Gruven\PhpBotGram\Types\PreCheckoutQuery;
use Gruven\PhpBotGram\Types\ShippingAddress;
use Gruven\PhpBotGram\Types\ShippingQuery;
use Gruven\PhpBotGram\Types\TelegramObject;
use Gruven\PhpBotGram\Types\Update;
use Gruven\PhpBotGram\Types\User;
use PHPUnit\Framework\TestCase;

/**
 * Ports `aiogram/dispatcher/middlewares/user_context.py::UserContextMiddleware`.
 *
 * The upstream middleware dispatches on an Update's populated child slot
 * (Message, CallbackQuery, …) to build an `EventContext`, then injects four
 * standard kwargs into `$data`: `event_context`, `event_from_user`,
 * `event_chat`, `event_thread_id`. Our port also accepts the unwrapped child
 * directly so callers can resolve a context without rewrapping into an
 * Update.
 *
 * @internal
 *
 * @coversNothing
 */
final class UserContextMiddlewareTest extends TestCase
{
  public function testResolveContextFromMessageEvent(): void
  {
    $chat = new Chat(id: 100, type: 'private');
    $user = new User(id: 7, isBot: false, firstName: 'Ada');
    $message = new Message(
      messageId: 1,
      date: new DateTime('@0'),
      chat: $chat,
      messageThreadId: 5,
      fromUser: $user,
      businessConnectionId: 'bc-9',
      isTopicMessage: true,
    );

    $ctx = UserContextMiddleware::resolveContext($message);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertSame(5, $ctx->threadId);
    self::assertSame('bc-9', $ctx->businessConnectionId);
  }

  public function testResolveContextFromCallbackQueryWithMessage(): void
  {
    $chat = new Chat(id: 200, type: 'group');
    $user = new User(id: 9, isBot: false, firstName: 'Ben');
    $message = new Message(
      messageId: 11,
      date: new DateTime('@0'),
      chat: $chat,
      messageThreadId: 3,
      isTopicMessage: true,
      businessConnectionId: 'bc-cb',
    );
    $cq = new CallbackQuery(id: 'cq', fromUser: $user, chatInstance: 'inst', message: $message);

    $ctx = UserContextMiddleware::resolveContext($cq);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertSame(3, $ctx->threadId);
    self::assertSame('bc-cb', $ctx->businessConnectionId);
  }

  public function testResolveContextFromCallbackQueryWithoutMessage(): void
  {
    $user = new User(id: 12, isBot: false, firstName: 'Cara');
    $cq = new CallbackQuery(id: 'cq2', fromUser: $user, chatInstance: 'inst');

    $ctx = UserContextMiddleware::resolveContext($cq);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertNull($ctx->threadId);
    self::assertNull($ctx->businessConnectionId);
  }

  public function testResolveContextFromCallbackQueryWithInaccessibleMessage(): void
  {
    // InaccessibleMessage exposes only `chat` — no thread / business-connection
    // fields. Upstream guards both with `not isinstance(message, InaccessibleMessage)`.
    $chat = new Chat(id: 300, type: 'channel');
    $user = new User(id: 13, isBot: false, firstName: 'Dee');
    $inaccessible = new InaccessibleMessage(chat: $chat, messageId: 0, date: 0);
    $cq = new CallbackQuery(id: 'cq3', fromUser: $user, chatInstance: 'inst', message: $inaccessible);

    $ctx = UserContextMiddleware::resolveContext($cq);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertNull($ctx->threadId);
    self::assertNull($ctx->businessConnectionId);
  }

  public function testResolveContextUnwrapsUpdateMessage(): void
  {
    $chat = new Chat(id: 101, type: 'private');
    $user = new User(id: 21, isBot: false, firstName: 'Eve');
    $message = new Message(messageId: 2, date: new DateTime('@0'), chat: $chat, fromUser: $user);
    $update = new Update(updateId: 1, message: $message);

    $ctx = UserContextMiddleware::resolveContext($update);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertNull($ctx->threadId);
  }

  public function testResolveContextUnwrapsUpdateCallbackQuery(): void
  {
    $user = new User(id: 22, isBot: false, firstName: 'Finn');
    $cq = new CallbackQuery(id: 'cq', fromUser: $user, chatInstance: 'inst');
    $update = new Update(updateId: 2, callbackQuery: $cq);

    $ctx = UserContextMiddleware::resolveContext($update);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromBareUpdateReturnsEmptyContext(): void
  {
    $update = new Update(updateId: 999);
    $ctx = UserContextMiddleware::resolveContext($update);

    self::assertNull($ctx->chat);
    self::assertNull($ctx->user);
    self::assertNull($ctx->threadId);
    self::assertNull($ctx->businessConnectionId);
  }

  public function testResolveContextFromUnknownTelegramObjectReturnsEmpty(): void
  {
    // Chat is a TelegramObject but not an event the middleware handles —
    // mirrors the upstream fallback `return EventContext()`.
    $chat = new Chat(id: 1, type: 'private');
    $ctx = UserContextMiddleware::resolveContext($chat);

    self::assertNull($ctx->chat);
    self::assertNull($ctx->user);
  }

  public function testMessageIsTopicMessageFalseSuppressesThreadId(): void
  {
    $chat = new Chat(id: 1, type: 'supergroup');
    $message = new Message(
      messageId: 4,
      date: new DateTime('@0'),
      chat: $chat,
      messageThreadId: 77,
      isTopicMessage: false,
    );

    $ctx = UserContextMiddleware::resolveContext($message);

    self::assertNull($ctx->threadId, 'threadId must be null when isTopicMessage is false.');
  }

  public function testMessageIsTopicMessageNullSuppressesThreadId(): void
  {
    // Null is the schema default; only an explicit `true` activates the thread id.
    $chat = new Chat(id: 1, type: 'supergroup');
    $message = new Message(
      messageId: 4,
      date: new DateTime('@0'),
      chat: $chat,
      messageThreadId: 88,
    );

    $ctx = UserContextMiddleware::resolveContext($message);

    self::assertNull($ctx->threadId);
  }

  public function testResolveContextFromInlineQueryUserOnly(): void
  {
    $user = new User(id: 31, isBot: false, firstName: 'Gabe');
    $iq = new InlineQuery(id: 'i1', fromUser: $user, query: 'hello', offset: '');

    $ctx = UserContextMiddleware::resolveContext($iq);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromChosenInlineResultUserOnly(): void
  {
    $user = new User(id: 32, isBot: false, firstName: 'Hugo');
    $cir = new ChosenInlineResult(resultId: 'r1', fromUser: $user, query: 'q');

    $ctx = UserContextMiddleware::resolveContext($cir);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromShippingQueryUserOnly(): void
  {
    $user = new User(id: 33, isBot: false, firstName: 'Iris');
    $addr = new ShippingAddress(
      countryCode: 'GB',
      state: '',
      city: 'London',
      streetLine1: 'Baker St',
      streetLine2: '',
      postCode: 'NW1',
    );
    $sq = new ShippingQuery(id: 'sq', fromUser: $user, invoicePayload: 'pay', shippingAddress: $addr);

    $ctx = UserContextMiddleware::resolveContext($sq);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromPreCheckoutQueryUserOnly(): void
  {
    $user = new User(id: 34, isBot: false, firstName: 'Jay');
    $pq = new PreCheckoutQuery(
      id: 'pcq',
      fromUser: $user,
      currency: 'USD',
      totalAmount: 100,
      invoicePayload: 'pay',
    );

    $ctx = UserContextMiddleware::resolveContext($pq);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromPollAnswerUserAndChat(): void
  {
    $voterChat = new Chat(id: -100, type: 'group');
    $user = new User(id: 41, isBot: false, firstName: 'Kim');
    $pa = new PollAnswer(
      pollId: 'p',
      optionIds: [0],
      optionPersistentIds: ['a'],
      voterChat: $voterChat,
      user: $user,
    );

    $ctx = UserContextMiddleware::resolveContext($pa);

    self::assertSame($voterChat, $ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromChatMemberUpdated(): void
  {
    $chat = new Chat(id: -200, type: 'group');
    $user = new User(id: 42, isBot: false, firstName: 'Leo');
    $member = new ChatMemberMember(user: $user);
    $cmu = new ChatMemberUpdated(
      chat: $chat,
      fromUser: $user,
      date: new DateTime('@0'),
      oldChatMember: $member,
      newChatMember: $member,
    );

    $ctx = UserContextMiddleware::resolveContext($cmu);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromChatJoinRequest(): void
  {
    $chat = new Chat(id: -300, type: 'supergroup');
    $user = new User(id: 43, isBot: false, firstName: 'May');
    $jr = new ChatJoinRequest(chat: $chat, fromUser: $user, userChatId: 43, date: new DateTime('@0'));

    $ctx = UserContextMiddleware::resolveContext($jr);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromMessageReactionUpdated(): void
  {
    $chat = new Chat(id: -400, type: 'channel');
    $user = new User(id: 51, isBot: false, firstName: 'Nia');
    $mru = new MessageReactionUpdated(
      chat: $chat,
      messageId: 9,
      date: new DateTime('@0'),
      oldReaction: [],
      newReaction: [],
      user: $user,
    );

    $ctx = UserContextMiddleware::resolveContext($mru);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromMessageReactionCountUpdated(): void
  {
    $chat = new Chat(id: -401, type: 'channel');
    $mrcu = new MessageReactionCountUpdated(
      chat: $chat,
      messageId: 10,
      date: new DateTime('@0'),
      reactions: [],
    );

    $ctx = UserContextMiddleware::resolveContext($mrcu);

    self::assertSame($chat, $ctx->chat);
    self::assertNull($ctx->user);
  }

  public function testResolveContextFromChatBoostUpdatedPremiumExposesUser(): void
  {
    $chat = new Chat(id: -500, type: 'channel');
    $user = new User(id: 61, isBot: false, firstName: 'Oli');
    $boost = new ChatBoost(
      boostId: 'b1',
      addDate: new DateTime('@0'),
      expirationDate: new DateTime('@1'),
      source: new ChatBoostSourcePremium(user: $user),
    );
    $cbu = new ChatBoostUpdated(chat: $chat, boost: $boost);

    $ctx = UserContextMiddleware::resolveContext($cbu);

    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromChatBoostRemovedChatOnly(): void
  {
    $chat = new Chat(id: -501, type: 'channel');
    $cbr = new ChatBoostRemoved(
      chat: $chat,
      boostId: 'b2',
      removeDate: new DateTime('@0'),
      source: new ChatBoostSourcePremium(user: new User(id: 62, isBot: false, firstName: 'Pat')),
    );

    $ctx = UserContextMiddleware::resolveContext($cbr);

    self::assertSame($chat, $ctx->chat);
    self::assertNull($ctx->user);
  }

  public function testResolveContextFromBusinessConnectionExposesUserAndId(): void
  {
    // Upstream: only `user` + `business_connection_id` — there is no `userChat`
    // field on the BusinessConnection schema, just a `user_chat_id: int`.
    $user = new User(id: 71, isBot: false, firstName: 'Quinn');
    $bc = new BusinessConnection(
      id: 'bc-abc',
      user: $user,
      userChatId: 71,
      date: new DateTime('@0'),
      isEnabled: true,
    );

    $ctx = UserContextMiddleware::resolveContext($bc);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertSame('bc-abc', $ctx->businessConnectionId);
  }

  public function testResolveContextFromBusinessMessagesDeletedChatAndConnection(): void
  {
    $chat = new Chat(id: 81, type: 'private');
    $bmd = new BusinessMessagesDeleted(businessConnectionId: 'bc-z', chat: $chat, messageIds: [1, 2]);

    $ctx = UserContextMiddleware::resolveContext($bmd);

    self::assertSame($chat, $ctx->chat);
    self::assertNull($ctx->user);
    self::assertSame('bc-z', $ctx->businessConnectionId);
  }

  public function testResolveContextFromPaidMediaPurchasedUserOnly(): void
  {
    $user = new User(id: 91, isBot: false, firstName: 'Rae');
    $purchased = new PaidMediaPurchased(fromUser: $user, paidMediaPayload: 'p');

    $ctx = UserContextMiddleware::resolveContext($purchased);

    self::assertNull($ctx->chat);
    self::assertSame($user, $ctx->user);
  }

  public function testResolveContextFromPollHasNoUserOrChat(): void
  {
    $poll = new Poll(
      id: 'p',
      question: 'why?',
      options: [],
      totalVoterCount: 0,
      isClosed: false,
      isAnonymous: true,
      type: 'regular',
      allowsMultipleAnswers: false,
      allowsRevoting: false,
      membersOnly: false,
    );

    $ctx = UserContextMiddleware::resolveContext($poll);

    self::assertNull($ctx->chat);
    self::assertNull($ctx->user);
  }

  public function testInvokeInjectsAllFourKeysAndDelegatesToHandler(): void
  {
    $chat = new Chat(id: 100, type: 'private');
    $user = new User(id: 7, isBot: false, firstName: 'Ada');
    $message = new Message(
      messageId: 1,
      date: new DateTime('@0'),
      chat: $chat,
      messageThreadId: 5,
      fromUser: $user,
      businessConnectionId: 'bc',
      isTopicMessage: true,
    );
    $update = new Update(updateId: 1, message: $message);

    $captured = null;
    $handler = static function (TelegramObject $event, array $data) use (&$captured): string {
      $captured = ['event' => $event, 'data' => $data];

      return 'OK';
    };

    $middleware = new UserContextMiddleware();
    $result = $middleware($handler, $update, ['bot' => 'B']);

    self::assertSame('OK', $result);
    self::assertNotNull($captured);
    self::assertSame($update, $captured['event']);

    /** @var array<string, mixed> $data */
    $data = $captured['data'];
    self::assertArrayHasKey(UserContextMiddleware::EVENT_CONTEXT_KEY, $data);
    self::assertArrayHasKey(UserContextMiddleware::EVENT_FROM_USER_KEY, $data);
    self::assertArrayHasKey(UserContextMiddleware::EVENT_CHAT_KEY, $data);
    self::assertArrayHasKey(UserContextMiddleware::EVENT_THREAD_ID_KEY, $data);
    self::assertSame('B', $data['bot'], 'pre-existing data keys must be preserved.');

    /** @var EventContext $ctx */
    $ctx = $data[UserContextMiddleware::EVENT_CONTEXT_KEY];
    self::assertInstanceOf(EventContext::class, $ctx);
    self::assertSame($chat, $ctx->chat);
    self::assertSame($user, $ctx->user);
    self::assertSame(5, $ctx->threadId);
    self::assertSame('bc', $ctx->businessConnectionId);

    self::assertSame($user, $data[UserContextMiddleware::EVENT_FROM_USER_KEY]);
    self::assertSame($chat, $data[UserContextMiddleware::EVENT_CHAT_KEY]);
    self::assertSame(5, $data[UserContextMiddleware::EVENT_THREAD_ID_KEY]);
  }

  public function testInvokeInjectsNullKeysForBareUpdate(): void
  {
    // Even when nothing is resolved, every key must be present — handlers can
    // rely on `array_key_exists` instead of `isset` checks.
    $update = new Update(updateId: 0);
    $captured = null;
    $handler = static function (TelegramObject $event, array $data) use (&$captured): void {
      $captured = $data;
    };

    $middleware = new UserContextMiddleware();
    $middleware($handler, $update, []);

    self::assertNotNull($captured);
    self::assertArrayHasKey(UserContextMiddleware::EVENT_CONTEXT_KEY, $captured);
    self::assertArrayHasKey(UserContextMiddleware::EVENT_FROM_USER_KEY, $captured);
    self::assertArrayHasKey(UserContextMiddleware::EVENT_CHAT_KEY, $captured);
    self::assertArrayHasKey(UserContextMiddleware::EVENT_THREAD_ID_KEY, $captured);
    self::assertInstanceOf(EventContext::class, $captured[UserContextMiddleware::EVENT_CONTEXT_KEY]);
    self::assertNull($captured[UserContextMiddleware::EVENT_FROM_USER_KEY]);
    self::assertNull($captured[UserContextMiddleware::EVENT_CHAT_KEY]);
    self::assertNull($captured[UserContextMiddleware::EVENT_THREAD_ID_KEY]);
  }
}
