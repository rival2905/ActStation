<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Modules\Event\Http\Requests\Panel\OrganizerKycRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);

    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'JAWA BARAT'],
        ['kode' => '32.73', 'nama' => 'KOTA BANDUNG'],
        ['kode' => '32.73.01', 'nama' => 'SUKASARI'],
        ['kode' => '32.73.01.1001', 'nama' => 'SARIJADI'],
        ['kode' => '31', 'nama' => 'DKI JAKARTA'],
        ['kode' => '31.71', 'nama' => 'JAKARTA SELATAN'],
        ['kode' => '31.71.01', 'nama' => 'TEBET'],
        ['kode' => '31.71.01.1001', 'nama' => 'TEBET BARAT'],
    ]);

    $this->user = User::factory()->create([
        'name' => 'Doni Organizer',
        'email' => 'doni.organizer@test.com',
        'is_active' => true,
    ]);
    $this->user->assignRole('member');

    Storage::fake('local');

    $this->validPayload = [
        'organizer_mode' => 'individual',
        'identity_number' => '3273012345678901',
        'identity_name' => 'Doni Organizer',
        'birth_place' => 'Bandung',
        'birth_date' => '1995-05-15',
        'identity_occupation' => 'Wiraswasta',
        'identity_address' => 'Jl. Sarijadi Raya No. 10',
        'npwp_number' => '01.234.567.8-901.000',
        'npwp_name' => 'Doni Organizer',
        'bank_name' => 'BCA',
        'bank_account_number' => '8887776665',
        'bank_account_holder' => '  doni organizer  ',
        'bank_branch' => 'KCP Bandung',
        'province_id' => '32',
        'city_id' => '32.73',
        'subdistrict_id' => '32.73.01',
        'village_id' => '32.73.01.1001',
        'postal_code' => '40151',
        'address' => 'Jl. Sarijadi No. 12',
        'rt' => '01',
        'rw' => '05',
        'emergency_contact' => '081299887766',
        'identity_image' => UploadedFile::fake()->image('ktp.jpg'),
        'identity_selfie' => UploadedFile::fake()->image('selfie.jpg'),
        'responsibility_statement' => UploadedFile::fake()->create('sptjm.pdf', 500, 'application/pdf'),
        'bank_proof' => UploadedFile::fake()->create('bank.pdf', 500, 'application/pdf'),
    ];
});

test('controller show exposes banks array and authenticated user', function (): void {
    $response = $this->actingAs($this->user)->get(route('panel.organizer.apply'));

    $response->assertOk();
    $response->assertViewIs('panel.organizer.apply');
    $response->assertViewHas('user', $this->user);
    $response->assertViewHas('banks', OrganizerKycRequest::BANKS);
    expect(OrganizerKycRequest::BANKS)->toHaveKeys(['BCA', 'Mandiri', 'BRI', 'BNI', 'BJB', 'CIMB Niaga']);
});

test('valid expanded KYC saves all fields, normalizes NPWP, and sets pending review until admin approves', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    $permUpdate = Permission::firstOrCreate(['name' => 'user.update', 'guard_name' => 'web']);
    $adminRole->givePermissionTo($permUpdate);
    $admin->assignRole('admin');

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $this->validPayload);

    $response->assertRedirect(route('panel.organizer.apply'));
    $response->assertSessionHas('success');

    $pending = $this->user->fresh();
    expect($pending->kyc_status)->toBe('pending')
        ->and($pending->hasRole('organizer'))->toBeFalse()
        ->and($pending->identity_number)->toBe('3273012345678901')
        ->and($pending->identity_name)->toBe('Doni Organizer')
        ->and($pending->birth_place)->toBe('Bandung')
        ->and($pending->birth_date->format('Y-m-d'))->toBe('1995-05-15')
        ->and($pending->identity_occupation)->toBe('Wiraswasta')
        ->and($pending->identity_address)->toBe('Jl. Sarijadi Raya No. 10')
        ->and($pending->npwp_number)->toBe('012345678901000')
        ->and($pending->npwp_name)->toBe('Doni Organizer')
        ->and($pending->bank_name)->toBe('BCA')
        ->and($pending->bank_account_number)->toBe('8887776665')
        ->and($pending->bank_account_holder)->toBe('doni organizer')
        ->and($pending->bank_branch)->toBe('KCP Bandung')
        ->and($pending->province_id)->toBe('32')
        ->and($pending->city_id)->toBe('32.73')
        ->and($pending->subdistrict_id)->toBe('32.73.01')
        ->and($pending->village_id)->toBe('32.73.01.1001')
        ->and($pending->postal_code)->toBe('40151')
        ->and($pending->address)->toBe('Jl. Sarijadi No. 12')
        ->and($pending->rt)->toBe('01')
        ->and($pending->rw)->toBe('05')
        ->and($pending->emergency_contact)->toBe('081299887766');

    // Admin approves
    $this->actingAs($admin)
        ->post(route('admin.organizer-kyc.approve', $this->user));

    $updated = $this->user->fresh();
    expect($updated->hasRole('organizer'))->toBeTrue()
        ->and($updated->kyc_status)->toBe('verified');
});

