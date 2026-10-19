<?php
declare(strict_types=1);

// Startet alle Tests: php tests/run.php
require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/support.php';

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

exit(Tests::run() ? 0 : 1);
