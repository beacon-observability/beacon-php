<?php

declare(strict_types=1);

// Deliberately independent of Composer's global ClassLoader and PSR class identities.
(static function (): void {
    $root = __DIR__;
    $map = require $root . '/classmap.php';
    spl_autoload_register(static function (string $class) use ($root, $map): void {
        if (isset($map[$class])) { require $root . '/' . $map[$class]; }
    }, true, false);
    foreach (require $root . '/autoload-files.php' as $file) { require_once $root . '/' . $file; }
})();
