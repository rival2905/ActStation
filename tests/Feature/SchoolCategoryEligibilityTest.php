<?php

declare(strict_types=1);

use App\Contracts\CommunityLookup;
use App\Modules\Community\Models\Community;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Http\Requests\Admin\StoreCategoryRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
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
});

test('acceptsCommunityType separates SD SMP SMA with normalization and rejects unknown level', function (): void {
    $categorySmp = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kejuaraan SMP',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_smp'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // SMP accepted
    expect($categorySmp->acceptsCommunityType('school', null, 'smp'))->toBeTrue()
        ->and($categorySmp->acceptsCommunityType('school', null, 'mts'))->toBeTrue()
        // SMA / SMK / SD rejected
        ->and($categorySmp->acceptsCommunityType('school', null, 'sma'))->toBeFalse()
        ->and($categorySmp->acceptsCommunityType('school', null, 'smk'))->toBeFalse()
        ->and($categorySmp->acceptsCommunityType('school', null, 'ma'))->toBeFalse()
        ->and($categorySmp->acceptsCommunityType('school', null, 'sd'))->toBeFalse()
        ->and($categorySmp->acceptsCommunityType('school', null, 'mi'))->toBeFalse()
        // Unknown or missing school level rejected (fail-closed)
        ->and($categorySmp->acceptsCommunityType('school', null, null))->toBeFalse()
        ->and($categorySmp->acceptsCommunityType('school', null, ''))->toBeFalse()
        ->and($categorySmp->acceptsCommunityType('school', null, 'unknown_level'))->toBeFalse();

    $categorySma = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kejuaraan SMA/SMK',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_sma'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // SMA, SMK, MA all normalize to school_sma
    expect($categorySma->acceptsCommunityType('school', null, 'sma'))->toBeTrue()
        ->and($categorySma->acceptsCommunityType('school', null, 'smk'))->toBeTrue()
        ->and($categorySma->acceptsCommunityType('school', null, 'ma'))->toBeTrue()
        // SMP and SD rejected
        ->and($categorySma->acceptsCommunityType('school', null, 'smp'))->toBeFalse()
        ->and($categorySma->acceptsCommunityType('school', null, 'sd'))->toBeFalse()
        // Unknown school level rejected
        ->and($categorySma->acceptsCommunityType('school', null, null))->toBeFalse();

    $categorySd = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kejuaraan SD/MI',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_sd'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($categorySd->acceptsCommunityType('school', null, 'sd'))->toBeTrue()
        ->and($categorySd->acceptsCommunityType('school', null, 'mi'))->toBeTrue()
        ->and($categorySd->acceptsCommunityType('school', null, 'smp'))->toBeFalse()
        ->and($categorySd->acceptsCommunityType('school', null, 'sma'))->toBeFalse();
});

test('legacy school token in allowed_community_subtypes or community_subtype accepts any school level', function (): void {
    $legacyArrayCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Sekolah Aturan Lama Array',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($legacyArrayCategory->acceptsCommunityType('school', null, 'smp'))->toBeTrue()
        ->and($legacyArrayCategory->acceptsCommunityType('school', null, 'sma'))->toBeTrue()
        ->and($legacyArrayCategory->acceptsCommunityType('school', null, 'sd'))->toBeTrue()
        ->and($legacyArrayCategory->acceptsCommunityType('school', null, null))->toBeTrue()
        ->and($legacyArrayCategory->acceptsCommunityType('university'))->toBeFalse();

    $legacySingleSubtypeCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Sekolah Aturan Lama Single',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => null,
        'community_subtype' => 'school',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($legacySingleSubtypeCategory->acceptsCommunityType('school', null, 'smp'))->toBeTrue()
        ->and($legacySingleSubtypeCategory->acceptsCommunityType('school', null, null))->toBeTrue();
});

test('institution spoofing and clubs cannot bypass separated school category', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Khusus SMP',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_smp'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    // Club is rejected because allow_club is false
    expect($category->acceptsCommunityType('club', null, 'smp'))->toBeFalse();

    // Institution pretending to have smp level is rejected
    expect($category->acceptsCommunityType('institution', 'government', 'smp'))->toBeFalse();
});

