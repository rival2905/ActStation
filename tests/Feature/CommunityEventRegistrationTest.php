<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\EventInvitationService;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\Payment\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->devLevel = DevelopmentLevel::query()->firstOrCreate(
        ['name' => 'Remaja (13-16)', 'sport_category_id' => $this->sport->id],
        [
            'min_age' => 13,
            'max_age' => 16,
            'sort_order' => 2,
        ]
    );

    // User Manager Komunitas
    $this->manager = User::factory()->create([
        'email' => 'manager@test.com',
        'is_active' => true,
    ]);
    $this->manager->assignRole('community_manager');

    // Komunitas terverifikasi
    $this->community = Community::create([
        'name' => 'Klub Basket Satria',
        'slug' => 'klub-basket-satria',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
        'city_id' => '3273',
    ]);

    CommunityOfficial::create([
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'position' => 'Manajer Tim',
        'status' => 'active',
        'started_at' => now(),
    ]);

    // 5 Atlet Komunitas
    $this->athletes = [];
    for ($i = 1; $i <= 5; $i++) {
        $u = User::factory()->create([
            'name' => "Pemain {$i}",
            'gender' => 'male',
            'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
            'identity_number' => "327300000000000{$i}",
        ]);
        $u->assignRole('athlete');

        CommunityAthlete::create([
            'community_id' => $this->community->id,
            'user_id' => $u->id,
            'jersey_number' => $i * 10,
            'position' => 'Guard',
            'status' => 'active',
            'region_status' => 'verified',
            'joined_at' => now(),
        ]);

        $this->athletes[] = $u;
    }

    // Published Event
    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'registration_deadline' => Carbon::now()->addDays(6),
        'allow_manual_payment' => true,
        'manual_bank_name' => 'BCA',
        'manual_bank_account_number' => '1234567890',
        'manual_bank_account_holder' => 'Panitia Turnamen',
        'starts_at' => Carbon::now()->addDays(10),
        'ends_at' => Carbon::now()->addDays(15),
    ]);

    $this->category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Putra',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'payment_first',
        'registration_fee' => '500000.00',
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(5),
        'payment_start' => now()->subHour(),
        'payment_end' => now()->addDays(5),
        'allow_community' => true,
        'community_subtype' => 'free_association',
    ]);
});

test('event overview lists resumable drafts without treating them as submitted participation', function (): void {
    $this->community->update(['sport_category_id' => null]);
    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id, 'community_id' => $this->community->id,
        'event_id' => $this->event->id, 'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'Tim Dalam Proses'], 'revision' => 1, 'expires_at' => now()->addDays(7),
    ]);
    $url = route('panel.community-events.create', ['slug' => $this->event->slug, 'draft_id' => $draft->id, 'step' => 'roster']);
    $response = $this->actingAs($this->manager)->get(route('panel.community-events.index'))->assertOk()
        ->assertSee('Pendaftaran Sedang Berjalan')->assertSee('Tim Dalam Proses')->assertSee($this->category->name)
        ->assertSee('href="'.e($url).'"', false)->assertDontSee('Partisipasi Turnamen Komunitas Saya');
    $html = $response->getContent();
    $start = strpos($html, 'aria-label="Menu panel"');
    $sidebar = substr($html, $start, strpos($html, '</nav>', $start) - $start);
    foreach (['panel.coaches.index', 'panel.athletes.index', 'panel.documents.index'] as $menu) {
        expect($sidebar)->not->toContain('href="'.route($menu).'"');
    }
    $this->get($url)->assertOk()->assertSee($draft->id);
});

test('ongoing draft cards are informative and replace their event in paginated catalog', function (): void {
    $this->event->update(['banner_image' => 'events/demo-logo.png']);
    $other = Event::factory()->create(['status' => 'published', 'registration_open' => true]);
    RegistrationDraft::create([
        'user_id' => $this->manager->id, 'community_id' => $this->community->id,
        'event_id' => $this->event->id, 'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'Tim Informasi', 'members' => [['user_id' => $this->athletes[0]->id]]],
        'expires_at' => now()->addDays(7),
    ]);
    $response = $this->actingAs($this->manager)->get(route('panel.community-events.index'))->assertOk()
        ->assertSee(asset('storage/events/demo-logo.png'), false)
        ->assertSee('1 atlet tersimpan')->assertSee('Batas simpan draft')
        ->assertSee($this->event->starts_at->format('d M Y'));
    $ids = $response->viewData('events')->pluck('id')->all();
    expect($ids)->not->toContain($this->event->id)->toContain($other->id);
    expect($response->viewData('events')->total())->toBe(Event::where('status', 'published')->where('registration_open', true)->where('id', '!=', $this->event->id)->count());
});

