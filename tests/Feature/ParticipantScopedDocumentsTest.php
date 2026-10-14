<?php

declare(strict_types=1);

use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');

    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web', 'panel_access' => 'internal']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->devLevel = DevelopmentLevel::query()->firstOrCreate(
        ['name' => 'Remaja (13-16)', 'sport_category_id' => $this->sport->id],
        ['min_age' => 13, 'max_age' => 16, 'sort_order' => 2]
    );

    $this->organizer = User::factory()->create([
        'email' => 'organizer_docs@test.com',
        'is_active' => true,
    ]);
    $this->organizer->assignRole('organizer');

    $this->event = Event::factory()->create([
        'user_id' => $this->organizer->id,
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addDays(10),
        'ends_at' => Carbon::now()->addDays(15),
    ]);

    $this->manager = User::factory()->create([
        'email' => 'manager_docs@test.com',
        'is_active' => true,
    ]);
    $this->manager->assignRole('community_manager');

    $this->community = Community::create([
        'name' => 'Klub Dokumen Unggul',
        'slug' => 'klub-dokumen-unggul',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
        'subtype' => 'free_association',
        'city_id' => '3273',
    ]);

    CommunityOfficial::create([
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'position' => 'Manajer Tim',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $this->athletes = [];
    for ($i = 1; $i <= 5; $i++) {
        $u = User::factory()->create([
            'name' => "Atlet Doc {$i}",
            'gender' => 'male',
            'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
            'identity_number' => "327300000000999{$i}",
        ]);
        $u->assignRole('athlete');

        CommunityAthlete::create([
            'community_id' => $this->community->id,
            'user_id' => $u->id,
            'jersey_number' => $i * 5,
            'position' => 'Guard',
            'status' => 'active',
            'region_status' => 'verified',
            'joined_at' => now(),
        ]);

        $this->athletes[] = $u;
    }

    // Document Types
    $this->docTeam1 = DocumentType::firstOrCreate(
        ['code' => 'team_mandate'],
        ['name' => 'Surat Mandat Tim', 'scope' => 'team', 'is_active' => true]
    );
    $this->docTeam2 = DocumentType::firstOrCreate(
        ['code' => 'team_sptjm'],
        ['name' => 'SPTJM Integritas Tim', 'scope' => 'team', 'is_active' => true]
    );
    $this->docAthleteKtp = DocumentType::firstOrCreate(
        ['code' => 'ktp_athlete'],
        ['name' => 'KTP Atlet', 'scope' => 'athlete', 'is_active' => true]
    );
});

test('participant form describes stored athlete files as reusable evidence not lifetime verification', function (): void {
    EventCategory::factory()->create(['event_id' => $this->event->id, 'allow_community' => true, 'allowed_community_subtypes' => ['free_association'], 'registration_flow' => 'instant_free', 'registration_fee' => 0]);
    $this->actingAs($this->manager)->get(route('panel.community-events.create', $this->event->slug))
        ->assertOk()->assertSee('tetap mengikuti verifikasi dan persyaratan kategori')
        ->assertDontSee('otomatis terverifikasi')->assertDontSee('seumur hidup');
});

test('multiple team documents required rejects single team_document spoof and requires separate uploads', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Dua Dokumen Tim',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate', 'team_sptjm'],
    ]);

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

    // 1. Trying to pass single team_document when multiple team docs are required should fail
    $file = UploadedFile::fake()->create('mandat.pdf', 100);
    $response = $this->actingAs($this->manager)
        ->from(route('panel.community-events.create', $this->event->slug))
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Mandat Saja',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_document' => $file,
        ]);

    $response->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Mandat Saja')->exists())->toBeFalse();

    // 2. Providing both via team_documents[code] must succeed and store each separately with exact category code
    $fileMandate = UploadedFile::fake()->create('mandate.pdf', 100);
    $fileSptjm = UploadedFile::fake()->create('sptjm.pdf', 100);

    $successResponse = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Lengkap Dokumen',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_documents' => [
                'team_mandate' => $fileMandate,
                'team_sptjm' => $fileSptjm,
            ],
        ]);

    $participant = EventParticipant::where('team_name', 'Tim Lengkap Dokumen')->first();
    expect($participant)->not->toBeNull();

    $teamDocs = Document::query()
        ->where('documentable_type', EventParticipant::class)
        ->where('documentable_id', $participant->id)
        ->get();

    expect($teamDocs->count())->toBe(2)
        ->and($teamDocs->pluck('category')->all())->toEqualCanonicalizing(['team_mandate', 'team_sptjm']);
});

