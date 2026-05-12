<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Reset Revolt EventLoop driver to ensure deterministic test scheduling.
\Revolt\EventLoop::setDriver(new \Revolt\EventLoop\Driver\StreamSelectDriver());
