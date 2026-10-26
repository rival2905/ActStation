<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use App\Services\AgentOfficeAdminAccess;
use App\Services\AgentOfficeSecurity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AgentOfficeSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(AgentOfficeAdminAccess::class)->authorise($this);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('entries');
        if (! is_array($rows)) {
            return;
        }
        foreach ($rows as $index => &$row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['id'] ?? '') === 'new' && ($row['cidr'] ?? '') === '' && ($row['label'] ?? '') === '' && empty($row['expires_at'])) {
                unset($rows[$index]);

                continue;
            }
            if (is_string($row['cidr'] ?? null) && filter_var($row['cidr'], FILTER_VALIDATE_IP) && inet_ntop(inet_pton($row['cidr'])) === $row['cidr']) {
                $row['cidr'] .= str_contains($row['cidr'], ':') ? '/128' : '/32';
            }
            if (($row['id'] ?? '') === 'new') {
                $row['id'] = bin2hex(random_bytes(16));
            }
        }
        unset($row);
        $this->merge(['entries' => array_values($rows)]);
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'entries' => ['present', 'array', 'max:100'],
            'entries.*' => ['required', 'array:id,cidr,label,enabled,expires_at'],
            'entries.*.id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/D', 'distinct'],
            'entries.*.cidr' => ['required', 'string', 'distinct', function ($attribute, $value, $fail): void {
                if (! is_string($value) || ! AgentOfficeSecurity::validCidr($value)) {
                    $fail('IP/CIDR harus canonical; wildcard dan /0 ditolak.');
                }
            }],
            'entries.*.label' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1f\x7f]/'],
            'entries.*.enabled' => ['required', 'boolean'],
            'entries.*.expires_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:now'],
            'confirm_broad_risk' => ['sometimes', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $rows = $this->input('entries');
            if (! is_array($rows)) {
                return;
            }
            foreach ($rows as $row) {
                $cidr = is_array($row) ? ($row['cidr'] ?? null) : null;
                if (is_string($cidr) && AgentOfficeSecurity::validCidr($cidr)) {
                    [$ip, $bits] = explode('/', $cidr);
                    if ((int) $bits < (str_contains($ip, ':') ? 64 : 24) && ! $this->boolean('confirm_broad_risk')) {
                        $validator->errors()->add('confirm_broad_risk', 'Konfirmasi risiko rentang luas wajib.');
                    }
                }
            }
        }];
    }

    public function entries(): array
    {
        return array_map(fn (array $row): array => [
            'id' => $row['id'], 'cidr' => $row['cidr'], 'label' => $row['label'],
            'enabled' => (bool) $row['enabled'],
            'expires_at' => empty($row['expires_at']) ? null : CarbonImmutable::createFromFormat('Y-m-d\TH:i', $row['expires_at'], 'UTC')->timestamp,
        ], $this->validated('entries'));
    }
}
