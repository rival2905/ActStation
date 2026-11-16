<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AGENT_OFFICE_ENABLED', false),
    'social_relay_enabled' => (bool) env('AGENT_OFFICE_SOCIAL_RELAY_ENABLED', false),
    'social_relay_accounts' => ['actstationcontinent'],
    'allow_loopback_http' => (bool) env('AGENT_OFFICE_LOOPBACK_HTTP', false),
    // Dedicated final proxy only; no implicit CDN, tunnel or framework proxy trust.
    'trusted_proxies' => array_values(array_filter(explode(',', (string) env('AGENT_OFFICE_TRUSTED_PROXIES', '')))),
    'operator_ids' => array_map('intval', array_values(array_filter(explode(',', (string) env('AGENT_OFFICE_OPERATOR_IDS', '')), fn (string $id): bool => preg_match('/^[1-9][0-9]*$/D', $id) === 1))),
    'private_acl_required' => true,
    'admin_mfa_path' => storage_path('app/private/agent-office/admin-mfa.enc'),
    'registry_path' => storage_path('app/private/agent-office/registry.enc'),
    'state_path' => storage_path('app/private/agent-office/state.json'),
    'audit_path' => storage_path('app/private/agent-office/audit.jsonl'),
    'master_key' => env('AGENT_OFFICE_MASTER_KEY'),
];
