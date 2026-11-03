<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

final class Contract
{
    public const SPEC_COMMIT = 'af1b834501210bfd197bf606163680129cc5d09b';
    public const SCHEMA_VERSION = 1;
    public const FINGERPRINT_VERSION = 1;

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
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function enabled(string $key, bool $default = false): bool
    {
        $value = getenv($key);
        return $value === false ? $default : in_array(strtolower(trim($value)), ['true', '1', 'yes', 'on'], true);
    }
}
