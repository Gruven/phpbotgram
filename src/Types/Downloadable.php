<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Types;

interface Downloadable
{
  public function fileId(): string;
}
