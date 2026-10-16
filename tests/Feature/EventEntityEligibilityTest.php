<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Http\Requests\Admin\StoreCategoryRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        ['name' => 'Umum', 'sport_category_id' => $this->sport->id],
        ['min_age' => 10, 'max_age' => 40, 'sort_order' => 1]
    );

    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addDays(10),
        'ends_at' => Carbon::now()->addDays(15),
    ]);

    $this->manager = User::factory()->create([
        'email' => 'manager_eligibility@test.com',
        'is_active' => true,
    ]);
    $this->manager->assignRole('community_manager');
});

test('academic-only categories reject club bypass while open tournaments still accept schools', function (): void {
    foreach (['school_smp', 'school_sma', 'university'] as $subtype) {
        $category = EventCategory::factory()->create(['event_id' => $this->event->id, 'allow_club' => true, 'allow_community' => true, 'allowed_community_subtypes' => [$subtype]]);
        expect($category->acceptsCommunityType('club'))->toBeFalse()
            ->and($category->acceptsCommunityType('institution', 'corporate'))->toBeFalse()
            ->and($category->acceptsCommunityType('free_association'))->toBeFalse();
    }
    $open = EventCategory::factory()->create(['event_id' => $this->event->id, 'allow_club' => true, 'allow_community' => true, 'allowed_community_subtypes' => null, 'community_subtype' => null]);
    expect($open->acceptsCommunityType('school', null, 'sma'))->toBeTrue()
        ->and($open->acceptsCommunityType('university'))->toBeTrue()
        ->and($open->acceptsCommunityType('institution', 'corporate'))->toBeTrue();
});

test('institution manager sees only matching registration options and cannot forge an academic draft', function (): void {
    $community = Community::factory()->create(['subtype' => 'institution', 'institution_type' => 'corporate', 'is_active' => true, 'status' => 'verified']);
    CommunityOfficial::factory()->create(['community_id' => $community->id, 'user_id' => $this->manager->id, 'status' => 'active', 'ended_at' => null]);
    $school = EventCategory::factory()->create(['event_id' => $this->event->id, 'allow_club' => true, 'allow_community' => true, 'allowed_community_subtypes' => ['school_sma']]);
    $company = EventCategory::factory()->create(['event_id' => $this->event->id, 'allow_club' => false, 'allow_community' => true, 'allowed_community_subtypes' => ['institution'], 'allowed_institution_types' => ['corporate']]);
    $this->actingAs($this->manager)->withSession(['current_community_id' => $community->id]);
    $response = $this->get(route('panel.community-events.create', $this->event->slug))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $options = (new DOMXPath($dom))->query('//select[@id="event_category_id"]/option[@value!=""]');
    expect(collect($options)->map(fn ($option) => (int) $option->getAttribute('value'))->all())->toBe([$company->id]);
    $this->postJson(route('panel.registration-drafts.store', $this->event->slug), ['event_category_id' => $school->id, 'team_name' => 'Tidak Sah', 'community_subtype' => 'school', 'panel_community_id' => $community->id])
        ->assertUnprocessable()->assertJsonValidationErrors('event_category_id');
    $this->assertDatabaseMissing('event_registration_drafts', ['community_id' => $community->id, 'event_category_id' => $school->id]);
    $draftId = $this->postJson(route('panel.registration-drafts.store', $this->event->slug), ['event_category_id' => $company->id, 'team_name' => 'Tim Perusahaan', 'panel_community_id' => $community->id])->assertCreated()->json('draft.id');
    $this->putJson(route('panel.registration-drafts.update', [$this->event->slug, $draftId]), ['revision' => 1, 'event_category_id' => $school->id, 'panel_community_id' => $community->id])->assertUnprocessable()->assertJsonValidationErrors('event_category_id');
    $this->assertDatabaseHas('event_registration_drafts', ['id' => $draftId, 'event_category_id' => $company->id, 'revision' => 1]);
});

test('category accepts multiple community subtypes when configured', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Piala Pelajar & Mahasiswa',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school', 'university'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($category->acceptsCommunityType('school'))->toBeTrue()
        ->and($category->acceptsCommunityType('university'))->toBeTrue()
        ->and($category->acceptsCommunityType('free_association'))->toBeFalse()
        ->and($category->acceptsCommunityType('club'))->toBeFalse();
});

