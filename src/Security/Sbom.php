<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

use Composer\InstalledVersions;

final class Sbom
{
    public static function snapshot(Events $events): void
    {
        $timing = Contract::processTiming();
        $dependencies = [];
        foreach (get_loaded_extensions() as $extension) {
            $dependencies[] = ['name' => 'php-ext/' . $extension, 'version' => phpversion($extension) ?: ''];
        }
        $reasons = ['runtime_observation_is_not_complete_inventory', 'included_files_do_not_prove_execution_reachability'];
        // InstalledVersions is metadata, so only include packages whose path contains an included file.
        $files = array_slice(get_included_files(), 0, 8192);
        if (count(get_included_files()) > 8192) {
            throw new \OverflowException('Included file budget exceeded');
        }
        $candidates = [];
        if (class_exists(InstalledVersions::class)) {
            $packages = InstalledVersions::getInstalledPackages();
            if (count($packages) > 2048) {
                throw new \OverflowException('Package budget exceeded');
            }
            foreach (array_slice($packages, 0, 2048) as $package) {
                $candidates[] = ['name' => $package, 'version' => InstalledVersions::getPrettyVersion($package) ?? '', 'path' => InstalledVersions::getInstallPath($package)];
            }
        } else {
            $reasons[] = 'composer_runtime_metadata_unavailable';
        }
        // The isolated distribution owns a separate Composer registry. Inspect business
        // metadata only through its own already-registered application autoloader.
        $application = $GLOBALS['beacon.standalone.application_packages'] ?? null;
        if (is_callable($application)) {
            try {
                $observed = $application();
                array_push($candidates, ...$observed['packages']);
                if ($observed['truncated']) { throw new \OverflowException('Application package budget exceeded'); }
            } catch (\Throwable $error) { throw new \RuntimeException('Application package scan failed', previous: $error); }
        }
        $seen = [];
        foreach ($candidates as $package) {
            $path = is_string($package['path']) ? realpath($package['path']) : false;
            $key = $package['name'] . '|' . $package['version'];
            if ($path === false || isset($seen[$key])) { continue; }
            foreach ($files as $file) {
                if (str_starts_with($file, $path . DIRECTORY_SEPARATOR)) {
                    $dependencies[] = ['name' => $package['name'], 'version' => $package['version']];
                    $seen[$key] = true;
                    break;
                }
            }
        }
        usort($dependencies, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        $events->inventory($dependencies, $timing);
        $events->cyclonedx($dependencies, $reasons);
    }
}