test('allowedEntityLabels formats canonical school tokens and legacy school label', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Multi Jenjang',
        'allow_club' => true,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_sd', 'school_smp', 'school_sma', 'university'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $labels = $category->allowedEntityLabels();
    expect($labels)->toContain('Klub')
        ->and($labels)->toContain('SD/MI')
        ->and($labels)->toContain('SMP/MTs')
        ->and($labels)->toContain('SMA/SMK')
        ->and($labels)->toContain('Perguruan Tinggi / Universitas');

    $legacyCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Legacy Sekolah',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    expect($legacyCategory->allowedEntityLabels())->toContain('Sekolah (Semua Jenjang; aturan lama)');
});

test('RegistrationGuard resolves school level via CommunityLookup server NPSN source and rejects SMP on SMA category', function (): void {
    DB::table('schools')->insert([
        [
            'npsn' => '10101001',
            'name' => 'SMP Negeri 1 Test',
            'school_level' => 'smp',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'dataset_year' => 2026,
            'source' => 'test',
        ],
        [
            'npsn' => '10101002',
            'name' => 'SMA Negeri 1 Test',
            'school_level' => 'sma',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'dataset_year' => 2026,
            'source' => 'test',
        ],
    ]);

    $smpCommunity = Community::create([
        'name' => 'SMPN 1 Test Community',
        'slug' => 'smpn-1-test-comm',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'school',
        'status' => 'verified',
        'city_id' => '3273',
        'npsn' => '10101001',
    ]);

    $smaCommunity = Community::create([
        'name' => 'SMAN 1 Test Community',
        'slug' => 'sman-1-test-comm',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'school',
        'status' => 'verified',
        'city_id' => '3273',
        'npsn' => '10101002',
    ]);

    $unknownCommunity = Community::create([
        'name' => 'Sekolah Tanpa NPSN Community',
        'slug' => 'sekolah-tanpa-npsn-comm',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'school',
        'status' => 'verified',
        'city_id' => '3273',
        'npsn' => null,
    ]);

    $categorySma = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Divisi SMA',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_sma'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    /** @var RegistrationGuard $guard */
    $guard = app(RegistrationGuard::class);

    // SMA community succeeds on SMA category
    $guard->validate(
        event: $this->event,
        category: $categorySma,
        communityId: $smaCommunity->id,
        teamName: 'SMAN 1 Team',
        registrationSource: 'community',
        communitySubtype: 'school',
    );
    expect(true)->toBeTrue();

    // SMP community fails on SMA category
    expect(fn () => $guard->validate(
        event: $this->event,
        category: $categorySma,
        communityId: $smpCommunity->id,
        teamName: 'SMPN 1 Team',
        registrationSource: 'community',
        communitySubtype: 'school',
    ))->toThrow(RegistrationGuardException::class, 'Sumber atau subtipe komunitas tidak diizinkan pada kategori ini.');

    // Unknown school community fails on strict SMA category
    expect(fn () => $guard->validate(
        event: $this->event,
        category: $categorySma,
        communityId: $unknownCommunity->id,
        teamName: 'Unknown Team',
        registrationSource: 'community',
        communitySubtype: 'school',
    ))->toThrow(RegistrationGuardException::class, 'Sumber atau subtipe komunitas tidak diizinkan pada kategori ini.');

    // Legacy category accepts unknown school community
    $legacyCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Divisi Sekolah Bebas',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $guard->validate(
        event: $this->event,
        category: $legacyCategory,
        communityId: $unknownCommunity->id,
        teamName: 'Unknown Team Legacy',
        registrationSource: 'community',
        communitySubtype: 'school',
    );
    expect(true)->toBeTrue();
});

