<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

use Composer\InstalledVersions;

final class Sbom
{
    public static function snapshot(Events $events): void
    {
        $dependencies = [];
        foreach (get_loaded_extensions() as $extension) {
            $dependencies[] = ['name' => 'php-ext/' . $extension, 'version' => phpversion($extension) ?: ''];
        }
        $reasons = ['runtime_observation_is_not_complete_inventory', 'included_files_do_not_prove_execution_reachability'];
        // InstalledVersions is metadata, so only include packages whose path contains an included file.
        $files = array_slice(get_included_files(), 0, 8192);
        if (count(get_included_files()) > 8192) {
            $reasons[] = 'included_file_budget';
        }
        if (class_exists(InstalledVersions::class)) {
            $packages = InstalledVersions::getInstalledPackages();
            if (count($packages) > 2048) {
                $reasons[] = 'package_budget';
            }
            foreach (array_slice($packages, 0, 2048) as $package) {
                $path = InstalledVersions::getInstallPath($package);
                $path = $path === null ? false : realpath($path);
                if ($path === false) {
                    continue;
                }
                foreach ($files as $file) {
                    if (str_starts_with($file, $path . DIRECTORY_SEPARATOR)) {
                        $dependencies[] = ['name' => $package, 'version' => InstalledVersions::getPrettyVersion($package) ?? ''];
                        break;
                    }
                }
            }
        } else {
            $reasons[] = 'composer_runtime_metadata_unavailable';
        }
        usort($dependencies, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $id = 'sbom-' . hash('sha256', $events->applicationId() . '|' . $events->instanceId() . '|php');
        $release = 'runtime-' . hash('sha256', Contract::json($dependencies));
        $parts = array_chunk($dependencies, 100) ?: [[]];
        foreach ($parts as $index => $part) {
            $events->emit('beacon.security.sbom.snapshot', [
                'sbom_id' => $id, 'revision' => 1, 'release_id' => $release, 'status' => 'observed',
                'completeness' => 'partial', 'reasons' => $reasons, 'component_count' => count($dependencies),
                'part_index' => $index, 'part_count' => count($parts), 'dependencies' => $part,
            ], 'beacon_security_sbom');
        }
        $events->cyclonedx($dependencies, $reasons);
    }
}