test('backcompat singular team_document works when exactly one team doc required', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Satu Dokumen Tim',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate'],
    ]);

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

    $file = UploadedFile::fake()->create('mandat_single.pdf', 100);
    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Tunggal Mandat',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_document' => $file,
        ]);

    $participant = EventParticipant::where('team_name', 'Tim Tunggal Mandat')->first();
    expect($participant)->not->toBeNull();

    $doc = Document::query()
        ->where('documentable_type', EventParticipant::class)
        ->where('documentable_id', $participant->id)
        ->first();

    expect($doc)->not->toBeNull()
        ->and($doc->category)->toBe('team_mandate');
});

test('athlete vault document with rejected status is not counted as valid', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Dokumen KTP',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['ktp_athlete'],
    ]);

    // Give 4 athletes valid vault docs, 1 athlete has rejected doc
    Storage::disk('public')->put('vault/ktp.jpg', 'ktp valid fake file');
    for ($i = 0; $i < 4; $i++) {
        Document::create([
            'documentable_type' => User::class,
            'documentable_id' => $this->athletes[$i]->id,
            'category' => 'ktp_athlete',
            'name' => 'KTP Atlet',
            'file_path' => 'vault/ktp.jpg',
            'status' => 'pending',
        ]);
    }
    // 5th athlete has rejected status
    Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $this->athletes[4]->id,
        'category' => 'ktp_athlete',
        'name' => 'KTP Atlet',
        'file_path' => 'vault/ktp_bad.jpg',
        'status' => 'rejected',
    ]);

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
        ->from(route('panel.community-events.create', $this->event->slug))
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Atlet Rejected',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
        ]);

    $response->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Atlet Rejected')->exists())->toBeFalse();
});

test('required_entity_documents checks verified legal documents and rejects missing', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Butuh SK Kemenkumham',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_entity_documents' => ['sk_kemenkumham'],
    ]);

    // Mock CommunityLookup with legalDocumentsForUser method
    $realLookup = app(CommunityLookup::class);
    $mockLookup = new class($realLookup) implements CommunityLookup
    {
        public function __construct(private readonly CommunityLookup $inner) {}

        public function find(int $id): ?CommunitySummary
        {
            return $this->inner->find($id);
        }

        public function findForUser(int $userId): ?CommunitySummary
        {
            return $this->inner->findForUser($userId);
        }

        public function legalDocumentsForUser(int $userId, int $communityId): array
        {
            // Missing sk_kemenkumham
            return [
                'npwp' => ['id' => 1, 'category' => 'npwp', 'name' => 'NPWP Komunitas'],
            ];
        }

        public function legalDocumentDownloadForUser(int $userId, int $communityId, string $code): StreamedResponse
        {
            return response()->streamDownload(fn () => print (''), 'test.pdf');
        }
    };

    app()->instance(CommunityLookup::class, $mockLookup);

    /** @var RegistrationGuard $guard */
    $guard = app(RegistrationGuard::class);

    expect(fn () => $guard->validate(
        event: $this->event,
        category: $category,
        communityId: $this->community->id,
        teamName: 'Tim Tanpa SK',
        userId: $this->manager->id,
    ))->toThrow(RegistrationGuardException::class, 'Dokumen legalitas entitas [sk_kemenkumham] belum diverifikasi atau tidak tersedia.');

    // Now test with verified sk_kemenkumham
    $validMockLookup = new class($realLookup) implements CommunityLookup
    {
        public function __construct(private readonly CommunityLookup $inner) {}

        public function find(int $id): ?CommunitySummary
        {
            return $this->inner->find($id);
        }

        public function findForUser(int $userId): ?CommunitySummary
        {
            return $this->inner->findForUser($userId);
        }

        public function legalDocumentsForUser(int $userId, int $communityId): array
        {
            return [
                'sk_kemenkumham' => ['id' => 2, 'category' => 'sk_kemenkumham', 'name' => 'SK Kemenkumham'],
            ];
        }

        public function legalDocumentDownloadForUser(int $userId, int $communityId, string $code): StreamedResponse
        {
            return response()->streamDownload(fn () => print (''), 'test.pdf');
        }
    };

    app()->instance(CommunityLookup::class, $validMockLookup);

    app(RegistrationGuard::class)->validate(
        event: $this->event,
        category: $category,
        communityId: $this->community->id,
        teamName: 'Tim Ber-SK',
        userId: $this->manager->id,
    );

    expect(true)->toBeTrue();
});

