<?php

declare(strict_types=1);

namespace Beacon\PHP;

use Composer\InstalledVersions;
use InvalidArgumentException;

final class ComponentRegistry
{
    /** @return array<string, string> */
    public static function all(): array
    {
        static $components;
        if ($components === null) {
            $decoded = json_decode(
                (string) file_get_contents(dirname(__DIR__) . '/resources/components.json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Beacon component manifest must contain a JSON object.');
            }
            /** @var array<string, string> $decoded */
            $components = $decoded;
        }

        return $components;
    }

    /** @param list<string> $aliases @return list<string> */
    public static function resolve(array $aliases): array
    {
        $packages = [];
        $components = self::all();
        foreach (array_values(array_unique($aliases)) as $alias) {
            if (!isset($components[$alias])) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown component "%s". Run `beacon-php components` to list supported aliases.',
                    $alias,
                ));
            }
            $packages[] = $components[$alias];
        }

        return $packages;
    }

    /** @return array<string, array{package: string, installed: bool, version: ?string}> */
    public static function withInstallationStatus(): array
    {
        $result = [];
        foreach (self::all() as $alias => $package) {
            $installed = InstalledVersions::isInstalled($package);
            $result[$alias] = [
                'package' => $package,
                'installed' => $installed,
                'version' => $installed ? InstalledVersions::getPrettyVersion($package) : null,
            ];
        }

        return $result;
    }

    private function __construct()
    {
    }
}