test('category restricts institution types when explicit restriction array is configured', function (): void {
    $restrictedCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Piala Pengcab & BUMN',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['institution'],
        'allowed_institution_types' => ['federation', 'government'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // Federation & government are accepted
    expect($restrictedCategory->acceptsCommunityType('institution', 'federation'))->toBeTrue()
        ->and($restrictedCategory->acceptsCommunityType('institution', 'government'))->toBeTrue()
        // Corporate is rejected
        ->and($restrictedCategory->acceptsCommunityType('institution', 'corporate'))->toBeFalse()
        // Null / missing institution type is rejected when restriction exists
        ->and($restrictedCategory->acceptsCommunityType('institution', null))->toBeFalse()
        ->and($restrictedCategory->acceptsCommunityType('institution', ''))->toBeFalse();

    // Unrestricted institution category accepts any institution
    $unrestrictedCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Piala Antar Lembaga Umum',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['institution'],
        'allowed_institution_types' => null,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($unrestrictedCategory->acceptsCommunityType('institution', 'corporate'))->toBeTrue()
        ->and($unrestrictedCategory->acceptsCommunityType('institution', null))->toBeTrue();
});

test('club cannot enter via allow_community loophole', function (): void {
    $categoryNoClub = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Komunitas Non Klub',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school', 'free_association'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($categoryNoClub->acceptsCommunityType('club'))->toBeFalse();

    $categoryWithClub = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Divisi Klub',
        'allow_club' => true,
        'allow_community' => false,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($categoryWithClub->acceptsCommunityType('club'))->toBeTrue()
        ->and($categoryWithClub->acceptsCommunityType('school'))->toBeFalse();
});

test('legacy category behavior preserved when allowed_community_subtypes is null', function (): void {
    $legacyCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Legacy Sekolah Category',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => null,
        'community_subtype' => 'school',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($legacyCategory->acceptsCommunityType('school'))->toBeTrue()
        ->and($legacyCategory->acceptsCommunityType('university'))->toBeFalse();

    $legacyOpenCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Legacy Open Category',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => null,
        'community_subtype' => null,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($legacyOpenCategory->acceptsCommunityType('school'))->toBeTrue()
        ->and($legacyOpenCategory->acceptsCommunityType('university'))->toBeTrue()
        ->and($legacyOpenCategory->acceptsCommunityType('free_association'))->toBeTrue();
});

test('registration guard rejects source spoofing from community profile', function (): void {
    $community = Community::create([
        'name' => 'SMA 1 Test',
        'slug' => 'sma-1-test',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'school',
        'status' => 'verified',
        'city_id' => '3273',
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Sekolah',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    /** @var RegistrationGuard $guard */
    $guard = app(RegistrationGuard::class);

    // Spoofed source 'club' while community is 'school'
    expect(fn () => $guard->validate(
        event: $this->event,
        category: $category,
        communityId: $community->id,
        teamName: 'SMA 1 Team',
        registrationSource: 'club',
        communitySubtype: 'school',
    ))->toThrow(RegistrationGuardException::class, 'Sumber atau subtipe komunitas pada pendaftaran tidak sesuai dengan profil komunitas.');

    // Spoofed subtype 'university' while community is 'school'
    expect(fn () => $guard->validate(
        event: $this->event,
        category: $category,
        communityId: $community->id,
        teamName: 'SMA 1 Team',
        registrationSource: 'community',
        communitySubtype: 'university',
    ))->toThrow(RegistrationGuardException::class, 'Sumber atau subtipe komunitas pada pendaftaran tidak sesuai dengan profil komunitas.');
});

test('participant registration does not require organizer KYC or payout account', function (): void {
    // Organizer event has no KYC or payout configured
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Turnamen Terbuka',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $community = Community::create([
        'name' => 'SMP 2 Test',
        'slug' => 'smp-2-test',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'school',
        'status' => 'verified',
        'city_id' => '3273',
    ]);

    /** @var RegistrationGuard $guard */
    $guard = app(RegistrationGuard::class);

    // Validating registration must pass without throwing KYC / payout errors
    $guard->validate(
        event: $this->event,
        category: $category,
        communityId: $community->id,
        teamName: 'SMP 2 A',
        registrationSource: 'community',
        communitySubtype: 'school',
    );

    expect(true)->toBeTrue();
});

test('same-category athlete in two teams remains rejected', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Remaja',
        'min_roster' => 5,
        'max_roster' => 12,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $athlete = User::factory()->create([
        'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
        'gender' => 'male',
    ]);
    $athlete->assignRole('athlete');

    $participant1 = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'team_name' => 'Tim Pertama',
        'status' => 'registered',
    ]);

    $participant1->members()->create([
        'user_id' => $athlete->id,
        'jersey_number' => 7,
    ]);

    $participant2 = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'team_name' => 'Tim Kedua',
        'status' => 'registered',
    ]);

    $rosterValidator = app(RosterValidator::class);

    expect(fn () => $rosterValidator->validate($participant2, [
        ['user_id' => $athlete->id, 'jersey_number' => 10],
    ]))->toThrow(RosterValidationException::class);
});