test('one team requirement validates once for whole team not each athlete', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Satu Mandat Tim',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate'],
    ]);

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

    $file = UploadedFile::fake()->create('mandat_tim.pdf', 100);
    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Mandat Tunggal Sukses',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_document' => $file,
        ]);

    $participant = EventParticipant::where('team_name', 'Tim Mandat Tunggal Sukses')->first();
    expect($participant)->not->toBeNull();

    $teamDocs = Document::where('documentable_type', EventParticipant::class)->where('documentable_id', $participant->id)->get();
    expect($teamDocs->count())->toBe(1)
        ->and($teamDocs->first()->category)->toBe('team_mandate');

    // No user documents created for athletes
    $athDocs = Document::where('documentable_type', User::class)->whereIn('documentable_id', collect($this->athletes)->pluck('id'))->get();
    expect($athDocs->count())->toBe(0);
});

test('each athlete requires their own document and fails if any athlete missing', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 KTP Per Atlet',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['ktp_athlete'],
    ]);

    Storage::disk('local')->put('vault/ktp_common.pdf', 'ktp-athlete-data');
    for ($i = 0; $i < 4; $i++) {
        Document::create([
            'documentable_type' => User::class,
            'documentable_id' => $this->athletes[$i]->id,
            'category' => 'ktp_athlete',
            'name' => 'KTP Atlet',
            'file_path' => 'vault/ktp_common.pdf',
            'status' => 'pending',
            'is_public' => false,
        ]);
    }

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

    // 1. Without upload for 5th athlete: fails
    $resFail = $this->actingAs($this->manager)
        ->from(route('panel.community-events.create', $this->event->slug))
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Atlet Belum Lengkap',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
        ]);

    $resFail->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Atlet Belum Lengkap')->exists())->toBeFalse();

    // 2. With upload for 5th athlete: succeeds
    $file5 = UploadedFile::fake()->create('ktp5.pdf', 100);
    $resSuccess = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Atlet Lengkap',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'athlete_documents' => [
                $this->athletes[4]->id => [
                    'ktp_athlete' => $file5,
                ],
            ],
        ]);

    expect(EventParticipant::where('team_name', 'Tim Atlet Lengkap')->exists())->toBeTrue();
    expect(Document::where('documentable_type', User::class)->where('documentable_id', $this->athletes[4]->id)->where('category', 'ktp_athlete')->exists())->toBeTrue();
});

test('cannot use shared team document to satisfy individual athlete document requirement', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 KTP Wajib',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['ktp_athlete'],
    ]);

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

    $file = UploadedFile::fake()->create('mandat.pdf', 100);
    $response = $this->actingAs($this->manager)
        ->from(route('panel.community-events.create', $this->event->slug))
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Palsu Shared Doc',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_document' => $file,
        ]);

    $response->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Palsu Shared Doc')->exists())->toBeFalse();
});

test('forged foreign athlete upload cannot mutate target user documents', function (): void {
    $foreignUser = User::factory()->create([
        'name' => 'Foreign Victim',
        'is_active' => true,
    ]);
    $foreignUser->assignRole('athlete');

    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Cek Foreign ID',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['ktp_athlete'],
    ]);

    Storage::disk('local')->put('vault/ktp_common.pdf', 'valid-ktp');
    for ($i = 0; $i < 5; $i++) {
        Document::create([
            'documentable_type' => User::class,
            'documentable_id' => $this->athletes[$i]->id,
            'category' => 'ktp_athlete',
            'name' => 'KTP Atlet',
            'file_path' => 'vault/ktp_common.pdf',
            'status' => 'pending',
            'is_public' => false,
        ]);
    }

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

    $exploitFile = UploadedFile::fake()->create('exploit.pdf', 100);
    $response = $this->actingAs($this->manager)
        ->postJson(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Exploit Foreign',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'athlete_documents' => [
                $foreignUser->id => [
                    'ktp_athlete' => $exploitFile,
                ],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['athlete_documents']);

    expect($foreignUser->documents()->count())->toBe(0);
    expect(EventParticipant::where('team_name', 'Tim Exploit Foreign')->exists())->toBeFalse();
});

