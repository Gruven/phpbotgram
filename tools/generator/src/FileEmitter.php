<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Generator;

use RuntimeException;

/**
 * Pipeline-side disk emitter (Task 2.12).
 *
 * Encapsulates the per-output-path **write-or-skip** decision so the pipeline
 * orchestrator can hand off every Twig-rendered source string to this class
 * without re-implementing the protection logic on the call-side.
 *
 * The class enforces three invariants:
 *
 *   1. **Hand-authored protection.** Paths listed in `PROTECTED_PATHS` never
 *      receive a write — regardless of whether the generator would have
 *      produced byte-identical content. The maintainer-edited files in the
 *      protection list always survive a `make regenerate`.
 *
 *   2. **Atomic writes.** Generated files are written to a sibling
 *      `<basename>.tmp.<pid>.<unique>` and `rename()`d into place. If the
 *      write or the rename fails mid-flight, the previous on-disk file (if
 *      any) is left untouched — there is no "half-written" intermediate
 *      state visible to a concurrent reader.
 *
 *   3. **Parent-directory creation.** Nested output paths
 *      (`Methods/Sub/Foo.php`) automatically materialise their parent
 *      directories with `mkdir($dir, recursive: true)`.
 *
 * The emitter does NOT enforce idempotency — the orchestrator is responsible
 * for ensuring two successive runs over the same `.butcher/` input produce
 * byte-identical content; this class only writes whatever the renderer hands
 * it. Determinism is the renderer's contract.
 */
final class FileEmitter
{
  /**
   * Relative paths (under `$outDir`) the pipeline must never overwrite.
   *
   * These are the maintainer-authored files that ship with the package and
   * cannot be reconstructed from the `.butcher/` schema:
   *
   *   - `Types/Downloadable.php`, `Types/InputFile.php`, and the three
   *     concrete InputFile siblings: hand-coded multipart upload helpers
   *     that the codegen schema knows nothing about. The schema's `InputFile`
   *     "type" is a marker only; the runtime surface is hand-authored.
   *   - `Types/Custom/DateTime.php`: thin wrapper over the PHP built-in
   *     DateTime that the serializer relies on to encode/decode the
   *     Telegram-API integer Unix timestamps.
   *   - `Types/Unspecified.php`: the singleton sentinel returned by methods
   *     that want to distinguish "explicit null" from "argument omitted".
   *   - `Types/MutableTelegramObject.php` and `Types/TelegramObject.php`:
   *     the runtime base classes every generated type extends from.
   *
   * Adding to this list requires re-running the codegen with the protected
   * file already on disk to verify the orchestrator skips it correctly.
   *
   * @var list<string>
   */
  public const array PROTECTED_PATHS = [
    'Types/Downloadable.php',
    'Types/InputFile.php',
    'Types/BufferedInputFile.php',
    'Types/FsInputFile.php',
    'Types/UrlInputFile.php',
    'Types/Custom/DateTime.php',
    'Types/Unspecified.php',
    'Types/MutableTelegramObject.php',
    'Types/TelegramObject.php',
  ];

  /**
   * Lookup form of `PROTECTED_PATHS` for O(1) checking inside `emit()`.
   *
   * @var array<string, true>
   */
  private readonly array $protectedSet;

  public function __construct(private readonly string $outDir)
  {
    /** @var array<string, true> $set */
    $set = [];

    foreach (self::PROTECTED_PATHS as $p) {
      $set[$p] = true;
    }

    $this->protectedSet = $set;
  }

  /**
   * Write `$contents` to `<outDir>/<relativePath>` atomically, or skip if the
   * relative path is in the protection list. Returns `'written'` when the file
   * was written (overwriting any prior content), or `'skipped'` when the path
   * was matched against the protection list and left untouched.
   *
   * @return 'skipped'|'written'
   */
  public function emit(string $relativePath, string $contents): string
  {
    // Normalize to forward-slash form so PROTECTED_PATHS lookups work
    // identically on Windows builds (none today, but cheap).
    $normalised = str_replace('\\', '/', $relativePath);

    if (isset($this->protectedSet[$normalised])) {
      return 'skipped';
    }

    $absolute = $this->outDir . '/' . $normalised;
    $dir = \dirname($absolute);

    if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
      throw new RuntimeException("Failed to create directory: {$dir}");
    }

    // tmpfile + rename so a partial-write doesn't corrupt the on-disk file.
    // We can't use tempnam() because we want the temp file to be on the same
    // filesystem as the final path (rename() across filesystems falls back
    // to copy+unlink, which loses the atomicity guarantee).
    $temp = $absolute . '.tmp.' . posix_getpid() . '.' . bin2hex(random_bytes(4));

    if (file_put_contents($temp, $contents) === false) {
      throw new RuntimeException("Failed to write temp file: {$temp}");
    }

    if (!rename($temp, $absolute)) {
      @unlink($temp);

      throw new RuntimeException("Failed to rename {$temp} to {$absolute}");
    }

    return 'written';
  }
}