test('allowedEntityLabels generates dynamic human readable labels', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Campuran',
        'allow_club' => true,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school', 'institution'],
        'allowed_institution_types' => ['federation'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $labels = $category->allowedEntityLabels();
    expect($labels)->toContain('Klub');

    $schoolLabel = collect($labels)->first(fn ($l) => str_contains($l, 'Sekolah'));
    expect($schoolLabel)->not->toBeNull();

    $instLabel = collect($labels)->first(fn ($l) => str_contains($l, 'Instansi'));
    expect($instLabel)->not->toBeNull()
        ->and($instLabel)->toContain('Federasi');
});

test('category form request preserves missing arrays on update and rejects risky changes when participants exist', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-18 Pelajar',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'allowed_institution_types' => null,
        'required_entity_documents' => ['institution_decree'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // Create a participant to trigger risky edit locks
    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'team_name' => 'SMA Risky',
        'status' => 'registered',
    ]);

    $request = new StoreCategoryRequest;
    $request->setRouteResolver(fn () => new class($category, $this->event)
    {
        public function __construct(private $cat, private $evt) {}

        public function parameter($name)
        {
            if ($name === 'category') {
                return $this->cat;
            }
            if ($name === 'event') {
                return $this->evt;
            }

            return null;
        }
    });

    // Test 1: Updating categoryData preserves existing arrays if not supplied in input
    $request->merge([
        'name' => 'KU-18 Pelajar Updated',
        'match_type' => '5x5',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 1,
    ]);

    $data = $request->categoryData();
    expect($data['allowed_community_subtypes'])->toEqual(['school'])
        ->and($data['required_entity_documents'])->toEqual(['institution_decree']);

    // Test 2: Validation rejects attempting to change allowed_community_subtypes when participants exist
    $request->merge([
        'name' => 'KU-18 Pelajar Edit',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 1,
        'allowed_community_subtypes' => ['university'], // Changing from school to university
    ]);

    $validator = Validator::make($request->all(), $request->rules());
    $request->withValidator($validator);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('allowed_community_subtypes'))->toBeTrue();

    // Test 3: Validation rejects 'club' inside allowed_community_subtypes
    $validatorClubLoophole = Validator::make([
        'name' => 'Kategori Loophole',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 1,
        'allowed_community_subtypes' => ['club'],
    ], $request->rules());

    expect($validatorClubLoophole->fails())->toBeTrue()
        ->and($validatorClubLoophole->errors()->has('allowed_community_subtypes.0'))->toBeTrue();
});

