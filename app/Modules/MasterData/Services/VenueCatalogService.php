<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Services;

use App\Contracts\VenueCatalog;
use App\Modules\MasterData\Models\Venue;
use App\Modules\MasterData\Models\VenueCourt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VenueCatalogService implements VenueCatalog
{
    public function allowsPanelCreation(bool $court): bool
    {
        $key = $court ? 'allow_panel_court_creation' : 'allow_panel_venue_creation';
        $value = DB::table('settings')->where('group', 'venues')->where('key', $key)->value('value');

        return $value === null ? true : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function createVenue(array $data): int
    {
        if (Venue::where('name', $data['venue_name'])->where('address', $data['address'])->exists()) {
            throw ValidationException::withMessages(['venue_name' => 'Venue dengan nama dan alamat ini sudah tersedia. Pilih dari daftar.']);
        }
        $path = null;
        try {
            $path = isset($data['image']) ? $data['image']->store('venues', 'public') : null;

            return (int) Venue::create([
                'name' => $data['venue_name'], 'address' => $data['address'],
                'province_id' => $data['province_id'], 'city_id' => $data['city_id'],
                'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                'image' => $path, 'is_active' => true,
            ])->id;
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $error;
        }
    }

    public function createCourt(int $venueId, array $data): int
    {
        $venue = Venue::query()->lockForUpdate()->findOrFail($venueId);
        if (! $venue->is_active) {
            throw ValidationException::withMessages(['venue_id' => 'Venue tidak aktif. Hubungi admin.']);
        }
        if ($venue->courts()->where('name', $data['court_name'])->exists()) {
            throw ValidationException::withMessages(['court_name' => 'Nama lapangan sudah digunakan di venue ini. Pilih lapangan yang tersedia.']);
        }
        try {
            return (int) VenueCourt::create([
                'venue_id' => $venueId, 'name' => $data['court_name'], 'surface' => $data['surface'],
                'condition' => $data['condition'], 'is_indoor' => $data['is_indoor'], 'is_active' => true,
            ])->id;
        } catch (QueryException $error) {
            if ($error->getCode() === '23000') {
                throw ValidationException::withMessages(['court_name' => 'Nama lapangan sudah digunakan di venue ini.']);
            }
            throw $error;
        }
    }
}
