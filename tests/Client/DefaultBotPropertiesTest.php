<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Client;

use Gruven\PhpBotGram\Client\DefaultBotProperties;
use Gruven\PhpBotGram\Types\LinkPreviewOptions;
use PHPUnit\Framework\TestCase;

final class DefaultBotPropertiesTest extends TestCase
{
  public function testGetReturnsNullWhenUnset(): void
  {
    $d = new DefaultBotProperties();
    self::assertNull($d->get('parse_mode'));
  }

  public function testGetReturnsValue(): void
  {
    $d = new DefaultBotProperties(parseMode: 'HTML');
    self::assertSame('HTML', $d->get('parse_mode'));
    self::assertSame('HTML', $d['parse_mode']);
  }

  public function testLinkPreviewAggregation(): void
  {
    $d = new DefaultBotProperties(linkPreviewIsDisabled: true);
    $lp = $d->get('link_preview');
    self::assertInstanceOf(LinkPreviewOptions::class, $lp);
    self::assertTrue($lp->isDisabled);
  }
}