test('team uploads only accept required team document codes and reject unknown or athlete codes', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Validasi Kode Tim',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate'],
    ]);

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

    $badFile = UploadedFile::fake()->create('bad.pdf', 100);
    // 1. Unknown code under team_documents
    $resUnknown = $this->actingAs($this->manager)
        ->postJson(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Kode Ngawur',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_documents' => [
                'kode_ilegal_123' => $badFile,
            ],
        ]);

    $resUnknown->assertStatus(422)->assertJsonValidationErrors(['team_documents']);

    // 2. Athlete-scoped code under team_documents
    $resAthleteScope = $this->actingAs($this->manager)
        ->postJson(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Scope Salah',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_documents' => [
                'ktp_athlete' => $badFile,
            ],
        ]);

    $resAthleteScope->assertStatus(422)->assertJsonValidationErrors(['team_documents']);
});

test('athlete vault document missing from physical disk does not count as valid', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Disk Missing Test',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['ktp_athlete'],
    ]);

    // DB record exists but file_path does NOT exist on disk
    for ($i = 0; $i < 5; $i++) {
        Document::create([
            'documentable_type' => User::class,
            'documentable_id' => $this->athletes[$i]->id,
            'category' => 'ktp_athlete',
            'name' => 'KTP Atlet',
            'file_path' => "vault/ghost_{$i}.pdf",
            'status' => 'pending',
            'is_public' => false,
        ]);
    }

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
        ->from(route('panel.community-events.create', $this->event->slug))
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Berkas Hantu',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
        ]);

    $response->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Berkas Hantu')->exists())->toBeFalse();
});

test('organizer verify checks all roster verified and physical file exists on disk', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Moderasi Dokumen',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'screening_first',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate', 'ktp_athlete'],
    ]);

    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'community',
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Tim Moderasi Cek Fisik',
        'contact_person' => 'PIC Test',
        'contact_phone' => '081234567890',
        'status' => 'registered',
        'registered_at' => now(),
    ]);

    Document::where('documentable_type', User::class)->whereIn('documentable_id', collect($this->athletes)->pluck('id'))->delete();

    foreach ($this->athletes as $index => $ath) {
        $participant->members()->create([
            'user_id' => $ath->id,
            'jersey_number' => $index + 1,
            'is_captain' => $index === 0,
            'position' => 'Guard',
            'team_role' => 'player',
        ]);

        Storage::disk('local')->put("vault/ath_{$ath->id}.pdf", 'konten-ktp');
        Document::create([
            'documentable_type' => User::class,
            'documentable_id' => $ath->id,
            'category' => 'ktp_athlete',
            'name' => 'KTP Atlet',
            'file_path' => "vault/ath_{$ath->id}.pdf",
            'status' => 'verified',
            'is_public' => false,
        ]);
    }

    Storage::disk('local')->put("participant/mandate_{$participant->id}.pdf", 'konten-mandat');
    Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'team_mandate',
        'name' => 'Mandat Tim',
        'file_path' => "participant/mandate_{$participant->id}.pdf",
        'status' => 'verified',
        'is_public' => false,
    ]);

    // 1. All verified and physical files exist: verify succeeds
    $resVerify = $this->actingAs($this->organizer)
        ->post(route('panel.organizer.teams.verify', [$this->event->id, $participant->id]));

    $resVerify->assertSessionHas('success');
    expect($participant->fresh()->status)->toBe('confirmed');

    // 2. Physical file missing for one athlete: verify fails
    EventParticipant::whereKey($participant->id)->update(['status' => 'registered']);
    Storage::disk('local')->delete("vault/ath_{$this->athletes[0]->id}.pdf");

    $resFailAth = $this->actingAs($this->organizer)
        ->post(route('panel.organizer.teams.verify', [$this->event->id, $participant->id]));

    $resFailAth->assertSessionHas('error');
    expect($participant->fresh()->status)->toBe('registered');

    // 3. Restore athlete file, remove team physical file: verify fails
    Storage::disk('local')->put("vault/ath_{$this->athletes[0]->id}.pdf", 'konten-ktp');
    Storage::disk('local')->delete("participant/mandate_{$participant->id}.pdf");

    $resFailTeam = $this->actingAs($this->organizer)
        ->post(route('panel.organizer.teams.verify', [$this->event->id, $participant->id]));

    $resFailTeam->assertSessionHas('error');
    expect($participant->fresh()->status)->toBe('registered');
});