test('invalid form returns session errors and does not upgrade role to organizer', function (): void {
    $invalidPayload = $this->validPayload;
    $invalidPayload['identity_name'] = '';
    $invalidPayload['bank_account_number'] = '';

    $response = $this->actingAs($this->user)
        ->from(route('panel.organizer.apply'))
        ->post(route('panel.organizer.apply.store'), $invalidPayload);

    $response->assertRedirect(route('panel.organizer.apply'));
    $response->assertSessionHasErrors(['identity_name', 'bank_account_number']);

    $fresh = $this->user->fresh();
    expect($fresh->hasRole('organizer'))->toBeFalse()
        ->and($fresh->kyc_status)->toBe('unverified');
});

test('malformed array inputs are rejected without a server error', function (): void {
    foreach (['npwp_number', 'npwp_name', 'province_id', 'city_id', 'subdistrict_id', 'village_id'] as $field) {
        $payload = $this->validPayload;
        $payload[$field] = ['invalid'];
        $this->actingAs($this->user)->postJson(route('panel.organizer.apply.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }
});

test('bank holder and branch require at least three characters', function (): void {
    $payload = $this->validPayload;
    $payload['identity_name'] = 'Ab';
    $payload['bank_account_holder'] = 'Ab';
    $payload['bank_branch'] = 'BC';
    $this->actingAs($this->user)->postJson(route('panel.organizer.apply.store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors(['identity_name', 'bank_account_holder', 'bank_branch']);
});

test('identity_number must be exactly 16 digits', function (): void {
    $payload = $this->validPayload;
    $payload['identity_number'] = '12345';

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertSessionHasErrors('identity_number');
});

test('identity_number unique check ignores own NIK but rejects other user NIK without disclosing it', function (): void {
    $other = User::factory()->create([
        'identity_number' => '3273999988887777',
    ]);

    // Same NIK as other user -> must fail uniqueness
    $payload = $this->validPayload;
    $payload['identity_number'] = '3273999988887777';

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertSessionHasErrors('identity_number');
    $error = session('errors')->first('identity_number');
    expect($error)->not->toContain('3273999988887777');

    // Updating own NIK -> allowed
    $this->user->update(['identity_number' => '3273012345678901']);
    $payload['identity_number'] = '3273012345678901';

    $okResponse = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payload);

    $okResponse->assertRedirect(route('panel.organizer.apply'));
    $okResponse->assertSessionDoesntHaveErrors();
});

test('bank_account_holder must match identity_name case-insensitively and trimmed', function (): void {
    $payload = $this->validPayload;
    $payload['identity_name'] = 'Budi Santoso';
    $payload['bank_account_holder'] = 'Ahmad Dahlan';

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertSessionHasErrors('bank_account_holder');
});

test('bank_name must be one of allowed banks', function (): void {
    $payload = $this->validPayload;
    $payload['bank_name'] = 'Bank Ghaib';

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertSessionHasErrors('bank_name');
});

test('birth_date in future is rejected', function (): void {
    $payload = $this->validPayload;
    $payload['birth_date'] = Carbon::tomorrow()->format('Y-m-d');

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payload);

    $response->assertSessionHasErrors('birth_date');
});

test('wilayah must exist and adhere to hierarchy and regex', function (): void {
    // Non-existent region
    $payload = $this->validPayload;
    $payload['province_id'] = '99';

    $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payload)
        ->assertSessionHasErrors('province_id');

    // Bad chained wilayah (city not under province)
    $badChain = $this->validPayload;
    $badChain['province_id'] = '32';
    $badChain['city_id'] = '31.71'; // Jakarta city under Jabar province!

    $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $badChain)
        ->assertSessionHasErrors('city_id');

    // Subdistrict not under city
    $badSub = $this->validPayload;
    $badSub['province_id'] = '32';
    $badSub['city_id'] = '32.73';
    $badSub['subdistrict_id'] = '31.71.01'; // Jakarta subdistrict

    $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $badSub)
        ->assertSessionHasErrors('subdistrict_id');

    // Village not under subdistrict
    $badVillage = $this->validPayload;
    $badVillage['village_id'] = '31.71.01.1001';

    $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $badVillage)
        ->assertSessionHasErrors('village_id');
});

