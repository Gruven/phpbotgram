<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Support;

use Gruven\PhpBotGram\Bot;
use Gruven\PhpBotGram\Methods\Response;
use Gruven\PhpBotGram\Methods\TelegramMethod;
use Gruven\PhpBotGram\Types\ResponseParameters;
use Gruven\PhpBotGram\Types\User;
use Gruven\PhpBotGram\Utils\Token;
use LogicException;

final class MockedBot extends Bot
{
  private User $cachedMeStub;

  public function __construct(string $token = '42:TEST')
  {
    parent::__construct(token: $token, session: new MockedSession());
    // Stub id derived from token so $bot->me()->id === $bot->getId() stays consistent.
    $this->cachedMeStub = new User(
      id: Token::extractBotId($token),
      isBot: true,
      firstName: 'FirstName',
      lastName: 'LastName',
      username: 'tbot',
      languageCode: 'uk-UA',
    );
  }

  public function getMockedSession(): MockedSession
  {
    assert($this->session instanceof MockedSession);

    return $this->session;
  }

  public function me(): User
  {
    return $this->cachedMeStub;
  }

  /**
   * @param class-string<TelegramMethod<mixed>> $methodClass
   *
   * @return Response<mixed>
   */
  public function addResultFor(
    string $methodClass,
    bool $ok,
    mixed $result = null,
    ?string $description = null,
    int $errorCode = 200,
    ?int $migrateToChatId = null,
    ?int $retryAfter = null,
  ): Response {
    // Type-check the canned result against the method's declared ReturnsType.
    // Catches misconfigured tests at queueing time rather than as a confusing
    // TypeError at the user-facing return-type boundary.
    if ($ok && $result !== null) {
      $expected = $methodClass::ReturnsType;

      if ($expected !== '' && class_exists($expected) && !($result instanceof $expected)) {
        throw new LogicException(sprintf(
          'Canned result for %s must be an instance of %s — got %s',
          $methodClass,
          $expected,
          get_debug_type($result),
        ));
      }
    }
    $parameters = new ResponseParameters(
      migrateToChatId: $migrateToChatId,
      retryAfter: $retryAfter,
    );
    $response = new Response(
      ok: $ok,
      result: $result,
      description: $description,
      errorCode: $errorCode,
      parameters: $parameters,
    );
    $this->getMockedSession()->addResult($response);

    return $response;
  }

  /**
   * @return TelegramMethod<mixed>
   */
  public function getRequest(): TelegramMethod
  {
    return $this->getMockedSession()->getRequest();
  }
}
