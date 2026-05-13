<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Fsm\Storage;

/**
 * Minimal collection interface required by `MongoStorage`.
 *
 * Defines only the four operations `MongoStorage` needs:
 * `findOne`, `updateOne`, `deleteOne`, and `findOneAndUpdate`.
 * By depending on this interface rather than the concrete
 * `\MongoDB\Collection`, `MongoStorage` can be unit-tested with a
 * lightweight anonymous-class mock without the `ext-mongodb` extension.
 *
 * The real `\MongoDB\Collection` is not a subtype of this interface
 * at the PHP type level (structural typing); a thin adapter is provided
 * by `MongoCollectionAdapter` and wired up in `MongoStorage::fromUrl()`
 * and `MongoStorage::create()`.
 */
interface MongoCollectionInterface
{
  /**
   * Find a single document matching `$filter`.
   *
   * @param array<string, mixed> $filter Query filter.
   * @param array<string, mixed> $options Command options.
   *
   * @return null|object Matched document, or `null` when no document matches.
   */
  public function findOne(array $filter, array $options = []): ?object;

  /**
   * Update a single document matching `$filter`.
   *
   * @param array<string, mixed> $filter Query filter.
   * @param array<string, mixed> $update Update specification.
   * @param array<string, mixed> $options Command options (e.g. `['upsert' => true]`).
   */
  public function updateOne(array $filter, array $update, array $options = []): void;

  /**
   * Delete a single document matching `$filter`.
   *
   * @param array<string, mixed> $filter Query filter.
   * @param array<string, mixed> $options Command options.
   */
  public function deleteOne(array $filter, array $options = []): void;

  /**
   * Atomically find a document, apply `$update`, and return the result.
   *
   * The return value after applying `$update` is returned (matching
   * `FindOneAndUpdate::RETURN_DOCUMENT_AFTER` semantics used inside
   * `MongoStorage`).
   *
   * @param array<string, mixed> $filter Query filter.
   * @param array<string, mixed> $update Update specification.
   * @param array<string, mixed> $options Command options.
   *
   * @return null|object Document after update, or `null` when no document matches.
   */
  public function findOneAndUpdate(array $filter, array $update, array $options = []): ?object;
}