test('entity applicability: school_team_letter applies only to school and not institution', function (): void {
    $docSchool = DocumentType::firstOrCreate(
        ['code' => 'school_team_letter'],
        [
            'name' => 'Surat Keterangan Sekolah Tim',
            'scope' => 'team',
            'applicable_entities' => ['school'],
            'is_active' => true,
        ]
    );

    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Antar Sekolah & Instansi',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['institution', 'school'],
        'required_documents' => ['school_team_letter'],
    ]);

    // 1. Community subtype: institution
    $institutionCommunity = Community::create([
        'name' => 'Instansi Korporat',
        'slug' => 'instansi-korporat',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
        'subtype' => 'institution',
        'city_id' => '3273',
    ]);
    $instManager = User::factory()->create(['email' => 'inst_mgr@test.com', 'is_active' => true]);
    $instManager->assignRole('community_manager');
    CommunityOfficial::create([
        'community_id' => $institutionCommunity->id,
        'user_id' => $instManager->id,
        'position' => 'Manajer',
        'status' => 'active',
        'started_at' => now(),
    ]);

    foreach ($this->athletes as $i => $u) {
        CommunityAthlete::create([
            'community_id' => $institutionCommunity->id,
            'user_id' => $u->id,
            'jersey_number' => ($i + 1),
            'position' => 'Guard',
            'status' => 'active',
            'region_status' => 'verified',
            'joined_at' => now(),
        ]);
    }

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

    // Institution registers WITHOUT school_team_letter: succeeds with no error!
    $resInst = $this->actingAs($instManager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'institution',
            'team_name' => 'Tim Instansi Tanpa Surat Sekolah',
            'contact_person' => 'PIC Instansi',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
        ]);

    expect(EventParticipant::where('team_name', 'Tim Instansi Tanpa Surat Sekolah')->exists())->toBeTrue();

    // Incompatible upload: institution trying to upload school_team_letter is rejected!
    $fakeSchoolDoc = UploadedFile::fake()->create('surat_sekolah.pdf', 100);
    $resInstIncompatible = $this->actingAs($instManager)
        ->postJson(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'institution',
            'team_name' => 'Tim Instansi Forged Upload',
            'contact_person' => 'PIC Instansi',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_documents' => [
                'school_team_letter' => $fakeSchoolDoc,
            ],
        ]);

    $resInstIncompatible->assertStatus(422)->assertJsonValidationErrors(['team_documents']);

    // 2. Community subtype: school
    $schoolCommunity = Community::create([
        'name' => 'SMAN 1 Juara',
        'slug' => 'sman-1-juara',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
        'subtype' => 'school',
        'city_id' => '3273',
    ]);
    $schoolManager = User::factory()->create(['email' => 'school_mgr@test.com', 'is_active' => true]);
    $schoolManager->assignRole('community_manager');
    CommunityOfficial::create([
        'community_id' => $schoolCommunity->id,
        'user_id' => $schoolManager->id,
        'position' => 'Guru Pendamping',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $schoolAthletes = [];
    $schoolMembersPayload = [];
    for ($i = 1; $i <= 5; $i++) {
        $u = User::factory()->create([
            'name' => "Siswa Sekolah {$i}",
            'gender' => 'male',
            'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
            'identity_number' => "327300000000888{$i}",
        ]);
        $u->assignRole('athlete');

        CommunityAthlete::create([
            'community_id' => $schoolCommunity->id,
            'user_id' => $u->id,
            'jersey_number' => $i,
            'position' => 'Guard',
            'status' => 'active',
            'region_status' => 'verified',
            'joined_at' => now(),
        ]);

        $schoolAthletes[] = $u;
        $schoolMembersPayload[] = [
            'user_id' => $u->id,
            'jersey_number' => $i,
            'is_captain' => $i === 1 ? 1 : 0,
            'position' => 'Guard',
            'team_role' => 'player',
        ];
    }

    // School registers WITHOUT school_team_letter: fails!
    $resSchoolMissing = $this->actingAs($schoolManager)
        ->from(route('panel.community-events.create', $this->event->slug))
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'school',
            'team_name' => 'Tim Sekolah Tanpa Surat',
            'contact_person' => 'PIC Sekolah',
            'contact_phone' => '081234567890',
            'members' => $schoolMembersPayload,
        ]);

    $resSchoolMissing->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Sekolah Tanpa Surat')->exists())->toBeFalse();

    // School registers WITH school_team_letter: succeeds!
    $validSchoolDoc = UploadedFile::fake()->create('surat_sekolah_valid.pdf', 100);
    $resSchoolSuccess = $this->actingAs($schoolManager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'school',
            'team_name' => 'Tim Sekolah Ber-Surat',
            'contact_person' => 'PIC Sekolah',
            'contact_phone' => '081234567890',
            'members' => $schoolMembersPayload,
            'team_documents' => [
                'school_team_letter' => $validSchoolDoc,
            ],
        ]);

    expect(EventParticipant::where('team_name', 'Tim Sekolah Ber-Surat')->exists())->toBeTrue();
});

