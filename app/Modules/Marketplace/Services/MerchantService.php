<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Services;

use App\Modules\Marketplace\Models\Merchant;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MerchantService
{
    public function approve(Merchant $merchant): Merchant
    {
        if ($merchant->status !== 'pending') {
            throw new InvalidArgumentException("Merchant dengan status '{$merchant->status}' tidak dapat disetujui.");
        }

        $merchant->update([
            'status' => 'active',
            'verified_at' => now(),
        ]);

        return $merchant;
    }

    public function reject(Merchant $merchant, ?string $reason = null): Merchant
    {
        if ($merchant->status === 'closed') {
            throw new InvalidArgumentException("Merchant sudah berstatus 'closed'.");
        }

        $merchant->update([
            'status' => 'closed',
        ]);

        return $merchant;
    }

    public function suspend(Merchant $merchant, ?string $reason = null): Merchant
    {
        if ($merchant->status !== 'active') {
            throw new InvalidArgumentException("Hanya merchant dengan status 'active' yang dapat disuspend. Status saat ini: '{$merchant->status}'.");
        }

        $merchant->update([
            'status' => 'suspended',
        ]);

        return $merchant;
    }

    public function activate(Merchant $merchant): Merchant
    {
        if ($merchant->status !== 'suspended') {
            throw new InvalidArgumentException("Hanya merchant dengan status 'suspended' yang dapat diaktifkan kembali. Status saat ini: '{$merchant->status}'.");
        }

        $merchant->update([
            'status' => 'active',
        ]);

        return $merchant;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Merchant
    {
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug((string) $data['name']);
        }

        return Merchant::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Merchant $merchant, array $data): Merchant
    {
        if (isset($data['name']) && $data['name'] !== $merchant->name) {
            $data['slug'] = $this->generateUniqueSlug((string) $data['name'], $merchant->id);
        }

        $merchant->update($data);

        return $merchant;
    }

    public function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $i = 1;

        while (Merchant::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        return $slug;
    }
}
