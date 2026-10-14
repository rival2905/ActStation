<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\MasterData\Models\DocumentType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::where('email', 'superadmin@actstation.test')->firstOrFail();
    $this->organizer = User::factory()->create();
    $this->organizer->assignRole('organizer');
    $this->otherOrganizer = User::factory()->create();
    $this->otherOrganizer->assignRole('organizer');
    $this->event = Event::factory()->create([
        'user_id' => $this->organizer->id,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(12),
        'registration_deadline' => now()->addDays(8),
    ]);
    DocumentType::firstOrCreate(['code' => 'bpjs_tk'], ['name' => 'BPJS TK', 'scope' => 'athlete']);
});

test('admin dan organizer mengelola kategori identik pada event yang sama', function (): void {
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Instansi KU 35+',
            'match_type' => '5x5',
            'gender' => 'male',
            'min_age' => 35,
            'max_age' => 50,
            'registration_fee' => 500000,
            'min_roster' => 5,
            'max_roster' => 12,
            'max_teams' => 8,
            'registration_flow' => 'screening_first',
            'enable_waiting_list' => 1,
            'allow_community' => 1,
            'allow_club' => 0,
            'community_subtype' => 'institution',
            'required_documents' => ['bpjs_tk'],
            'payment_time_limit_hours' => 24,
        ])->assertSessionHasNoErrors();

    $category = $this->event->categories()->firstOrFail();
    expect((float) $category->registration_fee)->toBe(500000.0)
        ->and($category->min_age)->toBe(35)
        ->and($category->max_age)->toBe(50)
        ->and($category->payment_time_limit_hours)->toBe(24);

    $this->actingAs($this->admin)
        ->get(route('admin.events.categories.index', $this->event))
        ->assertOk()->assertSee('Instansi KU 35+')->assertSee('bpjs_tk')
        ->assertSee('Screening First')->assertSee('Instansi / Lembaga');

    $regStart = $this->event->starts_at->copy()->subDays(6)->format('Y-m-d H:i:s');
    $payStart = $this->event->starts_at->copy()->subDays(5)->format('Y-m-d H:i:s');
    $regEnd = $this->event->starts_at->copy()->subDays(3)->format('Y-m-d H:i:s');
    $payEnd = $regEnd;

    $this->actingAs($this->admin)
        ->put(route('admin.events.categories.update', [$this->event, $category]), [
            'name' => 'Instansi KU 35+',
            'match_type' => '3x3',
            'gender' => 'male',
            'min_age' => 35,
            'max_age' => 50,
            'registration_fee' => 750000,
            'min_roster' => 3,
            'max_roster' => 4,
            'max_teams' => 8,
            'registration_flow' => 'payment_first',
            'registration_start' => $regStart,
            'payment_start' => $payStart,
            'registration_end' => $regEnd,
            'payment_end' => $payEnd,
            'enable_waiting_list' => 0,
            'allow_community' => 1,
            'allow_club' => 0,
            'community_subtype' => 'institution',
            'required_documents' => ['bpjs_tk'],
        ])->assertSessionHasNoErrors();

    expect($category->fresh()->registration_flow)->toBe('payment_first')
        ->and((float) $category->fresh()->registration_fee)->toBe(750000.0)
        ->and($category->fresh()->payment_time_limit_hours)->toBeNull();

    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.categories.index', $this->event))
        ->assertOk()->assertSee('Payment First')->assertSee('bpjs_tk');
});

