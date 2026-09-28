<?php

declare(strict_types=1);

namespace Beacon\PHP;

final class ComposerInstaller
{
    public function __construct(private readonly string $binary = 'composer')
    {
    }

    /** @param list<string> $packages */
    public function command(array $packages): string
    {
        $arguments = array_merge([$this->binary, 'require'], $packages, ['--no-interaction']);

        return implode(' ', array_map(escapeshellarg(...), $arguments));
    }

    public function run(string $command): int
    {
        passthru($command, $status);

        return $status;
    }
}