test('newly uploaded files are stored on local disk with is_public false', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Disk Lokal Privat',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate'],
    ]);

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

    $file = UploadedFile::fake()->create('private_mandate.pdf', 100);
    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Berkas Lokal',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_documents' => [
                'team_mandate' => $file,
            ],
        ]);

    $participant = EventParticipant::where('team_name', 'Tim Berkas Lokal')->first();
    expect($participant)->not->toBeNull();

    $doc = Document::where('documentable_type', EventParticipant::class)->where('documentable_id', $participant->id)->first();
    expect($doc)->not->toBeNull()
        ->and($doc->is_public)->toBeFalse()
        ->and(Storage::disk('local')->exists($doc->file_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($doc->file_path))->toBeFalse();
});

test('cleanup new files on failure preserves pre existing files', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Fail Cleanup',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate', 'ktp_athlete'],
    ]);

    // Existing vault document for athlete 1
    Storage::disk('local')->put('vault_documents/old_ktp.pdf', 'old-ktp-content');
    Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $this->athletes[0]->id,
        'category' => 'ktp_athlete',
        'name' => 'KTP Lama',
        'file_path' => 'vault_documents/old_ktp.pdf',
        'status' => 'verified',
        'is_public' => false,
    ]);

    // Setup duplicate jersey number to cause RosterValidationException
    $membersPayload = [];
    foreach ($this->athletes as $index => $ath) {
        $membersPayload[] = [
            'user_id' => $ath->id,
            'jersey_number' => 7, // All same jersey number -> triggers RosterValidationException!
            'is_captain' => $index === 0 ? 1 : 0,
            'position' => 'Guard',
            'team_role' => 'player',
        ];
    }

    $newMandate = UploadedFile::fake()->create('new_mandate.pdf', 100);
    $newKtp = UploadedFile::fake()->create('new_ktp.pdf', 100);

    $response = $this->actingAs($this->manager)
        ->from(route('panel.community-events.create', $this->event->slug))
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Roster Gagal',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'team_documents' => [
                'team_mandate' => $newMandate,
            ],
            'athlete_documents' => [
                $this->athletes[0]->id => [
                    'ktp_athlete' => $newKtp,
                ],
            ],
        ]);

    $response->assertSessionHas('error');
    // Pre-existing file is preserved on local disk!
    expect(Storage::disk('local')->exists('vault_documents/old_ktp.pdf'))->toBeTrue();
});

test('replacing existing athlete document resets verified_at and verified_by to null', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Reset Verified',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['ktp_athlete'],
    ]);

    Storage::disk('local')->put('vault_documents/ath1_old.pdf', 'old-content');
    $existingDoc = Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $this->athletes[0]->id,
        'category' => 'ktp_athlete',
        'name' => 'KTP Atlet 1',
        'file_path' => 'vault_documents/ath1_old.pdf',
        'status' => 'verified',
        'verified_at' => now()->subDays(5),
        'verified_by' => $this->organizer->id,
        'is_public' => false,
    ]);

    // Other 4 athletes have valid docs
    for ($i = 1; $i < 5; $i++) {
        Storage::disk('local')->put("vault_documents/ath_{$i}.pdf", 'content');
        Document::create([
            'documentable_type' => User::class,
            'documentable_id' => $this->athletes[$i]->id,
            'category' => 'ktp_athlete',
            'name' => 'KTP Atlet',
            'file_path' => "vault_documents/ath_{$i}.pdf",
            'status' => 'verified',
            'is_public' => false,
        ]);
    }

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

    $replacementKtp = UploadedFile::fake()->create('replacement_ktp.pdf', 100);

    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Pengganti Dokumen',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
            'athlete_documents' => [
                $this->athletes[0]->id => [
                    'ktp_athlete' => $replacementKtp,
                ],
            ],
        ]);

    $freshDoc = $existingDoc->fresh();
    expect($freshDoc->status)->toBe('pending')
        ->and($freshDoc->verified_at)->toBeNull()
        ->and($freshDoc->verified_by)->toBeNull()
        ->and(Storage::disk('local')->exists($freshDoc->file_path))->toBeTrue();
});