test('multi-draft registration wizard displays Tim 1 and Tim 2 badges in accordion headers when multiple drafts exist', function (): void {
    $draft1 = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'Satria A'],
        'created_at' => now()->subHours(2),
        'expires_at' => now()->addDays(7),
    ]);

    $draft2 = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'Satria B'],
        'created_at' => now()->subHour(),
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->manager)
        ->get(route('panel.community-events.create', ['slug' => $this->event->slug, 'draft_id' => $draft2->id]))
        ->assertOk();

    $response->assertSee('team-header-index', false)
        ->assertSee('Tim 1')
        ->assertSee('Tim 2');
});

test('single draft registration wizard does not display Tim 1 badge in accordion headers', function (): void {
    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'Satria Tunggal'],
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($this->manager)
        ->get(route('panel.community-events.create', ['slug' => $this->event->slug, 'draft_id' => $draft->id]))
        ->assertOk()
        ->assertDontSee('team-header-index')
        ->assertDontSee('Tim 1');
});

test('event overview keeps multiple drafts including one without category', function (): void {
    $drafts = collect(['Tim Pertama', 'Tim Kedua'])->map(fn (string $name) => RegistrationDraft::create([
        'user_id' => $this->manager->id, 'community_id' => $this->community->id,
        'event_id' => $this->event->id, 'payload' => ['team_name' => $name], 'expires_at' => null,
    ]));
    $response = $this->actingAs($this->manager)->get(route('panel.community-events.index'))->assertOk()
        ->assertSee('Tim Pertama')->assertSee('Tim Kedua')->assertSee('Belum dipilih');
    foreach ($drafts as $draft) {
        $response->assertSee('draft_id='.$draft->id, false);
    }
});

test('event overview excludes unavailable and unauthorized drafts', function (string $scenario): void {
    $data = [
        'user_id' => $this->manager->id, 'community_id' => $this->community->id,
        'event_id' => $this->event->id, 'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'Draft Harus Tersembunyi'], 'expires_at' => now()->addDays(7),
    ];
    if ($scenario === 'other-user') {
        $data['user_id'] = User::factory()->create()->id;
    } elseif ($scenario === 'other-community') {
        $data['community_id'] = Community::factory()->create()->id;
    } elseif ($scenario === 'expired') {
        $data['expires_at'] = now()->subSecond();
    } elseif ($scenario === 'submitted') {
        $data['payload']['_submitted'] = true;
    } elseif ($scenario === 'tombstone') {
        $data['payload']['_tombstone'] = true;
    } elseif ($scenario === 'unpublished') {
        $this->event->update(['status' => 'draft']);
    } elseif ($scenario === 'participant') {
        $data['participant_id'] = EventParticipant::create([
            'event_id' => $this->event->id, 'event_category_id' => $this->category->id,
            'community_id' => $this->community->id, 'user_id' => $this->manager->id,
            'team_name' => 'Tim Terkirim', 'status' => 'registered',
        ])->id;
    }
    RegistrationDraft::create($data);
    $response = $this->actingAs($this->manager)->get(route('panel.community-events.index'))->assertOk()
        ->assertDontSee('Draft Harus Tersembunyi')->assertDontSee('id="ongoing-registrations"', false);
    if ($scenario !== 'unpublished') {
        expect($response->viewData('events')->pluck('id')->all())->toContain($this->event->id);
    }
})->with(['other-user', 'other-community', 'expired', 'submitted', 'tombstone', 'unpublished', 'participant']);

