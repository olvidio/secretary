<?php

declare(strict_types=1);

use src\shared\infrastructure\Kernel;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$kernel = Kernel::boot();
$kernel->handle()->send();