test('validasi kategori menolak subtipe tidak dikenal dan mewajibkan subtipe jika komunitas aktif', function (): void {
    // Penolakan jenjang tidak dikenal oleh organizer
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Kategori Sekolah SD',
            'match_type' => '5x5',
            'gender' => 'mixed',
            'registration_flow' => 'instant_free',
            'registration_fee' => 0,
            'allow_community' => 1,
            'allow_club' => 0,
            'community_subtype' => 'school_unknown',
        ])->assertSessionHasErrors(['community_subtype']);

    // Penolakan tipe tidak dikenal oleh admin
    $this->actingAs($this->admin)
        ->post(route('admin.events.categories.store', $this->event), [
            'name' => 'Kategori Sekolah SMP',
            'match_type' => '5x5',
            'gender' => 'mixed',
            'registration_flow' => 'instant_free',
            'registration_fee' => 0,
            'allow_community' => 1,
            'allow_club' => 0,
            'community_subtype' => 'unknown_entity',
        ])->assertSessionHasErrors(['community_subtype']);

    // Penolakan ketika allow_community aktif tapi subtipe kosong
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Komunitas Tanpa Subtipe',
            'match_type' => '5x5',
            'gender' => 'mixed',
            'registration_flow' => 'instant_free',
            'registration_fee' => 0,
            'allow_community' => 1,
            'allow_club' => 0,
            'community_subtype' => '',
        ])->assertSessionHasErrors(['community_subtype']);

    // Penolakan ketika allow_club dan allow_community keduanya nonaktif
    $this->actingAs($this->admin)
        ->post(route('admin.events.categories.store', $this->event), [
            'name' => 'Tanpa Sumber Tim',
            'match_type' => '5x5',
            'gender' => 'mixed',
            'registration_flow' => 'instant_free',
            'registration_fee' => 0,
            'allow_club' => 0,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['allow_club']);

    // Pilihan subtipe yang sah (school, university, institution, club, free_association) diterima
    foreach (['school', 'university', 'institution', 'club', 'free_association'] as $subtype) {
        $this->actingAs($this->organizer)
            ->post(route('panel.organizer.categories.store', $this->event), [
                'name' => 'Kategori '.$subtype,
                'match_type' => '5x5',
                'gender' => 'mixed',
                'registration_flow' => 'instant_free',
                'registration_fee' => 0,
                'allow_community' => 1,
                'allow_club' => 0,
                'community_subtype' => $subtype,
            ])->assertSessionHasNoErrors();
    }
});

test('validasi alur dan biaya pendaftaran konsisten antara admin dan organizer', function (): void {
    // payment_first dengan harga 0 ditolak
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Payment First Gratis',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'payment_first',
            'registration_fee' => 0,
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['registration_fee']);

    // instant_free dengan harga > 0 ditolak
    $this->actingAs($this->admin)
        ->post(route('admin.events.categories.store', $this->event), [
            'name' => 'Instant Free Berbayar',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'instant_free',
            'registration_fee' => 100000,
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['registration_fee']);

    // min_age > max_age ditolak
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Usia Terbalik',
            'match_type' => '5x5',
            'gender' => 'male',
            'min_age' => 40,
            'max_age' => 20,
            'registration_flow' => 'instant_free',
            'registration_fee' => 0,
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['max_age']);

    // screening_first berbayar tanpa payment_time_limit_hours ditolak
    $this->actingAs($this->admin)
        ->post(route('admin.events.categories.store', $this->event), [
            'name' => 'Screening Paid Tanpa Tenggat',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'screening_first',
            'registration_fee' => 200000,
            'payment_time_limit_hours' => null,
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['payment_time_limit_hours']);
});

