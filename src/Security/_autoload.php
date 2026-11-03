<?php

declare(strict_types=1);

if (getenv('COMPOSER_DEV_MODE') === false) {
    \Beacon\PHP\Security\Runtime::boot();
}
