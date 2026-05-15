<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class UpdateVersionsJsonTest extends TestCase
{
  private string $tmp;

  protected function setUp(): void
  {
    $this->tmp = sys_get_temp_dir() . '/uvj-' . uniqid();
    mkdir($this->tmp, recursive: true);
  }

  protected function tearDown(): void
  {
    foreach (glob($this->tmp . '/*') as $f) unlink($f);
    rmdir($this->tmp);
  }

  public function testFirstDevPush(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions": []}');

    $this->runScript($path, ['id=dev', 'path=en/dev/', 'label=dev (master)', 'stable=false']);

    $data = json_decode(file_get_contents($path), true);
    self::assertCount(1, $data['versions']);
    self::assertSame('dev', $data['versions'][0]['id']);
    self::assertFalse($data['versions'][0]['stable']);
  }

  public function testFirstTagPushFlagsStable(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions": [{"id":"dev","path":"en/dev/","label":"dev","stable":false}]}');

    $this->runScript($path, ['id=v0.1.0', 'path=en/v0.1.0/', 'label=v0.1.0', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    self::assertCount(2, $data['versions']);
    self::assertSame('v0.1.0', $data['versions'][0]['id']);
    self::assertTrue($data['versions'][0]['stable']);
    self::assertSame('dev', $data['versions'][1]['id']);
    self::assertFalse($data['versions'][1]['stable']);
  }

  public function testSecondTagFlipsPriorStable(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":['
      . '{"id":"v0.1.0","path":"en/v0.1.0/","label":"v0.1.0","stable":true},'
      . '{"id":"dev","path":"en/dev/","label":"dev","stable":false}'
      . ']}');

    $this->runScript($path, ['id=v0.2.0', 'path=en/v0.2.0/', 'label=v0.2.0', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    self::assertSame('v0.2.0', $data['versions'][0]['id']);
    self::assertTrue($data['versions'][0]['stable']);
    self::assertSame('v0.1.0', $data['versions'][1]['id']);
    self::assertFalse($data['versions'][1]['stable']);
  }

  public function testBackportPublishLeavesNewerStable(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":['
      . '{"id":"v0.2.0","path":"en/v0.2.0/","label":"v0.2.0","stable":true},'
      . '{"id":"dev","path":"en/dev/","label":"dev","stable":false}'
      . ']}');

    $this->runScript($path, ['id=v0.1.1', 'path=en/v0.1.1/', 'label=v0.1.1', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    $ids = array_column($data['versions'], 'id');
    $byId = array_combine($ids, $data['versions']);
    self::assertTrue($byId['v0.2.0']['stable']);
    self::assertFalse($byId['v0.1.1']['stable']);
    self::assertFalse($byId['dev']['stable']);
  }

  public function testForcePushDeduplicates(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":[{"id":"v0.1.0","path":"en/v0.1.0/","label":"v0.1.0","stable":true}]}');

    $this->runScript($path, ['id=v0.1.0', 'path=en/v0.1.0/', 'label=v0.1.0', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    self::assertCount(1, $data['versions']);
  }

  public function testForcePushOfOlderTagDoesNotReclaimStable(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions":['
      . '{"id":"v0.2.0","path":"en/v0.2.0/","label":"v0.2.0","stable":true},'
      . '{"id":"v0.1.0","path":"en/v0.1.0/","label":"v0.1.0","stable":false}'
      . ']}');

    $this->runScript($path, ['id=v0.1.0', 'path=en/v0.1.0/', 'label=v0.1.0 (re-tagged)', 'stable=auto']);

    $data = json_decode(file_get_contents($path), true);
    $byId = array_column($data['versions'], null, 'id');
    self::assertCount(2, $data['versions']);
    self::assertTrue($byId['v0.2.0']['stable'], 'v0.2.0 must retain stable flag');
    self::assertFalse($byId['v0.1.0']['stable'], 'force-pushed older tag must not reclaim stable');
    self::assertSame('v0.1.0 (re-tagged)', $byId['v0.1.0']['label']);
  }

  public function testLabelContainingSpaces(): void
  {
    $path = $this->tmp . '/versions.json';
    file_put_contents($path, '{"versions": []}');

    $this->runScript($path, ['id=dev', 'path=en/dev/', 'label=dev (master)', 'stable=false']);

    $data = json_decode(file_get_contents($path), true);
    self::assertSame('dev (master)', $data['versions'][0]['label']);
  }

  /** @param list<string> $args */
  private function runScript(string $path, array $args): void
  {
    $script = dirname(__DIR__, 2) . '/scripts/update-versions-json.php';
    $cmd = sprintf(
      'php %s %s --upsert %s 2>&1',
      escapeshellarg($script),
      escapeshellarg($path),
      implode(' ', array_map(escapeshellarg(...), $args)),
    );
    exec($cmd, $output, $rc);
    self::assertSame(0, $rc, "Script failed: " . implode("\n", $output));
  }
}