test('validasi empat tanggal payment_first dan pembatasan tenggat event', function (): void {
    $validRegStart = $this->event->starts_at->copy()->subDays(6)->format('Y-m-d H:i:s');
    $validPayStart = $this->event->starts_at->copy()->subDays(5)->format('Y-m-d H:i:s');
    $validRegEnd = $this->event->starts_at->copy()->subDays(3)->format('Y-m-d H:i:s');
    $validPayEnd = $validRegEnd;

    // payment_end != registration_end ditolak
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Tutup Bayar Beda',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'payment_first',
            'registration_fee' => 300000,
            'registration_start' => $validRegStart,
            'payment_start' => $validPayStart,
            'registration_end' => $validRegEnd,
            'payment_end' => $this->event->starts_at->copy()->subDays(2)->format('Y-m-d H:i:s'),
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['payment_end']);

    // payment_start < registration_start ditolak
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Bayar Sebelum Daftar',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'payment_first',
            'registration_fee' => 300000,
            'registration_start' => $validRegStart,
            'payment_start' => $this->event->starts_at->copy()->subDays(7)->format('Y-m-d H:i:s'),
            'registration_end' => $validRegEnd,
            'payment_end' => $validPayEnd,
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['payment_start']);

    // payment_start >= registration_end ditolak
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Bayar Saat Tutup Daftar',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'payment_first',
            'registration_fee' => 300000,
            'registration_start' => $validRegStart,
            'payment_start' => $validRegEnd,
            'registration_end' => $validRegEnd,
            'payment_end' => $validPayEnd,
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['payment_start']);

    // registration_end > event starts_at ditolak
    $this->actingAs($this->admin)
        ->post(route('admin.events.categories.store', $this->event), [
            'name' => 'Tutup Lewat Event',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'payment_first',
            'registration_fee' => 300000,
            'registration_start' => $validRegStart,
            'payment_start' => $validPayStart,
            'registration_end' => $this->event->starts_at->copy()->addDay()->format('Y-m-d H:i:s'),
            'payment_end' => $this->event->starts_at->copy()->addDay()->format('Y-m-d H:i:s'),
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['registration_end']);

    // registration_end > event registration_deadline ditolak
    $this->actingAs($this->admin)
        ->post(route('admin.events.categories.store', $this->event), [
            'name' => 'Tutup Lewat Deadline Event',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'payment_first',
            'registration_fee' => 300000,
            'registration_start' => $validRegStart,
            'payment_start' => $validPayStart,
            'registration_end' => $this->event->registration_deadline->copy()->addDay()->format('Y-m-d H:i:s'),
            'payment_end' => $this->event->registration_deadline->copy()->addDay()->format('Y-m-d H:i:s'),
            'allow_club' => 1,
            'allow_community' => 0,
        ])->assertSessionHasErrors(['registration_end']);
});

test('perubahan konfigurasi berisiko ditolak setelah kategori memiliki peserta terdaftar', function (): void {
    $this->actingAs($this->organizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Kategori Terisi',
            'match_type' => '5x5',
            'gender' => 'male',
            'min_age' => 20,
            'max_age' => 30,
            'registration_fee' => 500000,
            'min_roster' => 5,
            'max_roster' => 12,
            'max_teams' => 8,
            'registration_flow' => 'screening_first',
            'payment_time_limit_hours' => 24,
            'allow_club' => 1,
            'allow_community' => 0,
            'required_documents' => ['bpjs_tk'],
        ])->assertSessionHasNoErrors();

    $category = $this->event->categories()->where('name', 'Kategori Terisi')->firstOrFail();

    // Buat peserta terdaftar di kategori ini
    EventParticipant::factory()->create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
    ]);

    $validRegStart = $this->event->starts_at->copy()->subDays(6)->format('Y-m-d H:i:s');
    $validPayStart = $this->event->starts_at->copy()->subDays(5)->format('Y-m-d H:i:s');
    $validRegEnd = $this->event->starts_at->copy()->subDays(3)->format('Y-m-d H:i:s');
    $validPayEnd = $validRegEnd;

    // Mengubah registration_flow ditolak
    $this->actingAs($this->admin)
        ->put(route('admin.events.categories.update', [$this->event, $category]), [
            'name' => 'Kategori Terisi',
            'match_type' => '5x5',
            'gender' => 'male',
            'min_age' => 20,
            'max_age' => 30,
            'registration_fee' => 500000,
            'registration_flow' => 'payment_first',
            'registration_start' => $validRegStart,
            'payment_start' => $validPayStart,
            'registration_end' => $validRegEnd,
            'payment_end' => $validPayEnd,
            'allow_club' => 1,
            'allow_community' => 0,
            'required_documents' => ['bpjs_tk'],
        ])->assertSessionHasErrors(['registration_flow']);

    // Mengubah registration_fee diizinkan (invoice lama menggunakan snapshot nominal per PRD 1161)
    $this->actingAs($this->organizer)
        ->put(route('panel.organizer.categories.update', [$this->event, $category]), [
            'name' => 'Kategori Terisi',
            'match_type' => '5x5',
            'gender' => 'male',
            'min_age' => 20,
            'max_age' => 30,
            'min_roster' => 5,
            'max_roster' => 12,
            'registration_fee' => 600000,
            'registration_flow' => 'screening_first',
            'allow_club' => 1,
            'allow_community' => 0,
            'payment_time_limit_hours' => 24,
            'required_documents' => ['bpjs_tk'],
        ])->assertSessionHasNoErrors();

    expect((float) $category->fresh()->registration_fee)->toBe(600000.0);
    // Mengubah batas usia ditolak
    $this->actingAs($this->organizer)
        ->put(route('panel.organizer.categories.update', [$this->event, $category]), [
            'name' => 'Kategori Terisi',
            'match_type' => '5x5',
            'gender' => 'male',
            'min_age' => 25,
            'max_age' => 30,
            'registration_fee' => 500000,
            'registration_flow' => 'screening_first',
            'allow_club' => 1,
            'allow_community' => 0,
            'payment_time_limit_hours' => 24,
            'required_documents' => ['bpjs_tk'],
        ])->assertSessionHasErrors(['min_age']);

    // Mengubah nama atau kuota tim tanpa mengubah aturan inti diizinkan
    $this->actingAs($this->admin)
        ->put(route('admin.events.categories.update', [$this->event, $category]), [
            'name' => 'Kategori Terisi Diperbarui',
            'match_type' => '5x5',
            'gender' => 'male',
            'min_age' => 20,
            'max_age' => 30,
            'min_roster' => 5,
            'max_roster' => 12,
            'max_teams' => 16,
            'registration_fee' => 500000,
            'registration_flow' => 'screening_first',
            'payment_time_limit_hours' => 24,
            'allow_club' => 1,
            'allow_community' => 0,
            'required_documents' => ['bpjs_tk'],
        ])->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Kategori Terisi Diperbarui')
        ->and($category->fresh()->max_teams)->toBe(16);

    // Kategori dengan peserta tidak dapat dihapus
    $this->actingAs($this->organizer)
        ->delete(route('panel.organizer.categories.destroy', [$this->event, $category]))
        ->assertSessionHas('error');

    expect(EventCategory::where('id', $category->id)->exists())->toBeTrue();
});