test('sports sidebar follows active community sport or tournament history', function (string $scenario): void {
    $this->community->update(['sport_category_id' => $scenario === 'sport' ? $this->sport->id : null]);
    if (in_array($scenario, ['history', 'cancelled', 'other-community'])) {
        $other = $scenario === 'other-community' ? Community::factory()->create(['sport_category_id' => null]) : $this->community;
        EventParticipant::create([
            'event_id' => $this->event->id, 'event_category_id' => $this->category->id,
            'community_id' => $other->id, 'user_id' => $this->manager->id,
            'team_name' => 'Tim Riwayat', 'contact_person' => 'DEMO', 'contact_phone' => '081234567890',
            'status' => $scenario === 'cancelled' ? 'cancelled' : 'registered',
        ]);
    }
    if ($scenario === 'draft-only') {
        RegistrationDraft::create([
            'community_id' => $this->community->id, 'user_id' => $this->manager->id,
            'event_id' => $this->event->id, 'event_category_id' => $this->category->id,
            'payload' => ['team_name' => 'Tim Draft'], 'revision' => 1,
        ]);
    }
    $visible = in_array($scenario, ['sport', 'history', 'cancelled']);
    foreach (['panel.community-events.index', 'panel.profile.show'] as $route) {
        $response = $this->actingAs($this->manager)->withSession(['current_community_id' => $this->community->id])->get(route($route))->assertOk();
        $html = $response->getContent();
        $start = strpos($html, 'aria-label="Menu panel"');
        expect($start)->not->toBeFalse();
        $sidebar = substr($html, $start, strpos($html, '</nav>', $start) - $start);
        foreach (['panel.coaches.index', 'panel.athletes.index', 'panel.documents.index'] as $menu) {
            expect(str_contains($sidebar, 'href="'.route($menu).'"'))->toBe($visible);
        }
        expect($sidebar)->toContain('Profil Komunitas')->toContain('Pengurus')->toContain('Turnamen');
        if ($route === 'panel.community-events.index') {
            expect(str_contains($html, 'Partisipasi Turnamen Komunitas Saya'))->toBe(in_array($scenario, ['history', 'cancelled']));
        }
    }
})->with(['empty', 'sport', 'history', 'cancelled', 'other-community', 'draft-only']);

test('manager komunitas dapat mengakses halaman daftar turnamen dan form pendaftaran', function (): void {
    $this->actingAs($this->manager)
        ->get(route('panel.community-events.index'))
        ->assertOk()
        ->assertSee($this->event->title);

    $this->actingAs($this->manager)
        ->get(route('panel.community-events.create', $this->event->slug))
        ->assertOk()
        ->assertSee('KU-16 Putra');
});

test('manager dapat mendaftarkan tim tanpa prasyarat verifikasi wilayah atlet', function (string $regionStatus): void {
    CommunityAthlete::where('community_id', $this->community->id)->update(['region_status' => $regionStatus]);
    $membersPayload = [];
    foreach ($this->athletes as $index => $ath) {
        $membersPayload[] = [
            'user_id' => $ath->id,
            'jersey_number' => $index + 1,
            'is_captain' => $index === 0 ? 1 : 0,
            'position' => 'Guard',
            'team_role' => 'player',
        ];
    }

    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Satria KU-16 A',
            'contact_person' => 'Budi Manajer',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
        ]);

    $participant = EventParticipant::where('team_name', 'Satria KU-16 A')->first();
    expect($participant)->not->toBeNull()
        ->and($participant->members()->count())->toBe(5)
        ->and($participant->status)->toBe('pending_payment');

    $response->assertRedirect(route('panel.community-events.checkout', $participant->id));
    expect(CommunityAthlete::where('community_id', $this->community->id)->pluck('region_status')->unique()->all())->toBe([$regionStatus]);
})->with(['pending', 'rejected', 'verified']);

