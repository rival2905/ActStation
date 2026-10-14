<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    config(['app.organizer_private_documents_required' => true]);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    Role::query()->firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    Role::query()->firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    Role::query()->firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);

    Permission::query()->firstOrCreate(['name' => 'user.view', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'user.update', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'event.view', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'event.update', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'event.publish', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'event.delete', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'event-category.view', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'event-category.update', 'guard_name' => 'web']);

    Storage::fake('local');
    Storage::fake('public');
});

test('admin sidebar shows pending organizer count and removes badge after review', function (): void {
    User::where('kyc_status', 'pending')->update(['kyc_status' => 'unverified']);
    $viewer = User::factory()->create(['is_active' => true, 'kyc_status' => 'unverified']);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo('user.view');
    $pending = User::factory()->count(2)->create(['kyc_status' => 'pending']);
    User::factory()->create(['kyc_status' => 'verified']);
    User::factory()->create(['kyc_status' => 'rejected']);
    $deleted = User::factory()->create(['kyc_status' => 'pending']);
    $deleted->delete();

    $response = $this->actingAs($viewer)->get(route('admin.organizer-kyc.index'));
    $response->assertOk()->assertSee('id="organizer-kyc-pending-badge"', false);
    $dom = new \DOMDocument;
    @$dom->loadHTML($response->getContent());
    $badge = (new \DOMXPath($dom))->query('//*[@id="organizer-kyc-pending-badge"]')->item(0);
    expect(trim($badge->textContent))->toBe('2');
    $pending->first()->update(['kyc_status' => 'verified']);
    $pending->last()->update(['kyc_status' => 'rejected']);
    $this->get(route('admin.organizer-kyc.index'))->assertOk()
        ->assertDontSee('id="organizer-kyc-pending-badge"', false);
});

test('admin sidebar hides organizer badge and menu without read permission', function (): void {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->assignRole('admin');
    $viewer->roles()->first()->revokePermissionTo('user.view');
    User::factory()->create(['kyc_status' => 'pending']);
    $this->actingAs($viewer);
    $html = view('layouts.partials.sidebar')->render();
    expect($html)->not->toContain('id="organizer-kyc-pending-badge"')
        ->not->toContain(route('admin.organizer-kyc.index'));
});

test('admin kyc show with missing docs displays readiness warning and disables approve action', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('user.view', 'user.update');

    $applicant = User::factory()->create([
        'name' => 'Pemohon Kurang Dokumen',
        'kyc_status' => 'pending',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.organizer-kyc.show', $applicant));

    $response->assertOk();
    $response->assertSee('Persyaratan Verifikasi Belum Terpenuhi');
    $response->assertSee('disabled', false);
    // Approve button is disabled
    $response->assertSee('Setujui Pengajuan');
    // Reject button remains available
    $response->assertSee('Tolak Pengajuan');
    $response->assertSee('#rejectModal', false);
});

test('admin kyc show with valid complete docs enables approve action', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('user.view', 'user.update');

    $applicant = User::factory()->create([
        'name' => 'Pemohon Lengkap',
        'kyc_status' => 'pending',
        'is_active' => true,
    ]);

    $docs = [
        'organizer_identity_image' => 'ktp.jpg',
        'organizer_identity_selfie' => 'selfie.jpg',
        'organizer_responsibility_statement' => 'sptjm.pdf',
        'organizer_bank_proof' => 'bank.pdf',
    ];

    foreach ($docs as $cat => $filename) {
        $path = "organizer_kyc/{$applicant->id}/{$filename}";
        Storage::disk('local')->put($path, 'dummy content');
        $applicant->documents()->create([
            'category' => $cat,
            'name' => $filename,
            'file_path' => $path,
            'status' => 'pending',
            'is_public' => false,
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.organizer-kyc.show', $applicant));

    $response->assertOk();
    $response->assertDontSee('Persyaratan Verifikasi Belum Terpenuhi');
    $response->assertSee(route('admin.organizer-kyc.approve', $applicant));
    $response->assertSee('Setujui Pengajuan');
});

test('admin kyc show view only without user.update permission hides all moderation actions', function (): void {
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo('user.view'); // Has view but NOT user.update

    $applicant = User::factory()->create([
        'kyc_status' => 'pending',
        'is_active' => true,
    ]);

    $response = $this->actingAs($viewer)->get(route('admin.organizer-kyc.show', $applicant));

    $response->assertOk();
    $response->assertDontSee('Setujui Pengajuan');
    $response->assertDontSee('Tolak Pengajuan');
    $response->assertDontSee('#rejectModal', false);
});

test('admin kyc show self blocked when viewing own application', function (): void {
    $admin = User::factory()->create([
        'is_active' => true,
        'kyc_status' => 'pending',
    ]);
    $admin->assignRole('admin');
    $admin->givePermissionTo('user.view', 'user.update');

    $response = $this->actingAs($admin)->get(route('admin.organizer-kyc.show', $admin));

    $response->assertOk();
    $response->assertDontSee('Setujui Pengajuan');
    $response->assertDontSee('Tolak Pengajuan');
});

test('organizer apply view when pending has fieldset disabled and submit button disabled', function (): void {
    $user = User::factory()->create([
        'is_active' => true,
        'kyc_status' => 'pending',
    ]);
    $user->assignRole('member');

    $response = $this->actingAs($user)->get(route('panel.organizer.apply'));

    $response->assertOk();
    $response->assertSee('<fieldset disabled>', false);
    $response->assertSee('id="submit_kyc_btn" disabled', false);
});

test('event category manager displays static badge instead of toggle form when user cannot update', function (): void {
    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $owner = User::factory()->create(['is_active' => true]);
    $owner->assignRole('organizer');

    $event = Event::create([
        'user_id' => $owner->id,
        'sport_category_id' => $sport->id,
        'title' => 'Piala Merdeka',
        'slug' => 'piala-merdeka',
        'status' => 'published',
        'registration_open' => true,
        'payout_status' => 'none',
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'KU-18 Putra',
        'match_type' => '5x5',
        'gender' => 'male',
        'registration_flow' => 'instant_free',
        'is_roster_public' => false,
    ]);

    // Viewer in admin workspace without event-category.update
    $viewer = User::factory()->create(['is_active' => true]);
    $viewer->assignRole('admin');
    $viewer->givePermissionTo('event.view', 'event-category.view');

    $response = $this->actingAs($viewer)->get(route('admin.events.categories.index', $event));

    $response->assertOk();
    // Static badge is shown
    $response->assertSee('Dirahasiakan');
    // But toggle form is NOT shown for viewer without canUpdate
    $response->assertDontSee(route('admin.events.categories.toggleRosterVisibility', [$event, $category]), false);
});
