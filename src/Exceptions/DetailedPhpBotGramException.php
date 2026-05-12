<?php

declare(strict_types=1);

namespace Gruven\PhpBotGram\Exceptions;

class DetailedPhpBotGramException extends PhpBotGramException
{
    public ?string $url = null;

    public function __construct(public readonly string $detail)
    {
        $msg = $detail;
        if ($this->url !== null) {
            $msg .= "\n(background on this error at: {$this->url})";
        }
        parent::__construct($msg);
    }
}