test('workspace dan scheduler berbagi mesin tetapi organizer lain ditolak', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.events.workspace.manage', $this->event))
        ->assertOk()->assertSee('Kategori');

    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.events.manage', $this->event))
        ->assertOk()->assertSee('Kategori');

    $this->actingAs($this->admin)
        ->getJson(route('admin.events.scheduler.api.config', $this->event))
        ->assertOk()->assertJsonPath('data.id', $this->event->id);

    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.tournament.index', $this->event))
        ->assertOk()->assertViewIs('admin.events.scheduler')
        ->assertViewHas('apiBase', url("panel/organizer/events/{$this->event->id}/scheduler/api"));

    $this->actingAs($this->organizer)
        ->getJson(route('panel.organizer.scheduler.api.config', $this->event))
        ->assertOk()->assertJsonPath('data.id', $this->event->id);

    $this->actingAs($this->otherOrganizer)
        ->getJson(route('panel.organizer.scheduler.api.config', $this->event))
        ->assertForbidden();
    $this->actingAs($this->otherOrganizer)
        ->get(route('panel.organizer.tournament.index', $this->event))
        ->assertForbidden();

    $this->actingAs($this->otherOrganizer)
        ->postJson(route('panel.organizer.scheduler.api.phases.store', $this->event), [
            'event_category_id' => EventCategory::factory()->create(['event_id' => $this->event->id])->id,
            'name' => 'Semifinal', 'phase_type' => 'knockout',
        ])->assertForbidden();

    $this->actingAs($this->otherOrganizer)
        ->post(route('panel.organizer.categories.store', $this->event), [
            'name' => 'Ilegal', 'gender' => 'male', 'match_type' => '5x5',
            'registration_flow' => 'screening_first',
        ])->assertForbidden();
});

test('panel organizer kelola event saya hanya memfilter event milik user sendiri', function (): void {
    // Admin tidak memiliki event buatan sendiri
    $this->actingAs($this->admin)
        ->get(route('panel.organizer.events.index'))
        ->assertOk()
        ->assertDontSee($this->event->title);

    // Organizer pembuat event dapat melihat event miliknya
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.events.index'))
        ->assertOk()
        ->assertSee($this->event->title);
});
