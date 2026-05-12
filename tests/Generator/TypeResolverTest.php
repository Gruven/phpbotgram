<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Generator;

use Gruven\PhpBotGram\Generator\AnnotationEntity;
use Gruven\PhpBotGram\Generator\LoadedSchema;
use Gruven\PhpBotGram\Generator\MethodEntity;
use Gruven\PhpBotGram\Generator\PhpTypeKind;
use Gruven\PhpBotGram\Generator\SchemaLoader;
use Gruven\PhpBotGram\Generator\TypeEntity;
use Gruven\PhpBotGram\Generator\TypeResolver;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \Gruven\PhpBotGram\Generator\PhpType
 * @covers \Gruven\PhpBotGram\Generator\PhpTypeKind
 * @covers \Gruven\PhpBotGram\Generator\TypeResolver
 */
final class TypeResolverTest extends TestCase
{
  private static ?LoadedSchema $loaded = null;
  private static ?TypeResolver $resolver = null;

  public static function setUpBeforeClass(): void
  {
    $schemaDir = dirname(__DIR__, 2) . '/.butcher';
    $loader = new SchemaLoader($schemaDir);
    self::$loaded = $loader->load();
    self::$resolver = new TypeResolver(self::$loaded);
  }

  public function testIntegerWireMapsToScalarInt(): void
  {
    $t = $this->resolver()->resolveWire('Integer');

    self::assertSame(PhpTypeKind::Scalar, $t->kind);
    self::assertSame('int', $t->phpType);
    self::assertNull($t->importFqcn);
    self::assertFalse($t->isTrueLiteral);
  }

  public function testStringWireMapsToScalarString(): void
  {
    $t = $this->resolver()->resolveWire('String');

    self::assertSame(PhpTypeKind::Scalar, $t->kind);
    self::assertSame('string', $t->phpType);
    self::assertNull($t->importFqcn);
  }

  public function testBooleanWireMapsToScalarBool(): void
  {
    $t = $this->resolver()->resolveWire('Boolean');

    self::assertSame(PhpTypeKind::Scalar, $t->kind);
    self::assertSame('bool', $t->phpType);
    self::assertNull($t->importFqcn);
    self::assertFalse($t->isTrueLiteral);
  }

  public function testTrueWireMapsToScalarBoolWithLiteralFlag(): void
  {
    $t = $this->resolver()->resolveWire('True');

    self::assertSame(PhpTypeKind::Scalar, $t->kind);
    self::assertSame('bool', $t->phpType);
    self::assertNull($t->importFqcn);
    self::assertTrue($t->isTrueLiteral);
  }

  public function testFloatAndFloatNumberBothMapToFloat(): void
  {
    $a = $this->resolver()->resolveWire('Float');
    $b = $this->resolver()->resolveWire('Float number');

    self::assertSame(PhpTypeKind::Scalar, $a->kind);
    self::assertSame('float', $a->phpType);
    self::assertSame('float', $b->phpType);
  }