test('manager dapat mengunggah bukti bayar manual dengan waiver hukum', function (): void {
    Storage::fake('public');

    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Satria KU-16 B',
        'contact_person' => 'Budi',
        'contact_phone' => '081234567890',
        'status' => 'pending_payment',
    ]);

    Transaction::create([
        'user_id' => $this->manager->id,
        'invoice_number' => 'INV-MANUAL-TEST',
        'type' => 'registration',
        'payable_type' => 'event_participant',
        'payable_id' => $participant->id,
        'amount' => $this->category->registration_fee,
        'fee' => 0,
        'total' => $this->category->registration_fee,
        'status' => 'pending',
        'expired_at' => now()->addDay(),
    ]);

    $file = UploadedFile::fake()->image('transfer.jpg');

    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.pay-manual', $participant->id), [
            'payment_proof' => $file,
            'waiver_agreed' => 1,
        ]);

    $updated = $participant->fresh();
    expect($updated->is_escrow_waived)->toBeTrue()
        ->and($updated->payment_channel)->toBe('manual_organizer')
        ->and($updated->waiver_signed_at)->not->toBeNull()
        ->and($updated->payment_proof)->not->toBeNull();

    $response->assertRedirect(route('panel.community-events.roster', $participant->id));
});

test('tim pendaftar melebihi max_teams dengan enable_waiting_list aktif masuk status waiting_list #1', function (): void {
    $this->category->update([
        'max_teams' => 1,
        'enable_waiting_list' => true,
    ]);

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Tim Slot 1',
        'contact_person' => 'Budi',
        'contact_phone' => '081234567890',
        'status' => 'confirmed',
    ]);

    $manager2 = User::factory()->create();
    $manager2->assignRole('community_manager');
    $comm2 = Community::create([
        'name' => 'Klub Rajawali',
        'slug' => 'klub-rajawali',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
    ]);
    CommunityOfficial::create([
        'community_id' => $comm2->id,
        'user_id' => $manager2->id,
        'position' => 'Manajer',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $athleteUsers = [];
    for ($i = 1; $i <= 5; $i++) {
        $u = User::factory()->create([
            'gender' => 'male',
            'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
            'identity_number' => "327388880000000{$i}",
        ]);
        $u->assignRole('athlete');
        CommunityAthlete::create([
            'community_id' => $comm2->id,
            'user_id' => $u->id,
            'jersey_number' => $i + 20,
            'status' => 'active',
            'region_status' => 'verified',
            'joined_at' => now(),
        ]);
        $athleteUsers[] = $u;
    }

    $members = array_map(fn ($u, $idx) => [
        'user_id' => $u->id,
        'jersey_number' => $idx + 5,
        'is_captain' => $idx === 0 ? 1 : 0,
        'position' => 'Guard',
        'team_role' => 'player',
    ], $athleteUsers, array_keys($athleteUsers));

    $response = $this->actingAs($manager2)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Rajawali Waiting List',
            'contact_person' => 'Manajer Rajawali',
            'contact_phone' => '081299990000',
            'members' => $members,
        ]);

    $participant2 = EventParticipant::where('team_name', 'Rajawali Waiting List')->first();
    expect($participant2)->not->toBeNull()
        ->and($participant2->status)->toBe('waiting_list')
        ->and($participant2->waiting_list_order)->toBe(1);

    $response->assertRedirect(route('panel.community-events.roster', $participant2->id));
});

test('screening_first tetap registered saat jumlah tim melebihi max_teams', function (): void {
    $this->category->update([
        'max_teams' => 1,
        'enable_waiting_list' => true,
        'registration_flow' => 'screening_first',
        'payment_time_limit_hours' => 24,
    ]);

    EventParticipant::factory()->create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'community_id' => null,
        'status' => 'confirmed',
    ]);

    $members = collect($this->athletes)->map(fn (User $athlete, int $index): array => [
        'user_id' => $athlete->id,
        'jersey_number' => $index + 1,
        'is_captain' => $index === 0,
        'position' => 'Guard',
        'team_role' => 'player',
    ])->all();

    $this->actingAs($this->manager)->post(route('panel.community-events.store', $this->event->slug), [
        'event_category_id' => $this->category->id,
        'registration_source' => 'community',
        'community_subtype' => 'free_association',
        'team_name' => 'Satria Screening',
        'contact_person' => 'Budi Manajer',
        'contact_phone' => '081234567890',
        'members' => $members,
    ])->assertRedirect();

    $participant = EventParticipant::where('team_name', 'Satria Screening')->firstOrFail();
    expect($participant->status)->toBe('registered')
        ->and($participant->waiting_list_order)->toBeNull();
});

