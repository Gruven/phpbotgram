<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

use MongoDB\Collection;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * Thin adapter that wraps `\MongoDB\Collection` and makes it satisfy
 * `MongoCollectionInterface`.
 *
 * The real `\MongoDB\Collection` returns `UpdateResult` / `DeleteResult`
 * from mutation methods.  This adapter discards those return values so the
 * interface can declare `void`, keeping `MongoStorage` independent of the
 * `mongodb/mongodb` userland library at the interface level.
 *
 * `findOneAndUpdate` is forwarded with `returnDocument: RETURN_DOCUMENT_AFTER`
 * so that the result reflects the document state **after** the update — required
 * for `MongoStorage`'s empty-document cleanup logic.
 */
final class MongoCollectionAdapter implements MongoCollectionInterface
{
  private readonly Collection $collection;

  /**
   * Wrap a `\MongoDB\Collection` and apply a typeMap that deep-converts
   * nested BSON documents to plain PHP arrays on read-back.
   *
   * Without a typeMap `findOne` returns a `BSONDocument` for the top-level
   * document and nested embedded documents stay as `BSONDocument` instances.
   * A shallow `(array)$document->data` cast inside `MongoStorage::getData`
   * would then leave nested values as `BSONDocument` rather than arrays,
   * violating the `array<string, mixed>` storage contract.
   *
   * TypeMap:
   * - `'root' => 'object'` — keeps the top-level findOne result as `?object`
   *   (matches the `MongoCollectionInterface::findOne` return-type signature).
   * - `'document' => 'array'` — nested BSON embedded documents become PHP arrays.
   * - `'array' => 'array'` — BSON arrays become PHP arrays.
   *
   * Applies via `Collection::withOptions` so the original `$collection` is
   * unchanged (the caller may reuse it with different options).
   */
  public function __construct(Collection $collection)
  {
    $this->collection = $collection->withOptions([
      'typeMap' => [
        'root' => 'object',
        'document' => 'array',
        'array' => 'array',
      ],
    ]);
  }

  /**
   * @param array<string, mixed> $filter
   * @param array<string, mixed> $options
   */
  public function findOne(array $filter, array $options = []): ?object
  {
    $result = $this->collection->findOne($filter, $options);

    if ($result === null || is_array($result)) {
      return null;
    }

    return $result;
  }

  /**
   * @param array<string, mixed> $filter
   * @param array<string, mixed> $update
   * @param array<string, mixed> $options
   */
  public function updateOne(array $filter, array $update, array $options = []): void
  {
    $this->collection->updateOne($filter, $update, $options);
  }

  /**
   * @param array<string, mixed> $filter
   * @param array<string, mixed> $options
   */
  public function deleteOne(array $filter, array $options = []): void
  {
    $this->collection->deleteOne($filter, $options);
  }

  /**
   * @param array<string, mixed> $filter
   * @param array<string, mixed> $update
   * @param array<string, mixed> $options
   */
  public function findOneAndUpdate(array $filter, array $update, array $options = []): ?object
  {
    $options['returnDocument'] = FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
    $result = $this->collection->findOneAndUpdate($filter, $update, $options);

    if ($result === null || is_array($result)) {
      return null;
    }

    return $result;
  }
}
