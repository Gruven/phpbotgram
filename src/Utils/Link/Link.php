<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Utils\Link;

/**
 * Telegram and documentation link builders.
 *
 * Port of upstream `aiogram/utils/link.py`.
 *
 * Docs URL note: the upstream project points at `https://docs.aiogram.dev/en/dev-3.x/`.
 * This PHP port has no published docs site yet, so the constant mirrors the upstream
 * URL verbatim. Update `BASE_PAGE_URL` once a dedicated docs site is available.
 *
 * API design note: PHP does not allow a non-variadic parameter to follow a variadic
 * one, so we accept explicit `array $path` for path segments instead of `string ...$path`.
 * Call sites pass the path as an array literal:
 *
 *   Link::createTelegramLink(['mybot'], ['start' => 'abc'])
 *   Link::createTelegramLink(['mybot', 'myapp'])
 *   Link::docsUrl(['handlers'], fragment: 'section')
 */
final class Link
{
  private const BASE_DOCS_URL = 'https://docs.aiogram.dev/';
  private const BRANCH = 'dev-3.x';
  private const BASE_PAGE_URL = self::BASE_DOCS_URL . 'en/' . self::BRANCH . '/';

  private function __construct() {}

  /**
   * Build a URL from a base, optional path segments, fragment, and query params.
   *
   * @param list<string> $path
   * @param array<string, int|string> $query
   */
  private static function formatUrl(
    string $base,
    array $path = [],
    ?string $fragment = null,
    array $query = [],
  ): string {
    if ($path !== []) {
      $base = rtrim($base, '/') . '/' . implode('/', $path);
    }

    if ($query !== []) {
      $base .= '?' . http_build_query($query);
    }

    if ($fragment !== null) {
      $base .= '#' . $fragment;
    }

    return $base;
  }

  /**
   * Build a documentation URL relative to the configured docs base.
   *
   *   Link::docsUrl(['handlers'], fragment: 'section')
   *   Link::docsUrl(['search'], query: ['q' => 'router'])
   *   Link::docsUrl()
   *
   * @param list<string> $path URL path segments appended to the base docs URL
   * @param null|string $fragment optional URL fragment (after `#`)
   * @param array<string, int|string> $query optional query parameters
   */
  public static function docsUrl(
    array $path = [],
    ?string $fragment = null,
    array $query = [],
  ): string {
    return self::formatUrl(self::BASE_PAGE_URL, $path, $fragment, $query);
  }

  /**
   * Build a `tg://` deep-link URL.
   *
   * @param array<string, int|string> $query optional query parameters
   */
  public static function createTgLink(string $link, array $query = []): string
  {
    return self::formatUrl('tg://' . $link, query: $query);
  }

  /**
   * Build an `https://t.me/...` URL.
   *
   *   Link::createTelegramLink(['mybot'], ['start' => 'abc'])
   *   Link::createTelegramLink(['mybot', 'myapp'])
   *   Link::createTelegramLink()
   *
   * @param list<string> $path URL path segments
   * @param array<string, int|string> $query optional query parameters
   */
  public static function createTelegramLink(
    array $path = [],
    array $query = [],
  ): string {
    return self::formatUrl('https://t.me', $path, null, $query);
  }
}
