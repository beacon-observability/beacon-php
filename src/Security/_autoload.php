<?php

declare(strict_types=1);

if (getenv('COMPOSER_DEV_MODE') === false && !defined('BEACON_STANDALONE_LOADED')) {
    \Beacon\PHP\Security\Runtime::boot();
}
