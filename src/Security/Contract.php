<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

final class Contract
{
    public const SPEC_COMMIT = 'd9d76e5a41d974d294bb596e06918bb954319bf6';
    public const SCHEMA_VERSION = 2;
    public const FINGERPRINT_VERSION = 1;

    public const OUTPUT_PROFILE = 'beacon-security-log-body-v1';

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    public static function processId(): string
    {
        static $id;
        if ($id !== null) { return $id; }
        $stat = @file_get_contents('/proc/self/stat');
        $boot = @file_get_contents('/proc/sys/kernel/random/boot_id');
        if ($stat === false || $boot === false) { return $id = self::uuid(); }
        $fields = preg_split('/\s+/', substr($stat, strrpos($stat, ')') + 2));
        $hex = substr(hash('sha256', $boot . '|' . getmypid() . '|' . $fields[19]), 0, 32);
        return $id = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    public static function timestamp(float $time): string
    {
        return gmdate('Y-m-d\\TH:i:s', (int) $time) . sprintf('.%06dZ', (int) (($time - floor($time)) * 1000000));
    }

    public static function processTiming(): array
    {
        $stat = @file_get_contents('/proc/self/stat');
        $uptime = @file_get_contents('/proc/uptime');
        $boot = @file_get_contents('/proc/stat');
        if ($stat === false || $uptime === false || $boot === false || !preg_match('/^btime ([0-9]+)$/m', $boot, $bootTime)) { throw new \RuntimeException('Process timing unavailable'); }
        // Linux exports /proc process start in USER_HZ ticks (100 per second).
        $fields = preg_split('/\s+/', substr($stat, strrpos($stat, ')') + 2));
        $elapsed = max(0, (float) explode(' ', $uptime)[0] - ((int) $fields[19]) / 100);
        static $started;
        $started ??= self::timestamp((int) $bootTime[1] + ((int) $fields[19]) / 100);
        return ['started_at' => $started, 'uptime_ms' => (int) ($elapsed * 1000)];
    }

    public static function fingerprint(array $input): string
    {
        $sink = $input['sink'];
        $normalize = static fn (string $s): string => json_decode(self::json($s), true, flags: JSON_THROW_ON_ERROR);
        $sources = array_values(array_unique(array_map($normalize, $input['source_signatures'])));
        // PHP strings are UTF-8 byte sequences; the contract sorts by UTF-16 code units.
        usort($sources, static fn (string $a, string $b): int => strcmp(
            mb_convert_encoding($a, 'UTF-16BE', 'UTF-8'),
            mb_convert_encoding($b, 'UTF-16BE', 'UTF-8'),
        ));
        $segments = ['1', $input['application_id'], $input['language'], $input['rule'],
            $sink['role'], $sink['function'], $sink['location'], $sink['operation'],
            $sink['path_role'], $sink['input_part'], ...$sources];
        $hash = hash_init('sha256');
        foreach ($segments as $segment) {
            $segment = $normalize($segment);
            hash_update($hash, pack('N', strlen($segment)) . $segment);
        }
        return 'finding-' . hash_final($hash);
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    public static function enabled(string $key, bool $default = false): bool
    {
        $value = getenv($key);
        return $value === false ? $default : in_array(strtolower(trim($value)), ['true', '1', 'yes', 'on'], true);
    }
}
