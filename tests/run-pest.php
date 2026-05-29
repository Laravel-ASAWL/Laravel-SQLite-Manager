<?php

declare(strict_types=1);

$binaries = [
    __DIR__.'/../vendor/bin/pest',
    __DIR__.'/../../vendor/bin/pest',
];

$pest = null;

foreach ($binaries as $binary) {
    if (is_file($binary)) {
        $pest = $binary;
        break;
    }
}

if ($pest === null) {
    fwrite(STDERR, "Unable to locate pest. Run composer install first.\n");
    exit(1);
}

$vendor = dirname($pest, 2);
$skeleton = $vendor.'/orchestra/testbench-core/laravel';

if (is_dir($skeleton)) {
    putenv('APP_BASE_PATH='.$skeleton);
}

putenv('TELESCOPE_ENABLED=false');
putenv('SESSION_DRIVER=array');

$arguments = array_map(escapeshellarg(...), array_slice($argv, 1));
$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($pest);

if ($arguments !== []) {
    $command .= ' '.implode(' ', $arguments);
}

passthru($command, $status);
exit($status);