test('npwp optional but requires paired npwp_name and rejects npwp_name without number', function (): void {
    // Optional when both omitted
    $optionalPayload = $this->validPayload;
    unset($optionalPayload['npwp_number'], $optionalPayload['npwp_name']);

    $response = $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $optionalPayload);

    $response->assertRedirect(route('panel.organizer.apply'));
    $response->assertSessionDoesntHaveErrors();

    $this->user->update(['kyc_status' => 'unverified']);

    // NPWP name without NPWP number is rejected
    $badPayload = $this->validPayload;
    $badPayload['npwp_number'] = null;
    $badPayload['npwp_name'] = 'PT Maju Terus';

    $this->actingAs($this->user->fresh())
        ->post(route('panel.organizer.apply.store'), $badPayload)
        ->assertSessionHasErrors('npwp_name');

    $this->user->update(['kyc_status' => 'unverified']);

    // NPWP number without NPWP name is rejected
    $badPayload2 = $this->validPayload;
    $badPayload2['npwp_number'] = '012345678901234';
    $badPayload2['npwp_name'] = '';

    $this->actingAs($this->user->fresh())
        ->post(route('panel.organizer.apply.store'), $badPayload2)
        ->assertSessionHasErrors('npwp_name');
});

test('identity_image upload stores file and preserves existing file when omitted', function (): void {
    Storage::fake('local');

    $file = UploadedFile::fake()->image('ktp.jpg');
    $payloadWithFile = $this->validPayload;
    $payloadWithFile['identity_image'] = $file;

    $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $payloadWithFile)
        ->assertRedirect(route('panel.organizer.apply'));

    $storedPath = $this->user->fresh()->identity_image;
    expect($storedPath)->not->toBeNull();
    Storage::disk('local')->assertExists($storedPath);

    // Subsequent submission without upload preserves existing identity_image
    $payloadNoFile = $this->validPayload;
    unset($payloadNoFile['identity_image']);

    // Admin rejects to allow resubmission
    $this->user->update(['kyc_status' => 'rejected']);

    $this->actingAs($this->user->fresh())
        ->post(route('panel.organizer.apply.store'), $payloadNoFile)
        ->assertRedirect(route('panel.organizer.apply'));

    expect($this->user->fresh()->identity_image)->toBe($storedPath);
});

test('owner scope: updating kyc touches only authenticated user', function (): void {
    $otherUser = User::factory()->create([
        'name' => 'Other Person',
        'identity_name' => 'Original Other',
    ]);

    $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $this->validPayload)
        ->assertRedirect(route('panel.organizer.apply'));

    expect($otherUser->fresh()->identity_name)->toBe('Original Other');
});

test('PII fields are hidden from user serialization and redacted in activity logs', function (): void {
    $this->actingAs($this->user)
        ->post(route('panel.organizer.apply.store'), $this->validPayload);

    $fresh = $this->user->fresh();
    $array = $fresh->toArray();

    // 1. Hidden from serialization
    expect(array_key_exists('identity_number', $array))->toBeFalse()
        ->and(array_key_exists('identity_image', $array))->toBeFalse()
        ->and(array_key_exists('identity_name', $array))->toBeFalse()
        ->and(array_key_exists('identity_occupation', $array))->toBeFalse()
        ->and(array_key_exists('identity_address', $array))->toBeFalse()
        ->and(array_key_exists('npwp_number', $array))->toBeFalse()
        ->and(array_key_exists('npwp_name', $array))->toBeFalse()
        ->and(array_key_exists('birth_place', $array))->toBeFalse()
        ->and(array_key_exists('birth_date', $array))->toBeFalse()
        ->and(array_key_exists('bank_name', $array))->toBeFalse()
        ->and(array_key_exists('bank_account_number', $array))->toBeFalse()
        ->and(array_key_exists('bank_account_holder', $array))->toBeFalse()
        ->and(array_key_exists('bank_branch', $array))->toBeFalse()
        ->and(array_key_exists('emergency_contact', $array))->toBeFalse()
        ->and(array_key_exists('address', $array))->toBeFalse()
        ->and(array_key_exists('province_id', $array))->toBeFalse()
        ->and(array_key_exists('city_id', $array))->toBeFalse()
        ->and(array_key_exists('subdistrict_id', $array))->toBeFalse()
        ->and(array_key_exists('village_id', $array))->toBeFalse()
        ->and(array_key_exists('postal_code', $array))->toBeFalse()
        ->and(array_key_exists('rt', $array))->toBeFalse()
        ->and(array_key_exists('rw', $array))->toBeFalse();

    // 2. ActivityLog redacts sensitive attributes
    $log = ActivityLog::where('auditable_type', $fresh->getMorphClass())
        ->where('auditable_id', $fresh->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    $newValues = $log->new_values;

    expect($newValues['identity_number'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['identity_name'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['identity_occupation'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['identity_address'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['npwp_number'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['npwp_name'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['birth_place'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['birth_date'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['bank_name'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['bank_account_number'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['bank_account_holder'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['bank_branch'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['emergency_contact'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['address'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['province_id'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['city_id'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['subdistrict_id'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['village_id'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['postal_code'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['rt'] ?? null)->toBe(ActivityLog::REDACTED)
        ->and($newValues['rw'] ?? null)->toBe(ActivityLog::REDACTED);
});
