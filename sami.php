#!/usr/bin/env php
<?php

// installed via composer?
if (file_exists($a = __DIR__.'/../../autoload.php')) {
    require_once $a;
} elseif (!getenv("SAMI_COMPOSER_AUTOLOAD")) {
    throw new \Exception('Cannot find composer dependencies. set `SAMI_COMPOSER_AUTOLOAD` environment variable.');
} else {
    require_once getenv("SAMI_COMPOSER_AUTOLOAD");
}

use Sami\Console\Application;

$application = new Application();
$application->run();
