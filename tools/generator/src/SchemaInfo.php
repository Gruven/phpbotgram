<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

/**
 * Pinned schema entity counts — a tripwire so a future `.butcher` sync
 * forces the maintainer to update the codegen tests + count constants
 * intentionally rather than silently rolling forward.
 *
 * `SchemaInfoTest` asserts each of these constants against the live
 * `LoadedSchema` (for source entity counts) and against the on-disk file
 * count of a freshly emitted `$outDir` (for codegen output counts). A
 * schema sync that adds or removes an entity will therefore fail CI until
 * the maintainer updates this class to the new numbers — a deliberate
 * speed-bump that catches accidental scope expansion in butcher's output.
 *
 * `EMITTED_TYPE_FILES` exceeds `TYPE_ENTITIES` because the codegen emits
 * one `<Parent>Union.php` resolver per union parent in addition to the
 * per-type class file (see `UnionDetector::plans()` and `Pipeline::run()`).
 * The number is empirically pinned: a manual `ls $tmpOut/Types | wc -l`
 * after `bin/generate.php` finishes is the source of truth.
 */
final class SchemaInfo
{
  /** Telegram Bot API version that the vendored `.butcher` schema targets. */
  public const string API_VERSION = '10.1';

  /** Release date of the targeted API version (`api.release_date` in `schema.json`). */
  public const string API_RELEASE_DATE = '2026-06-11';

  /**
   * Number of `category=types` children declared in `.butcher/schema/schema.json`.
   *
   * Note: `.butcher/types/` has 361 directories on disk, but two
   * (`KeyboardButtonRequestUser`, `UserShared`) are legacy/deprecated and
   * no longer listed in `schema.json#items[].children[]`. The 359 count
   * sourced from the JSON is the authoritative number consumed by every
   * downstream pipeline stage.
   */
  public const int TYPE_ENTITIES = 359;

  /** Number of `category=methods` children declared in `schema.json`. */
  public const int METHOD_ENTITIES = 180;

  /** Number of enum YAML files under `.butcher/enums/`. */
  public const int ENUM_ENTITIES = 36;

  /**
   * Files emitted by `Pipeline::run()` under `$outDir/Types/`.
   *
   * Composition: 359 schema types + 23 `<Parent>Union.php` resolvers +
   * 2 `<Parent>Interface.php` marker interfaces (only the two unions with
   * shadow members — `InputPollMedia` and `InputPollOptionMedia` — get an
   * interface; single-parent unions are satisfied by the abstract class
   * itself). One protected path (`Types/InputFile.php`) appears in the
   * emitter's `skipped` manifest but is also re-counted on disk because
   * the hand-authored sibling already lives there in production — for
   * the test we count emissions into a clean `$outDir`, where the
   * protected path is skipped and so does NOT contribute to this number.
   */
  public const int EMITTED_TYPE_FILES = 383;

  /** Files emitted under `$outDir/Methods/` — one PHP class per `MethodEntity`. */
  public const int EMITTED_METHOD_FILES = 180;

  /** Files emitted under `$outDir/Enums/` — one PHP class per `EnumEntity`. */
  public const int EMITTED_ENUM_FILES = 36;
}