  public function testNamedSchemaTypeResolvesToClassNameWithImport(): void
  {
    $t = $this->resolver()->resolveWire('Message');

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('Message', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\Message', $t->importFqcn);
  }

  public function testInputFileWireMapsToClassName(): void
  {
    $t = $this->resolver()->resolveWire('InputFile');

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('InputFile', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\InputFile', $t->importFqcn);
  }

  public function testArrayOfUpdateResolvesToListOf(): void
  {
    $t = $this->resolver()->resolveWire('Array of Update');

    self::assertSame(PhpTypeKind::ListOf, $t->kind);
    self::assertSame('list<Update>', $t->phpType);
    self::assertNotNull($t->innerType);
    self::assertSame(PhpTypeKind::ClassName, $t->innerType->kind);
    self::assertSame('Update', $t->innerType->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\Update', $t->innerType->importFqcn);
    self::assertSame('Gruven\\PhpBotGram\\Types\\Update', $t->importFqcn);
  }

  public function testArrayOfArrayOfPhotoSizeIsNestedListOf(): void
  {
    $t = $this->resolver()->resolveWire('Array of Array of PhotoSize');

    self::assertSame(PhpTypeKind::ListOf, $t->kind);
    self::assertSame('list<list<PhotoSize>>', $t->phpType);
    self::assertNotNull($t->innerType);
    self::assertSame(PhpTypeKind::ListOf, $t->innerType->kind);
    self::assertSame('list<PhotoSize>', $t->innerType->phpType);
    self::assertNotNull($t->innerType->innerType);
    self::assertSame(PhpTypeKind::ClassName, $t->innerType->innerType->kind);
    self::assertSame('PhotoSize', $t->innerType->innerType->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\PhotoSize', $t->innerType->innerType->importFqcn);
  }

  public function testArrayOfIntegerResolvesScalarInner(): void
  {
    $t = $this->resolver()->resolveWire('Array of Integer');

    self::assertSame(PhpTypeKind::ListOf, $t->kind);
    self::assertSame('list<int>', $t->phpType);
    self::assertNotNull($t->innerType);
    self::assertSame('int', $t->innerType->phpType);
    self::assertNull($t->innerType->importFqcn);
  }

  public function testIntegerOrStringIsUnion(): void
  {
    $t = $this->resolver()->resolveWire('Integer or String');

    self::assertSame(PhpTypeKind::Union, $t->kind);
    self::assertSame('int|string', $t->phpType);
    self::assertNull($t->importFqcn);
    self::assertCount(2, $t->unionMembers);
    self::assertSame('int', $t->unionMembers[0]->phpType);
    self::assertSame('string', $t->unionMembers[1]->phpType);
  }

  public function testInputFileOrStringUnionCarriesInputFileImport(): void
  {
    $t = $this->resolver()->resolveWire('InputFile or String');

    self::assertSame(PhpTypeKind::Union, $t->kind);
    self::assertSame('InputFile|string', $t->phpType);
    self::assertNull($t->importFqcn);
    self::assertCount(2, $t->unionMembers);

    // Find the InputFile member regardless of order.
    $inputFileMember = null;

    foreach ($t->unionMembers as $m) {
      if ($m->phpType === 'InputFile') {
        $inputFileMember = $m;
      }
    }

    self::assertNotNull($inputFileMember);
    self::assertSame('Gruven\\PhpBotGram\\Types\\InputFile', $inputFileMember->importFqcn);
  }

  public function testFourWayReplyMarkupUnionPreservesAllMembers(): void
  {
    $t = $this->resolver()->resolveWire('InlineKeyboardMarkup or ReplyKeyboardMarkup or ReplyKeyboardRemove or ForceReply');

    self::assertSame(PhpTypeKind::Union, $t->kind);
    self::assertCount(4, $t->unionMembers);
    self::assertSame('ForceReply|InlineKeyboardMarkup|ReplyKeyboardMarkup|ReplyKeyboardRemove', $t->phpType);
  }

  public function testArrayOfInlineUnionMembers(): void
  {
    // The wire spec for sendMediaGroup.media uses an inline-union shape:
    //   "Array of A, B, C and D"  -> list<A|B|C|D>
    $t = $this->resolver()->resolveWire('Array of InputMediaAudio, InputMediaDocument, InputMediaLivePhoto, InputMediaPhoto and InputMediaVideo');

    self::assertSame(PhpTypeKind::ListOf, $t->kind);
    self::assertNotNull($t->innerType);
    self::assertSame(PhpTypeKind::Union, $t->innerType->kind);
    self::assertCount(5, $t->innerType->unionMembers);
    self::assertSame(
      'list<InputMediaAudio|InputMediaDocument|InputMediaLivePhoto|InputMediaPhoto|InputMediaVideo>',
      $t->phpType,
    );
  }

  public function testResolveAnnotationFallsBackToWireWhenNoParsedType(): void
  {
    // Annotation `Update.message` has wire "Message" and no replace.yml override.
    $ann = new AnnotationEntity(
      name: 'message',
      description: '',
      type: 'Message',
      required: false,
      parsedType: null,
    );

    $t = $this->resolver()->resolve($ann);
    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('Message', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\Message', $t->importFqcn);
  }

  public function testParsedTypeStdDateTimeOverridesWire(): void
  {
    // `Message.date` ships wire "Integer" but replace.yml overrides to DateTime.
    $message = $this->findType('Message');
    $date = $this->findAnnotation($message->annotations, 'date');

    $t = $this->resolver()->resolve($date);

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('DateTime', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\Custom\\DateTime', $t->importFqcn);
  }

  public function testParsedTypeStdDatetimeDatetimeMapsToCustomDateTime(): void
  {
    // `Video.start_timestamp` uses `name: datetime.datetime` form.
    $video = $this->findType('Video');
    $ts = $this->findAnnotation($video->annotations, 'start_timestamp');

    $t = $this->resolver()->resolve($ts);

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('DateTime', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\Custom\\DateTime', $t->importFqcn);
  }

  public function testParsedTypeEntityReferenceResolvesToClassName(): void
  {
    // `InputMediaAudio.thumbnail` overrides to InputFile via entity-reference.
    $mediaAudio = $this->findType('InputMediaAudio');
    $thumb = $this->findAnnotation($mediaAudio->annotations, 'thumbnail');

    $t = $this->resolver()->resolve($thumb);

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('InputFile', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\InputFile', $t->importFqcn);
  }

  public function testParsedTypeUnionOfStdAndEntity(): void
  {
    // `InputMediaAudio.media` is a parsed-type union of {std str, entity InputFile}.
    $mediaAudio = $this->findType('InputMediaAudio');
    $media = $this->findAnnotation($mediaAudio->annotations, 'media');

    $t = $this->resolver()->resolve($media);

    self::assertSame(PhpTypeKind::Union, $t->kind);
    self::assertCount(2, $t->unionMembers);
    self::assertSame('InputFile|string', $t->phpType);
  }

  public function testParsedTypeEnumReferenceMapsToEnumsNamespace(): void
  {
    // Spec example: `{type: enum, name: ChatType}` (forward-compat shape).
    $ann = new AnnotationEntity(
      name: 'type',
      description: '',
      type: 'String',
      required: true,
      parsedType: ['type' => 'enum', 'name' => 'ChatType'],
    );

    $t = $this->resolver()->resolve($ann);

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('ChatType', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Enums\\ChatType', $t->importFqcn);
  }

  public function testParsedTypeEntityEnumCategoryMapsToEnumsNamespace(): void
  {
    // The actual .butcher YAML uses the entity-reference shape with category=enums.
    $ann = new AnnotationEntity(
      name: 'type',
      description: '',
      type: 'String',
      required: true,
      parsedType: [
        'type' => 'entity',
        'references' => ['category' => 'enums', 'name' => 'ChatType'],
      ],
    );

    $t = $this->resolver()->resolve($ann);

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('ChatType', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Enums\\ChatType', $t->importFqcn);
  }

  public function testParsedTypeStdDateIntervalMapsToBuiltinDateInterval(): void
  {
    // `name: datetime.timedelta` -> \DateInterval (PHP builtin).
    $ann = new AnnotationEntity(
      name: 'duration',
      description: '',
      type: 'Integer',
      required: false,
      parsedType: ['type' => 'std', 'name' => 'datetime.timedelta'],
    );

    $t = $this->resolver()->resolve($ann);

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('DateInterval', $t->phpType);
    self::assertSame('DateInterval', $t->importFqcn);
  }

  public function testParsedTypeStdIntStrFloatScalars(): void
  {
    foreach ([['int', 'int'], ['str', 'string'], ['float', 'float']] as [$pyName, $phpName]) {
      $ann = new AnnotationEntity(
        name: 'x',
        description: '',
        type: 'String',
        required: false,
        parsedType: ['type' => 'std', 'name' => $pyName],
      );
      $t = $this->resolver()->resolve($ann);

      self::assertSame(PhpTypeKind::Scalar, $t->kind);
      self::assertSame($phpName, $t->phpType);
      self::assertNull($t->importFqcn);
    }
  }

  public function testResolveWireForGetMeReturnTypeUser(): void
  {
    // Smoke test that getMe's wire string parses into User.
    $getMe = $this->findMethod('getMe');
    self::assertSame('getMe', $getMe->name);

    $t = $this->resolver()->resolveWire('User');

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('User', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\User', $t->importFqcn);
  }

  public function testEnumNameFromSchemaResolvesToEnumsNamespace(): void
  {
    // A wire string that matches a known enum name (ChatType) should land in Enums\.
    // The .butcher ships ChatType as an enum, so resolving the bare wire token
    // should map to the enum namespace even without parsed_type.
    $t = $this->resolver()->resolveWire('ChatType');

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('ChatType', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Enums\\ChatType', $t->importFqcn);
  }

  public function testUnknownClassNameFallsBackToTypesNamespace(): void
  {
    // Forward-reference fallback: if a wire token isn't in $schema->types or $schema->enums,
    // we still emit a Types\<Name> import so the renderer surfaces the missing class later.
    $t = $this->resolver()->resolveWire('SomeUnseenType');

    self::assertSame(PhpTypeKind::ClassName, $t->kind);
    self::assertSame('SomeUnseenType', $t->phpType);
    self::assertSame('Gruven\\PhpBotGram\\Types\\SomeUnseenType', $t->importFqcn);
  }

  public function testUnionMembersAreDeduplicatedAndSorted(): void
  {
    // A union that dedupes to a single member collapses back to that member
    // (a "union" with one element is not a union — emit the scalar directly).
    $t = $this->resolver()->resolveWire('Integer or Integer');

    self::assertSame(PhpTypeKind::Scalar, $t->kind);
    self::assertSame('int', $t->phpType);

    // Three-way union with a duplicate dedupes to a two-way union and sorts members.
    $u = $this->resolver()->resolveWire('String or Integer or String');
    self::assertSame(PhpTypeKind::Union, $u->kind);
    self::assertCount(2, $u->unionMembers);
    self::assertSame('int|string', $u->phpType);
  }

  public function testParsedTypeUnionRecursivelyResolvesItems(): void
  {
    // InputMediaVideo.start_timestamp -> union of {datetime.datetime, datetime.timedelta, int}.
    $video = $this->findType('InputMediaVideo');
    $ts = $this->findAnnotation($video->annotations, 'start_timestamp');

    $t = $this->resolver()->resolve($ts);

    self::assertSame(PhpTypeKind::Union, $t->kind);
    self::assertCount(3, $t->unionMembers);
    // The string form should contain all three.
    self::assertStringContainsString('DateInterval', $t->phpType);
    self::assertStringContainsString('DateTime', $t->phpType);
    self::assertStringContainsString('int', $t->phpType);
  }

  public function testListInheritsImportFromInnermostClassMember(): void
  {
    // Inner is a scalar — outer ListOf should NOT carry an import.
    $t = $this->resolver()->resolveWire('Array of String');
    self::assertSame(PhpTypeKind::ListOf, $t->kind);
    self::assertSame('list<string>', $t->phpType);
    self::assertNull($t->importFqcn);
  }

  private function resolver(): TypeResolver
  {
    $r = self::$resolver;

    if ($r === null) {
      self::fail('Resolver not initialised — setUpBeforeClass did not run');
    }

    return $r;
  }

  private function loaded(): LoadedSchema
  {
    $l = self::$loaded;

    if ($l === null) {
      self::fail('Schema not loaded — setUpBeforeClass did not run');
    }

    return $l;
  }

  private function findType(string $name): TypeEntity
  {
    foreach ($this->loaded()->types as $t) {
      if ($t->name === $name) {
        return $t;
      }
    }

    self::fail("Type {$name} not found");
  }

  private function findMethod(string $name): MethodEntity
  {
    foreach ($this->loaded()->methods as $m) {
      if ($m->name === $name) {
        return $m;
      }
    }

    self::fail("Method {$name} not found");
  }

  /**
   * @param list<AnnotationEntity> $annotations
   */
  private function findAnnotation(array $annotations, string $name): AnnotationEntity
  {
    foreach ($annotations as $a) {
      if ($a->name === $name) {
        return $a;
      }
    }

    self::fail("Annotation {$name} not found");
  }
}