test('registration guard rejects inactive community with real is_active property', function (): void {
    $community = Community::create([
        'name' => 'Komunitas Nonaktif',
        'slug' => 'komunitas-nonaktif',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'school',
        'status' => 'verified',
        'city_id' => '3273',
        'is_active' => false,
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Sekolah Aktif',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $guard = app(RegistrationGuard::class);

    expect(fn () => $guard->validate(
        event: $this->event,
        category: $category,
        communityId: $community->id,
        teamName: 'Tim Nonaktif',
        registrationSource: 'community',
        communitySubtype: 'school',
    ))->toThrow(RegistrationGuardException::class, 'Komunitas pendaftar tidak aktif.');
});

test('registration guard never borrows another official identity when applicant context is missing', function (): void {
    Storage::fake('local');
    $community = Community::factory()->create(['subtype' => 'club', 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $community->id, 'user_id' => $this->manager->id, 'position' => 'manager', 'status' => 'active', 'started_at' => now()]);
    Storage::disk('local')->put('community_documents/registration.pdf', 'document');
    $community->documents()->create(['category' => 'club_registration', 'name' => 'Pendaftaran klub', 'file_path' => 'community_documents/registration.pdf', 'status' => 'verified', 'is_public' => false]);
    $category = EventCategory::factory()->create(['event_id' => $this->event->id, 'allow_club' => true, 'required_entity_documents' => ['club_registration'], 'registration_flow' => 'instant_free', 'registration_fee' => 0]);
    expect(fn () => app(RegistrationGuard::class)->validate($this->event, $category, $community->id, 'Tim Tanpa Konteks'))
        ->toThrow(RegistrationGuardException::class, 'Dokumen legalitas entitas [club_registration] belum diverifikasi atau tidak tersedia.');
});

test('registration guard fails closed when entity documents required and userId cannot be resolved', function (): void {
    $community = Community::create([
        'name' => 'Klub Tanpa Official',
        'slug' => 'klub-tanpa-official',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'club',
        'status' => 'verified',
        'city_id' => '3273',
        'is_active' => true,
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Legal Required',
        'allow_club' => true,
        'allow_community' => false,
        'required_entity_documents' => ['club_registration'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $guard = app(RegistrationGuard::class);

    expect(fn () => $guard->validate(
        event: $this->event,
        category: $category,
        communityId: $community->id,
        teamName: 'Klub A',
        userId: null,
    ))->toThrow(RegistrationGuardException::class, 'Dokumen legalitas entitas [club_registration] belum diverifikasi atau tidak tersedia.');
});

test('updating categoryData with explicit empty array or clear intent clears restrictions instead of preserving them', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-20 Instansi Berizin',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['institution'],
        'allowed_institution_types' => ['federation'],
        'required_entity_documents' => ['appointment_decree'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $request = new StoreCategoryRequest;
    $request->setRouteResolver(fn () => new class($category, $this->event)
    {
        public function __construct(private $cat, private $evt) {}

        public function parameter($name)
        {
            return $name === 'category' ? $this->cat : ($name === 'event' ? $this->evt : null);
        }
    });

    // 1. Explicit empty array should clear restrictions/entitydocs
    $request->merge([
        'name' => 'KU-20 Instansi Terbuka',
        'match_type' => '5x5',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 1,
        'allowed_community_subtypes' => ['institution'],
        'allowed_institution_types' => [],
        'required_entity_documents' => [],
    ]);

    $data = $request->categoryData();
    expect($data['allowed_institution_types'])->toBeEmpty()
        ->and($data['required_entity_documents'])->toBeEmpty();

    // 2. Clear intent via hidden fields (when UI checkboxes are all unchecked)
    $request2 = new StoreCategoryRequest;
    $request2->setRouteResolver(fn () => new class($category, $this->event)
    {
        public function __construct(private $cat, private $evt) {}

        public function parameter($name)
        {
            return $name === 'category' ? $this->cat : ($name === 'event' ? $this->evt : null);
        }
    });
    $request2->merge([
        'name' => 'KU-20 Instansi Form Edit',
        'match_type' => '5x5',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 1,
        'allowed_community_subtypes' => ['institution'],
        'has_allowed_institution_types' => 1,
        'has_required_entity_documents' => 1,
    ]);

    $data2 = $request2->categoryData();
    expect($data2['allowed_institution_types'])->toBeEmpty()
        ->and($data2['required_entity_documents'])->toBeEmpty();
});

test('malformed category arrays return validation error 422 instead of 500', function (): void {
    $request = new StoreCategoryRequest;
    $request->setRouteResolver(fn () => new class($this->event)
    {
        public function __construct(private $evt) {}

        public function parameter($name)
        {
            return $name === 'event' ? $this->evt : null;
        }
    });

    $payload = [
        'name' => 'KU-Malformed',
        'match_type' => '5x5',
        'gender' => 'male',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 1,
        'allowed_community_subtypes' => 'not-an-array',
        'allowed_institution_types' => 'invalid-string',
        'required_entity_documents' => 'invalid-string',
    ];

    $validator = Validator::make($payload, $request->rules());
    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('allowed_community_subtypes'))->toBeTrue()
        ->and($validator->errors()->has('allowed_institution_types'))->toBeTrue()
        ->and($validator->errors()->has('required_entity_documents'))->toBeTrue();

    $payload2 = [
        'name' => 'KU-Invalid-Codes',
        'match_type' => '5x5',
        'gender' => 'male',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 1,
        'allowed_community_subtypes' => ['invalid_subtype_code'],
        'allowed_institution_types' => ['invalid_inst_code'],
        'required_entity_documents' => ['invalid_doc_code'],
    ];

    $validator2 = Validator::make($payload2, $request->rules());
    $request->withValidator($validator2);

    expect($validator2->fails())->toBeTrue()
        ->and($validator2->errors()->has('allowed_community_subtypes.0'))->toBeTrue()
        ->and($validator2->errors()->has('allowed_institution_types.0'))->toBeTrue()
        ->and($validator2->errors()->has('required_entity_documents.0'))->toBeTrue();
});

test('allowedEntityLabels formats institution restriction labels in legacy single subtype category', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Legacy Instansi Restricted',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => null,
        'community_subtype' => 'institution',
        'allowed_institution_types' => ['federation', 'government'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $labels = $category->allowedEntityLabels();
    expect($labels)->toHaveCount(1);
    expect($labels[0])->toContain('Instansi')
        ->and($labels[0])->toContain('Federasi')
        ->and($labels[0])->toContain('Pemerintah');
});
