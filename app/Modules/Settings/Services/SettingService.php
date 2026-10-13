<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingService
{
    public const MASK = '••••••••';

    /**
     * @var list<string>
     */
    public const SENSITIVE_KEYS = [
        'midtrans_server_key',
        'midtrans_client_key',
        'xendit_secret_key',
        'mail_password',
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $defaults = [
        'general' => [
            'app_name' => 'ActStation',
            'app_description' => '',
            'contact_email' => '',
            'contact_phone' => '',
        ],
        'payment' => [
            'payment_gateway_default' => 'midtrans',
            'midtrans_server_key' => '',
            'midtrans_client_key' => '',
            'midtrans_is_production' => false,
            'xendit_secret_key' => '',
            'xendit_public_key' => '',
        ],
        'email' => [
            'mail_mailer' => 'smtp',
            'mail_host' => '',
            'mail_port' => 587,
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => 'tls',
            'mail_from_address' => '',
            'mail_from_name' => 'ActStation',
        ],
    ];

    public static function isMasked(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return $value === self::MASK || $value === '********';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        /** @var array<string, string|null> $settings */
        $settings = Cache::rememberForever('settings:all', function (): array {
            return Setting::all()->pluck('value', 'key')->all();
        });

        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $rawValue = $settings[$key];
        if (in_array($key, self::SENSITIVE_KEYS, true) && ! empty($rawValue)) {
            try {
                $rawValue = Crypt::decryptString($rawValue);
            } catch (\Throwable) {
                // Return as is if already plain
            }
        }

        return $rawValue;
    }

    public function set(string $group, string $key, mixed $value, string $type = 'string'): void
    {
        $isSensitive = in_array($key, self::SENSITIVE_KEYS, true);

        if ($isSensitive) {
            if (self::isMasked(is_scalar($value) ? (string) $value : null)) {
                return;
            }

            $storedValue = Crypt::encryptString((string) $value);
        } else {
            $storedValue = is_array($value) ? json_encode($value) : (is_null($value) ? null : (string) $value);
        }

        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $storedValue, 'type' => $type]
        );

        $this->invalidateCache($group);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            $type = is_bool($value)
                ? 'boolean'
                : (is_numeric($value) ? 'number' : (is_array($value) ? 'json' : 'string'));

            $this->set($group, (string) $key, $value, $type);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroup(string $group, bool $maskSensitive = false): array
    {
        $defaults = $this->defaults[$group] ?? [];

        /** @var array<string, string|null> $cached */
        $cached = Cache::rememberForever("settings:group:{$group}", function () use ($group): array {
            return Setting::where('group', $group)->pluck('value', 'key')->all();
        });

        $result = $defaults;

        foreach ($cached as $key => $value) {
            $isSensitive = in_array($key, self::SENSITIVE_KEYS, true);

            if ($isSensitive) {
                if ($maskSensitive) {
                    $result[$key] = ! empty($value) ? self::MASK : '';
                } else {
                    if (! empty($value)) {
                        try {
                            $result[$key] = Crypt::decryptString($value);
                        } catch (\Throwable) {
                            $result[$key] = $value;
                        }
                    } else {
                        $result[$key] = '';
                    }
                }
            } else {
                $defaultType = isset($defaults[$key]) ? gettype($defaults[$key]) : 'string';
                $result[$key] = match ($defaultType) {
                    'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    'integer' => is_numeric($value) ? (int) $value : $value,
                    'double' => is_numeric($value) ? (float) $value : $value,
                    default => $value,
                };
            }
        }

        return $result;
    }

    public function invalidateCache(string $group): void
    {
        Cache::forget('settings:all');
        Cache::forget("settings:group:{$group}");
    }

    protected function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
