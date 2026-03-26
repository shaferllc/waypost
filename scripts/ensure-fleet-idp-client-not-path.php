<?php

declare(strict_types=1);

$lockPath = dirname(__DIR__).'/composer.lock';
if (! is_file($lockPath)) {
    fwrite(STDERR, "composer.lock not found.\n");

    exit(1);
}

$json = json_decode((string) file_get_contents($lockPath), true);
if (! is_array($json)) {
    fwrite(STDERR, "composer.lock is not valid JSON.\n");

    exit(1);
}

foreach ($json['packages'] ?? [] as $pkg) {
    if (($pkg['name'] ?? '') !== 'shaferllc/fleet-idp-client') {
        continue;
    }
    $type = $pkg['dist']['type'] ?? '';
    if ($type === 'path') {
        fwrite(STDERR, <<<'TXT'
composer.lock still pins shaferllc/fleet-idp-client to a path repository (../fleet-idp-client).
Run from the app root:

  composer update shaferllc/fleet-idp-client

Then commit the updated composer.lock. Remove any project or global Composer path repo
for this package if you added one (composer config --list --source).

TXT);
        exit(1);
    }

    exit(0);
}

fwrite(STDERR, "shaferllc/fleet-idp-client missing from composer.lock — run composer update.\n");

exit(1);