test('undangan berbayar dikonsumsi sekali dan membuat invoice kategori', function (): void {
    $this->category->update(['registration_flow' => 'by_invitation']);
    $token = app(EventInvitationService::class)->issue(
        $this->category,
        (int) $this->community->id,
        (int) $this->manager->id,
        now()->addDay()->toDateTimeString(),
    );
    $members = collect($this->athletes)->map(fn (User $athlete, int $index): array => [
        'user_id' => $athlete->id,
        'jersey_number' => $index + 1,
        'is_captain' => $index === 0 ? 1 : 0,
        'position' => 'Guard',
        'team_role' => 'player',
    ])->all();

    $response = $this->actingAs($this->manager)->post(route('panel.community-events.store', $this->event->slug), [
        'event_category_id' => $this->category->id,
        'registration_source' => 'community',
        'community_subtype' => 'free_association',
        'invitation_token' => $token,
        'team_name' => 'Satria Undangan',
        'contact_person' => 'Budi Manajer',
        'contact_phone' => '081234567890',
        'members' => $members,
    ]);

    $participant = EventParticipant::query()->where('team_name', 'Satria Undangan')->firstOrFail();
    $transaction = Transaction::query()->where('payable_type', 'event_participant')->where('payable_id', $participant->id)->firstOrFail();
    expect($participant->status)->toBe('pending_payment')
        ->and($transaction->amount)->toBe('500000.00')
        ->and($transaction->expired_at?->equalTo($this->category->payment_end))->toBeTrue()
        ->and(DB::table('event_category_invitations')->where('event_participant_id', $participant->id)->whereNotNull('used_at')->exists())->toBeTrue();
    $response->assertRedirect(route('panel.community-events.checkout', $participant->id));

    $this->actingAs($this->manager)->post(route('panel.community-events.store', $this->event->slug), [
        'event_category_id' => $this->category->id,
        'invitation_token' => $token,
        'team_name' => 'Satria Undangan Kedua',
        'contact_person' => 'Budi Manajer',
        'contact_phone' => '081234567890',
        'members' => $members,
    ])->assertSessionHas('error', 'Undangan event tidak valid, sudah dipakai, atau kedaluwarsa.');
});

test('undangan gratis tetap menunggu verifikasi tanpa invoice', function (): void {
    $this->category->update([
        'registration_flow' => 'by_invitation',
        'registration_fee' => 0,
        'registration_start' => null,
        'registration_end' => null,
        'payment_start' => null,
        'payment_end' => null,
    ]);
    $token = app(EventInvitationService::class)->issue(
        $this->category,
        (int) $this->community->id,
        (int) $this->manager->id,
        now()->addDay()->toDateTimeString(),
    );
    $members = collect($this->athletes)->map(fn (User $athlete, int $index): array => [
        'user_id' => $athlete->id,
        'jersey_number' => $index + 1,
        'is_captain' => $index === 0 ? 1 : 0,
        'position' => 'Guard',
        'team_role' => 'player',
    ])->all();

    $this->actingAs($this->manager)->post(route('panel.community-events.store', $this->event->slug), [
        'event_category_id' => $this->category->id,
        'invitation_token' => $token,
        'team_name' => 'Satria Undangan Gratis',
        'contact_person' => 'Budi Manajer',
        'contact_phone' => '081234567890',
        'members' => $members,
    ]);

    $participant = EventParticipant::query()->where('team_name', 'Satria Undangan Gratis')->firstOrFail();
    expect($participant->status)->toBe('registered')
        ->and(Transaction::query()->where('payable_type', 'event_participant')->where('payable_id', $participant->id)->exists())->toBeFalse();
});

test('user tanpa komunitas melengkapi organisasi sebelum mendaftarkan tim', function (): void {
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    $organizer = User::factory()->create([
        'is_active' => true,
    ]);
    $organizer->assignRole('organizer');

    $response = $this->actingAs($organizer)->get(route('panel.community-events.create', $this->event->slug));

    $response->assertRedirect(route('panel.community.create'));
});
