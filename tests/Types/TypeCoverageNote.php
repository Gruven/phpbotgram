<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Types;

/**
 * Upstream `tests/test_api/test_types/*` port status — Phase 8, Task 8.3.
 *
 * Upstream has 25 test files (one per major Telegram type).
 * Phase 8 ports a representative sample of 9 files.
 * The remaining ~16 type tests are NOT individually ported because:
 *
 * 1. Codegen-determined behavior (b): each `Types/*.php` class is generated
 *    from the Telegram API spec. Constructor shapes, property names, and
 *    WireNames remappings are all deterministically produced.
 *
 * 2. Round-trip serialization (b): the `model_dump()` / `model_validate()`
 *    pair that upstream type tests exercise is mirrored by phpbotgram's
 *    Serializer::pack() + Serializer::unpack(). These are exhaustively tested
 *    in tests/Client/SerializerTest.php and tests/Client/Session/BaseSessionTest.php.
 *
 * 3. Custom types already covered (b): tests/Types/Custom/DateTimeTest.php
 *    covers the DateTime wrapper; tests/Types/InputFileTest.php covers
 *    BufferedInputFile and FsInputFile; tests/Types/TelegramObjectTest.php
 *    covers the base class contract.
 *
 * 4. Test infrastructure divergence (c): upstream tests rely on Pydantic
 *    model_validate which maps directly to JSON payloads. PHP's equivalent
 *    (Serializer::unpack) is covered in SerializerTest.
 *
 * Sample type tests ported in Task 8.3:
 *   - UserTypeTest           (most-accessed object in filters)
 *   - ChatTypeTest           (commonly accessed, multi-variant type)
 *   - MessageTypeTest        (most-complex type, many optional fields)
 *   - UpdateTypeTest         (dispatcher entry point)
 *   - CallbackQueryTypeTest  (filter target + answer() shortcut)
 *   - InlineKeyboardMarkupTypeTest (builder target, nested arrays)
 *   - ReplyKeyboardMarkupTypeTest  (builder target, keyboard layout)
 *   - ForceReplyTypeTest     (simple markup type)
 *   - PhotoSizeTypeTest      (media size / fileId pattern)
 */
final class TypeCoverageNote {}