test('unknown or missing DocumentType in required_documents fails closed', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Unknown DocType',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['tipe_dokumen_tidak_dikenal_xyz'],
    ]);

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
        ->postJson(route('panel.community-events.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'registration_source' => 'community',
            'community_subtype' => 'free_association',
            'team_name' => 'Tim Unknown Type',
            'contact_person' => 'PIC Test',
            'contact_phone' => '081234567890',
            'members' => $membersPayload,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['required_documents']);
});

test('organizer can download private team and athlete documents via secure route', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Download Test',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'screening_first',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate', 'ktp_athlete'],
    ]);

    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'community',
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Tim Unduh Berkas',
        'contact_person' => 'PIC Test',
        'contact_phone' => '081234567890',
        'status' => 'registered',
        'registered_at' => now(),
    ]);

    $participant->members()->create([
        'user_id' => $this->athletes[0]->id,
        'jersey_number' => 10,
        'is_captain' => true,
        'position' => 'Guard',
        'team_role' => 'player',
    ]);

    // 1. Team document on local disk
    Storage::disk('local')->put("participant_documents/{$participant->id}_mandat.pdf", 'konten-mandat-privat');
    $teamDoc = Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'team_mandate',
        'name' => 'Surat Mandat Tim',
        'file_path' => "participant_documents/{$participant->id}_mandat.pdf",
        'status' => 'pending',
        'is_public' => false,
    ]);

    // 2. Athlete document on local disk
    Storage::disk('local')->put("vault_documents/{$this->athletes[0]->id}_ktp.pdf", 'konten-ktp-privat');
    $athDoc = Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $this->athletes[0]->id,
        'category' => 'ktp_athlete',
        'name' => 'KTP Atlet',
        'file_path' => "vault_documents/{$this->athletes[0]->id}_ktp.pdf",
        'status' => 'pending',
        'is_public' => false,
    ]);

    // Organizer downloads team doc: 200
    $respTeam = $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $teamDoc->id]));
    $respTeam->assertOk();

    // Organizer downloads athlete doc: 200
    $respAth = $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $athDoc->id]));
    $respAth->assertOk();

    // 3. Legacy public disk fallback
    Storage::disk('public')->put('participant_documents/legacy.pdf', 'legacy-public-content');
    $legacyDoc = Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'team_mandate',
        'name' => 'Surat Mandat Legacy',
        'file_path' => 'participant_documents/legacy.pdf',
        'status' => 'pending',
        'is_public' => false,
    ]);
    $respLegacy = $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $legacyDoc->id]));
    $respLegacy->assertOk();

    // 4. Reject public files: is_public = true -> 403
    $publicDoc = Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'team_mandate',
        'name' => 'Surat Publik',
        'file_path' => 'participant_documents/legacy.pdf',
        'status' => 'pending',
        'is_public' => true,
    ]);
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $publicDoc->id]))
        ->assertForbidden();

    // 5. Reject document belonging to unrelated user
    $unrelatedUser = User::factory()->create();
    $unrelatedDoc = Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $unrelatedUser->id,
        'category' => 'ktp_athlete',
        'name' => 'KTP User Asing',
        'file_path' => "vault_documents/{$this->athletes[0]->id}_ktp.pdf",
        'status' => 'pending',
        'is_public' => false,
    ]);
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $unrelatedDoc->id]))
        ->assertForbidden();

    // 6. Missing physical file -> 404
    $missingFileDoc = Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'team_mandate',
        'name' => 'Surat Hilang',
        'file_path' => 'nonexistent/path/file.pdf',
        'status' => 'pending',
        'is_public' => false,
    ]);
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $missingFileDoc->id]))
        ->assertNotFound();

    // 7. Unauthorized user (manager of another community) -> 403
    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $teamDoc->id]))
        ->assertForbidden();

    // 8. Admin readonly user with event-participant.view allowed via admin workspace route
    Role::firstOrCreate(['name' => 'admin_viewer', 'guard_name' => 'web', 'panel_access' => 'internal']);
    Permission::firstOrCreate(['name' => 'event-participant.view', 'guard_name' => 'web']);
    $adminViewer = User::factory()->create(['is_active' => true]);
    $adminViewer->assignRole('admin_viewer');
    $adminViewer->givePermissionTo('event-participant.view');

    $respAdmin = $this->actingAs($adminViewer)
        ->get(route('admin.events.workspace.teams.documents.download', [$this->event->id, $participant->id, $teamDoc->id]));
    $respAdmin->assertOk();

    // 9. External other user not owning the event -> 403
    $otherOrganizer = User::factory()->create(['is_active' => true]);
    $otherOrganizer->assignRole('organizer');
    $this->actingAs($otherOrganizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $teamDoc->id]))
        ->assertForbidden();

    // 10. Mismatched participant / event -> 404
    $otherEvent = Event::factory()->create([
        'user_id' => $this->organizer->id,
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
    ]);
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$otherEvent->id, $participant->id, $teamDoc->id]))
        ->assertNotFound();

    // 11. Athlete belongs to community but not roster of this participant -> 403
    $unrelatedRosterAthlete = $this->athletes[1];
    Storage::disk('local')->put("vault_documents/{$unrelatedRosterAthlete->id}_ktp.pdf", 'unrelated-roster-ktp');
    $unrelatedRosterDoc = Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $unrelatedRosterAthlete->id,
        'category' => 'ktp_athlete',
        'name' => 'KTP Atlet Non Roster',
        'file_path' => "vault_documents/{$unrelatedRosterAthlete->id}_ktp.pdf",
        'status' => 'pending',
        'is_public' => false,
    ]);
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $unrelatedRosterDoc->id]))
        ->assertForbidden();

    // 12. Document code not in category required_documents -> fail closed (403)
    Storage::disk('local')->put("participant_documents/{$participant->id}_unrequired.pdf", 'unrequired-content');
    $unrequiredDoc = Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'team_sptjm',
        'name' => 'Surat Tidak Diwajibkan Kategori',
        'file_path' => "participant_documents/{$participant->id}_unrequired.pdf",
        'status' => 'pending',
        'is_public' => false,
    ]);
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $unrequiredDoc->id]))
        ->assertForbidden();

    // 13. Unknown document type not in document_types -> fail closed (403)
    Storage::disk('local')->put("participant_documents/{$participant->id}_unknown.pdf", 'unknown-type-content');
    $unknownTypeDoc = Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'totally_unknown_code',
        'name' => 'Dokumen Tipe Ghaib',
        'file_path' => "participant_documents/{$participant->id}_unknown.pdf",
        'status' => 'pending',
        'is_public' => false,
    ]);
    $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.documents.download', [$this->event->id, $participant->id, $unknownTypeDoc->id]))
        ->assertForbidden();
});

