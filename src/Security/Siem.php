<?php

declare(strict_types=1);

namespace Beacon\PHP\Security;

/** Optional historical SIEM reference; producers use LogRecord. */
final class Siem
{
    public static function normalize(string $raw, array $resource = [], array $context = []): array
    {
        $body = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $inventory = ($body['event_name'] ?? '') === 'beacon.security.sbom.inventory';
        $source = $inventory ? 'beacon_security_sbom' : 'beacon_security';
        if (($body['schema_version'] ?? null) !== 2 || ($body['source'] ?? '') !== $source
            || (!$inventory && ($body['event_name'] ?? '') !== 'beacon.security.finding')
            || array_key_exists('server_span_id', $body) || array_key_exists('current_span_id', $body)) {
            throw new \InvalidArgumentException('Unsupported Security event');
        }
        $text = static fn (mixed $value): string => is_string($value) && trim($value) !== '' ? $value : '';
        $id = $inventory ? 'inventory-' . hash('sha256', Contract::json([
            $body['source'], $body['event_name'], $body['instance_id'], $body['observed_at'], (string) ($body['part_index'] ?? 0),
        ])) : $body['occurrence_id'];
        $ip = $text($context['client_ip'] ?? null);
        $tenant = $text($context['tenant_id'] ?? null);
        if ($tenant === '') { $tenant = $text($resource['tenant.id'] ?? null); }
        return [
            'time' => $body['observed_at'], 'event_id' => $id,
            'event_class' => $inventory ? 'software_inventory_info' : 'security_finding',
            'source' => $source, 'service' => $text($resource['service.name'] ?? null),
            'tenant_id' => $tenant === '' ? 'default' : $tenant,
            'actor' => $inventory ? '' : $text($context['actor'] ?? null),
            'ip' => !$inventory && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '',
            'action' => $inventory ? 'inventory_snapshot' : $body['rule'],
            'target' => $inventory ? $body['instance_id'] : $body['finding_id'],
            'target_type' => $inventory ? 'process' : 'finding', 'outcome' => $inventory ? 'success' : 'unknown',
            'status' => 'info', 'severity' => 'informational', 'message' => $raw,
        ] + array_intersect_key($body, array_flip(['instance_id', 'trace_id', 'span_id', 'trace_flags']));
    }
}
