<?php

declare(strict_types=1);
use Revolt\EventLoop;
use Revolt\EventLoop\Driver\StreamSelectDriver;

require __DIR__ . '/../vendor/autoload.php';

// Reset Revolt EventLoop driver to ensure deterministic test scheduling.
EventLoop::setDriver(new StreamSelectDriver());
