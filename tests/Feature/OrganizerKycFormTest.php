<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\MasterData\Models\Wilayah;
use App\Modules\MasterData\Models\WilayahKodepos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->member = User::factory()->create([
        'name' => 'Budi Penyelenggara',
        'email' => 'budi.member@test.com',
        'phone_number' => '081234567890',
        'is_active' => true,
    ]);
    $this->member->assignRole('member');

    Wilayah::query()->firstOrCreate(['kode' => '32'], ['nama' => 'Jawa Barat']);
    Wilayah::query()->firstOrCreate(['kode' => '32.73'], ['nama' => 'Kota Bandung']);
    Wilayah::query()->firstOrCreate(['kode' => '32.73.01'], ['nama' => 'Coblong']);
    Wilayah::query()->firstOrCreate(['kode' => '32.73.01.1001'], ['nama' => 'Dago']);
    WilayahKodepos::query()->firstOrCreate(['kode' => '32.73.01.1001'], ['kodepos' => '40135']);
});

test('member can view complete organizer kyc apply form with identity, address, npwp and bank sections', function (): void {
    $response = $this->actingAs($this->member)->get(route('panel.organizer.apply'));

    $response->assertOk();

    // Falcon card title & subtitle intent
    $response->assertSee('Identitas & Informasi Bank');
    $response->assertSee('penanggung jawab', false);

    // Section headings
    $response->assertSee('Alamat', false);
    $response->assertSee('KTP', false);
    $response->assertSee('NPWP', false);
    $response->assertSee('Bank', false);

    // Form inputs and prefill
    $response->assertSee('name="identity_number"', false);
    $response->assertSee('name="identity_name"', false);
    $response->assertSee('value="Budi Penyelenggara"', false); // identity_name fallback to user.name
    $response->assertSee('name="birth_place"', false);
    $response->assertSee('name="birth_date"', false);
    $response->assertSee('name="identity_occupation"', false);
    $response->assertSee('name="identity_address"', false);
    $response->assertSee('name="emergency_contact"', false);
    $response->assertSee('value="081234567890"', false); // emergency_contact fallback
    $response->assertSee('name="identity_image"', false);

    // Region cascading inputs
    $response->assertSee('name="province_id"', false);
    $response->assertSee('name="city_id"', false);
    $response->assertSee('name="subdistrict_id"', false);
    $response->assertSee('name="village_id"', false);
    $response->assertSee('name="postal_code"', false);
    $response->assertSee('name="address"', false);
    $response->assertSee('name="rt"', false);
    $response->assertSee('name="rw"', false);

    // ERP Readonly Full Address Preview (without name attribute)
    $response->assertSee('id="full_address_preview"', false);
    $response->assertDontSee('name="full_address_preview"');
    $response->assertSee('readonly', false);
    $response->assertSee('updateFullAddressPreview', false);

    // NPWP inputs & explanation
    $response->assertSee('name="npwp_number"', false);
    $response->assertSee('name="npwp_name"', false);
    $response->assertSee('opsional', false);
    $response->assertSee('wajib isi nama', false);

    // Bank inputs, validation messages & disclaimers
    $response->assertSee('name="bank_name"', false);
    $response->assertSee('name="bank_account_number"', false);
    $response->assertSee('name="bank_account_holder"', false);
    $response->assertSee('name="bank_branch"', false);
    $response->assertSee('Mohon isi nama rekening Anda minimal 3 karakter.');
    $response->assertSee('Mohon isi cabang bank minimal 3 karakter.');

    // Security & disclaimers: no false 'encrypted disk' claim, no auto-validation promise
    $response->assertDontSee('terenkripsi');
    $response->assertDontSee('otomatis divalidasi');
    $response->assertDontSee('auto-validated');

    // Region component uses panel.regions route prefix
    $response->assertSee('panel/regions/provinces', false);
});

test('member can access panel region json endpoints', function (): void {
    $provinces = $this->actingAs($this->member)->get(route('panel.regions.provinces'));
    $provinces->assertOk()->assertJsonFragment(['kode' => '32', 'nama' => 'Jawa Barat']);

    $cities = $this->actingAs($this->member)->get(route('panel.regions.cities', ['parent_id' => '32']));
    $cities->assertOk()->assertJsonFragment(['kode' => '32.73', 'nama' => 'Kota Bandung']);

    $districts = $this->actingAs($this->member)->get(route('panel.regions.districts', ['parent_id' => '32.73']));
    $districts->assertOk()->assertJsonFragment(['kode' => '32.73.01', 'nama' => 'Coblong']);

    $villages = $this->actingAs($this->member)->get(route('panel.regions.villages', ['parent_id' => '32.73.01']));
    $villages->assertOk()->assertJsonFragment(['kode' => '32.73.01.1001', 'nama' => 'Dago']);

    $postalCode = $this->actingAs($this->member)->get(route('panel.regions.postal-code', ['village_id' => '32.73.01.1001']));
    $postalCode->assertOk()->assertJson(['kodepos' => '40135']);
});

test('guest is rejected from panel region endpoints and organizer kyc apply form', function (): void {
    $this->get(route('panel.organizer.apply'))->assertRedirect(route('panel.login'));
    $this->get('/panel/regions/provinces')->assertRedirect(route('panel.login'));
});

test('member cannot access admin region endpoints and stays 403', function (): void {
    $response = $this->actingAs($this->member)->get('/admin/regions/provinces');
    $response->assertForbidden();
});