test('teams index passes participantDocuments and participantDocumentTypes to view', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Roster Modal',
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'screening_first',
        'registration_fee' => '0.00',
        'allow_community' => true,
        'allowed_community_subtypes' => ['free_association'],
        'required_documents' => ['team_mandate', 'ktp_athlete'],
    ]);

    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'community',
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Tim Modal Bukti',
        'contact_person' => 'PIC Test',
        'contact_phone' => '081234567890',
        'status' => 'registered',
        'registered_at' => now(),
    ]);

    $participant->members()->create([
        'user_id' => $this->athletes[0]->id,
        'jersey_number' => 10,
        'is_captain' => true,
        'position' => 'Guard',
        'team_role' => 'player',
    ]);

    Storage::disk('local')->put("participant_documents/{$participant->id}_mandat.pdf", 'konten-mandat');
    $teamDoc = Document::create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => 'team_mandate',
        'name' => 'Surat Mandat Tim',
        'file_path' => "participant_documents/{$participant->id}_mandat.pdf",
        'status' => 'pending',
        'is_public' => false,
    ]);

    $response = $this->actingAs($this->organizer)
        ->get(route('panel.organizer.teams.index', $this->event->id));

    $response->assertOk()
        ->assertViewHas('participantDocuments')
        ->assertViewHas('participantDocumentTypes');

    $pDocs = $response->viewData('participantDocuments');
    expect($pDocs)->toHaveKey($participant->id);
    expect($pDocs[$participant->id]->pluck('id')->all())->toContain($teamDoc->id);
});
