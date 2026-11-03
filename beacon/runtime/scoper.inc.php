<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

return [
    'prefix' => 'BeaconStandalone',
    'finders' => [Finder::create()->files()->in(getenv('BEACON_SCOPE_SOURCE'))],
    'exclude-namespaces' => ['OpenTelemetry\\Instrumentation'],
    'exclude-functions' => ['app', 'config', 'now', 'collect', 'data_get', 'data_set', 'event', 'report', 'rescue', 'tap', 'value', 'with'],
    'exclude-constants' => ['LARAVEL_START', 'BEACON_STANDALONE_LOADED', 'BEACON_STANDALONE_RUNTIME_ROOT', 'BEACON_STANDALONE_STATUS'],
    'expose-global-constants' => false,
    'expose-global-classes' => false,
    'expose-global-functions' => false,
    'patchers' => [
        static function (string $path, string $prefix, string $contents): string {
            // Instrumentation observes application objects. Keep their type identities global;
            // the instrumentation's OTel API/SDK and its transport dependencies stay isolated.
            if (str_contains(str_replace('\\', '/', $path), '/open-telemetry/opentelemetry-auto-')) {
                foreach (['Psr', 'Illuminate', 'Laravel', 'Symfony', 'GuzzleHttp'] as $namespace) {
                    $contents = str_replace($prefix . '\\' . $namespace . '\\', $namespace . '\\', $contents);
                    $contents = str_replace($prefix . '\\\\' . $namespace . '\\\\', $namespace . '\\\\', $contents);
                }
            }
            // Protobuf's binary descriptors contain original PHP class names. Scope only
            // the PHP identities returned by descriptor construction; wire message names stay intact.
            $normalized = str_replace('\\', '/', $path);
            if (str_ends_with($normalized, '/google/protobuf/src/Google/Protobuf/Internal/Descriptor.php')
                || str_ends_with($normalized, '/google/protobuf/src/Google/Protobuf/Internal/EnumDescriptor.php')) {
                $marker = '$desc->setClass($classname);';
                if (!str_contains($contents, $marker)) { throw new RuntimeException('Protobuf descriptor scoping anchor changed'); }
                $adjust = 'foreach ([&$classname, &$legacy_classname, &$previous_classname] as &$phpClass) {'
                    . ' if (is_string($phpClass) && !str_starts_with($phpClass, ' . var_export($prefix . '\\', true) . ')) { $phpClass = '
                    . var_export($prefix . '\\', true) . ' . $phpClass; } } unset($phpClass);';
                $contents = str_replace($marker, $adjust . "\n" . $marker, $contents);
            }
            return $contents;
        },
    ],
];
