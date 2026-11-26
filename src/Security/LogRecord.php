<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

/** Move event metadata once, preserving the remaining v2 object. */
final class LogRecord
{
    public static function bind(array $event): array
    {
        $inventory = ($event['event_name'] ?? '') === 'beacon.security.sbom.inventory';
        if (($event['schema_version'] ?? null) !== 2 || ($event['source'] ?? '') !== ($inventory ? 'beacon_security_sbom' : 'beacon_security')
            || (!$inventory && ($event['event_name'] ?? '') !== 'beacon.security.finding')) {
            throw new \InvalidArgumentException('Unsupported Security event');
        }
        $time = self::timestamp($event['observed_at']);
        $id = $inventory ? 'inventory-' . hash('sha256', Contract::json([
            $event['source'], $event['event_name'], $event['instance_id'], $event['observed_at'], (string) ($event['part_index'] ?? 0),
        ])) : $event['occurrence_id'];
        if (!is_string($id) || $id === '') { throw new \InvalidArgumentException('Missing Security event ID'); }
        unset($event['observed_at'], $event['occurrence_id']);
        return ['time_unix_nano' => (string) $time, 'body' => Contract::json($event),
            'attributes' => ['event_id' => $id, 'source' => $event['source']]];
    }

    private static function timestamp(string $value): int
    {
        if (PHP_INT_SIZE !== 8 || !preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,9}))?(Z|[+-]\d{2}:\d{2})$/D', $value, $match)) {
            throw new \InvalidArgumentException('Invalid Security event time');
        }
        $date = new \DateTimeImmutable($match[1] . $match[3]);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) { throw new \InvalidArgumentException('Invalid Security event time'); }
        $seconds = $date->getTimestamp();
        $fraction = (int) str_pad($match[2], 9, '0');
        if ($seconds < 0 || $seconds > 9223372036 || ($seconds === 9223372036 && $fraction > 854775807)) {
            throw new \InvalidArgumentException('Security event time out of range');
        }
        $time = $seconds * 1000000000 + $fraction;
        if ($time === 0) { throw new \InvalidArgumentException('Unset Security event time'); }
        return $time;
    }
}
