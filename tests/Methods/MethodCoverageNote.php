<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Methods;

/**
 * Upstream `tests/test_api/test_methods/*` port status — Phase 8, Task 8.2.
 *
 * Upstream has 179 test files (one per Telegram API method).
 * Phase 8 ports a representative sample of 9 files.
 * The remaining ~170 method tests are NOT individually ported because:
 *
 * 1. Codegen-determined behavior (b): each `Methods/*.php` class is generated
 *    from the Telegram API spec via `tools/generator/`. The constructor
 *    shapes, property names, and ApiMethod/ReturnsType constants are all
 *    deterministically produced — a per-method PHP test would not catch any
 *    divergence that the generator's own output already exhibits.
 *
 * 2. Round-trip serialization (b): the `model_dump()` / `model_validate()`
 *    pair that upstream method tests exercise is mirrored by phpbotgram's
 *    `Session::prepareValue()` + `Session::checkResponse()`. Both are
 *    exhaustively tested in `tests/Client/Session/BaseSessionTest.php`.
 *
 * 3. Integration via MockedBot (b): most Phase 3–7 tests that touch a method
 *    add a canned result via `MockedBot::addResultFor(SomeMethod::class, ...)`
 *    which exercises the method's constructor and serialization round-trip
 *    indirectly.
 *
 * 4. Test infrastructure divergence (c): upstream tests rely on pytest-asyncio,
 *    AsyncMock, and Python's `async with` session context managers — none of
 *    which have a meaningful PHP equivalent.
 *
 * Sample method tests ported in Task 8.2:
 *   - SendMessageTest    (most-used method)
 *   - GetUpdatesTest     (polling core, list return type)
 *   - AnswerCallbackQueryTest (callback handling, bool return)
 *   - SendPhotoTest      (file upload / InputFile|string union)
 *   - SendDocumentTest   (file upload with thumbnail)
 *   - SetWebhookTest     (webhook setup, bool return)
 *   - EditMessageTextTest (edit flow, union:Message|bool return)
 *   - DeleteMessageTest  (simplest bool call)
 *   - SendMediaGroupTest (multi-item, list:Message return)
 *   - StopPollTest       (object return type — Poll)
 */
final class MethodCoverageNote {}