test('StoreCategoryRequest accepts separated canonical tokens and legacy school but rejects invalid tokens', function (): void {
    $request = new StoreCategoryRequest;

    $rules = $request->rules();

    // Canonical tokens + legacy school pass
    $validSubtypes = ['school_sd', 'school_smp', 'school_sma', 'university', 'institution', 'free_association', 'school'];
    foreach ($validSubtypes as $subtype) {
        $validator = Validator::make([
            'allowed_community_subtypes' => [$subtype],
        ], [
            'allowed_community_subtypes' => $rules['allowed_community_subtypes'],
            'allowed_community_subtypes.*' => $rules['allowed_community_subtypes.*'],
        ]);
        expect($validator->passes())->toBeTrue();
    }

    // Invalid tokens rejected
    $invalidTokens = ['club', 'school_smk', 'random_subtype'];
    foreach ($invalidTokens as $token) {
        $validator = Validator::make([
            'allowed_community_subtypes' => [$token],
        ], [
            'allowed_community_subtypes' => $rules['allowed_community_subtypes'],
            'allowed_community_subtypes.*' => $rules['allowed_community_subtypes.*'],
        ]);
        expect($validator->fails())->toBeTrue();
    }
});

test('category form blade renders canonical choices without combined school and preserves legacy school for existing category', function (): void {
    $event = $this->event;

    // View rendered for new category
    $htmlNew = view('events.categories._form-fields', [
        'event' => $event,
        'category' => null,
        'fieldPrefix' => 'category_new',
    ])->render();

    expect($htmlNew)->toContain('SD/MI')
        ->and($htmlNew)->toContain('SMP/MTs')
        ->and($htmlNew)->toContain('SMA/SMK')
        ->and($htmlNew)->toContain('value="school_sd"')
        ->and($htmlNew)->toContain('value="school_smp"')
        ->and($htmlNew)->toContain('value="school_sma"')
        ->and($htmlNew)->not->toContain('Sekolah (Semua Jenjang; aturan lama)')
        ->and($htmlNew)->not->toContain('SD/MI (school_sd)');

    // View rendered for existing category with legacy school
    $existingLegacyCategory = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kategori Sekolah Lama',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $htmlLegacy = view('events.categories._form-fields', [
        'event' => $event,
        'category' => $existingLegacyCategory,
        'fieldPrefix' => 'category_edit',
    ])->render();

    expect($htmlLegacy)->toContain('Sekolah (Semua Jenjang; aturan lama)')
        ->and($htmlLegacy)->toContain('value="school"');
});

test('panel events register view passes school_level to acceptsCommunityType for both eligible and ineligible filter', function (): void {
    DB::table('schools')->insert([
        'npsn' => '20202001',
        'name' => 'SMP Negeri 2 Test',
        'school_level' => 'smp',
        'school_status' => 'negeri',
        'province_id' => '32',
        'city_id' => '32.73',
        'dataset_year' => 2026,
        'source' => 'test',
    ]);

    $community = Community::create([
        'name' => 'SMPN 2 Test',
        'slug' => 'smpn-2-test',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'school',
        'status' => 'verified',
        'city_id' => '3273',
        'npsn' => '20202001',
    ]);

    $categorySmp = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Khusus SMP',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_smp'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $categorySma = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Khusus SMA',
        'allow_club' => false,
        'allow_community' => true,
        'allowed_community_subtypes' => ['school_sma'],
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $lookup = app(CommunityLookup::class);
    $communitySummary = $lookup->find($community->id);

    view()->share('errors', new ViewErrorBag);

    $view = view('panel.events.register', [
        'event' => $this->event->load('categories'),
        'community' => $communitySummary,
        'eligibleRosterByCategory' => [],
        'documentTypes' => collect(),
        'vaultDocuments' => collect(),
        'legalDocuments' => [],
    ])->render();

    // SMP category is in eligible select options
    expect($view)->toContain('Kategori Khusus SMP')
        ->and($view)->toContain((string) $categorySmp->id)
        // SMA category is not eligible for SMP community, so it's not in the selection dropdown
        ->and($view)->not->toContain('Kategori Khusus SMA (5x5 - Mixed)');
});
