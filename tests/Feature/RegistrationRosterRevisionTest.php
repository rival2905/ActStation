<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationInspectionSummary;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');

    foreach ([
        'community_manager' => 'external',
        'athlete' => 'external',
        'member' => 'external',
        'superadmin' => 'both',
    ] as $role => $panelAccess) {
        Role::query()->firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'],
            ['panel_access' => $panelAccess],
        )->update(['panel_access' => $panelAccess]);
    }

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'rf03-sport'],
        ['name' => 'RF03 Sport', 'is_active' => true],
    );

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('community_manager');
    $this->community = Community::query()->create([
        'name' => 'RF03 Community',
        'slug' => 'rf03-community-'.uniqid(),
        'sport_category_id' => $this->sport->id,
        'subtype' => 'free_association',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $this->official = CommunityOfficial::query()->create([
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'position' => 'Manager',
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    $this->otherManager = User::factory()->create(['is_active' => true]);
    $this->otherManager->assignRole('community_manager');
    $this->otherCommunity = Community::query()->create([
        'name' => 'RF03 Other Community',
        'slug' => 'rf03-other-community-'.uniqid(),
        'sport_category_id' => $this->sport->id,
        'subtype' => 'free_association',
        'status' => 'verified',
        'is_active' => true,
    ]);
    CommunityOfficial::query()->create([
        'community_id' => $this->otherCommunity->id,
        'user_id' => $this->otherManager->id,
        'position' => 'Manager',
        'status' => 'active',
        'started_at' => now(),
        'ended_at' => null,
    ]);

    $this->organizer = User::factory()->create(['is_active' => true]);
    $this->event = Event::factory()->create([
        'user_id' => $this->organizer->id,
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'registration_deadline' => now()->addDays(5),
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(12),
    ]);

    $this->athletes = collect(range(1, 6))->map(function (int $number): User {
        $athlete = User::factory()->create([
            'is_active' => true,
            'gender' => 'male',
            'birth_date' => now()->subYears(20)->format('Y-m-d'),
        ]);
        $athlete->assignRole('athlete');
        CommunityAthlete::query()->create([
            'community_id' => $this->community->id,
            'user_id' => $athlete->id,
            'jersey_number' => $number,
            'position' => 'Guard',
            'initiated_by' => 'community',
            'status' => 'active',
            'responded_at' => now(),
            'region_status' => 'verified',
            'region_verified_at' => now(),
            'joined_at' => now(),
            'left_at' => null,
        ]);

        return $athlete;
    });

    $this->makeCategory = function (array $attributes = []): EventCategory {
        return EventCategory::factory()->create(array_merge([
            'event_id' => $this->event->id,
            'gender' => 'male',
            'min_roster' => 5,
            'max_roster' => 6,
            'registration_flow' => 'screening_first',
            'registration_fee' => 0,
            'allow_club' => false,
            'allow_community' => true,
            'community_subtype' => 'free_association',
            'allowed_community_subtypes' => ['free_association'],
            'required_documents' => [],
        ], $attributes));
    };

    $this->makeParticipant = function (EventCategory $category, string $status = 'revision_needed'): EventParticipant {
        $participant = EventParticipant::query()->create([
            'event_id' => $this->event->id,
            'event_category_id' => $category->id,
            'registrant_type' => 'community',
            'community_id' => $this->community->id,
            'user_id' => $this->manager->id,
            'team_name' => 'RF03 Team '.uniqid(),
            'contact_person' => 'RF03 Manager',
            'contact_phone' => '081234567890',
            'status' => $status,
            'revision_notes' => 'Perbaiki roster dan berkas.',
            'registered_at' => now(),
        ]);

        foreach ($this->athletes->take(5)->values() as $index => $athlete) {
            $participant->members()->create([
                'user_id' => $athlete->id,
                'team_role' => 'player',
                'jersey_number' => $index + 1,
                'is_captain' => $index === 0,
                'position' => 'Guard',
            ]);
        }

        return $participant;
    };

    $this->validMembers = fn (): array => $this->athletes->slice(1, 5)->values()->map(
        fn (User $athlete, int $index): array => [
            'user_id' => $athlete->id,
            'team_role' => 'player',
            'jersey_number' => $index + 21,
            'is_captain' => $index === 1,
            'position' => $index === 0 ? 'Center' : 'Forward',
        ],
    )->all();
});

test('active community manager can open revision form while roster athlete remains read only', function (): void {
    $participant = ($this->makeParticipant)(($this->makeCategory)());

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->get(route('panel.community-events.revise', $participant))
        ->assertOk();

    $this->actingAs($this->athletes->first())
        ->get(route('panel.community-events.revise', $participant))
        ->assertForbidden();
});

test('roster serves inspection and restricts unrelated manager role to own athlete details', function (): void {
    $participant = ($this->makeParticipant)(($this->makeCategory)());
    $athlete = $this->athletes->first();
    $athlete->assignRole('community_manager');

    $this->actingAs($athlete)->get(route('panel.community-events.roster', $participant))
        ->assertOk()
        ->assertViewHas('inspection')
        ->assertViewHas('isManager', false)
        ->assertViewHas('canRevise', false)
        ->assertSee($athlete->email)
        ->assertDontSee($this->athletes->get(1)->email);

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)->get(route('panel.community-events.roster', $participant))
        ->assertOk()->assertViewHas('isManager', true)->assertViewHas('canRevise', true);
});

test('inspection team completeness requires each athlete document availability but not approval', function (): void {
    $code = 'rf04_inspection_identity';
    DocumentType::query()->create(['code' => $code, 'name' => 'Berkas Atlet', 'scope' => 'athlete', 'is_active' => true]);
    $participant = ($this->makeParticipant)(($this->makeCategory)(['required_documents' => [$code]]));
    $service = app(RegistrationInspectionSummary::class);
    expect($service->inspect($participant)['team_complete'])->toBeFalse();
    foreach ($this->athletes->take(5) as $athlete) {
        $path = 'inspection/'.$athlete->id.'.pdf';
        Storage::disk('local')->put($path, 'test');
        $athlete->documents()->create(['name' => 'Berkas', 'category' => $code, 'file_path' => $path, 'status' => 'pending', 'is_public' => false]);
    }
    expect($service->inspect($participant)['team_complete'])->toBeTrue();
    $this->athletes->first()->documents()->where('category', $code)->update(['status' => 'rejected']);
    expect($service->inspect($participant)['team_complete'])->toBeFalse();
});

test('resubmit rejects a stale active community token', function (): void {
    $participant = ($this->makeParticipant)(($this->makeCategory)());

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->otherCommunity->id,
            'members' => ($this->validMembers)(),
        ])
        ->assertUnprocessable();

    expect($participant->fresh()->status)->toBe('revision_needed');
});

test('valid revision replaces roster and scoped documents then returns screening to inspection', function (): void {
    $teamCode = 'rf03_team_mandate';
    $athleteCode = 'rf03_athlete_id';
    DocumentType::query()->updateOrCreate(
        ['code' => $teamCode],
        ['name' => 'RF03 Team Mandate', 'scope' => 'team', 'applicable_entities' => ['free_association'], 'is_active' => true],
    );
    DocumentType::query()->updateOrCreate(
        ['code' => $athleteCode],
        ['name' => 'RF03 Athlete ID', 'scope' => 'athlete', 'applicable_entities' => ['free_association'], 'is_active' => true],
    );
    $category = ($this->makeCategory)(['required_documents' => [$teamCode, $athleteCode]]);
    $participant = ($this->makeParticipant)($category);

    Storage::disk('local')->put('participant_documents/rf03-old-team.pdf', 'old-team');
    Document::query()->create([
        'documentable_type' => EventParticipant::class,
        'documentable_id' => $participant->id,
        'category' => $teamCode,
        'name' => 'Old Team Mandate',
        'file_path' => 'participant_documents/rf03-old-team.pdf',
        'status' => 'rejected',
        'is_public' => false,
    ]);
    foreach ($this->athletes->slice(1, 5) as $athlete) {
        Storage::disk('local')->put("vault_documents/rf03-old-{$athlete->id}.pdf", 'old-athlete');
        Document::query()->create([
            'documentable_type' => User::class,
            'documentable_id' => $athlete->id,
            'category' => $athleteCode,
            'name' => 'Old Athlete ID',
            'file_path' => "vault_documents/rf03-old-{$athlete->id}.pdf",
            'status' => 'rejected',
            'is_public' => false,
        ]);
    }

    $athleteDocuments = [];
    foreach ($this->athletes->slice(1, 5) as $athlete) {
        $athleteDocuments[$athlete->id][$athleteCode] = UploadedFile::fake()->create("athlete-{$athlete->id}.pdf", 100, 'application/pdf');
    }

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
            'team_documents' => [
                $teamCode => UploadedFile::fake()->create('mandate.pdf', 100, 'application/pdf'),
            ],
            'athlete_documents' => $athleteDocuments,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $participant->refresh();
    expect($participant->status)->toBe('registered')
        ->and($participant->revision_notes)->toBeNull()
        ->and($participant->members()->pluck('user_id')->all())->toEqualCanonicalizing($this->athletes->slice(1, 5)->pluck('id')->all())
        ->and($participant->members()->where('jersey_number', 22)->where('is_captain', true)->exists())->toBeTrue()
        ->and($participant->members()->where('jersey_number', 21)->where('position', 'Center')->exists())->toBeTrue();

    $teamDocument = Document::query()
        ->where('documentable_type', EventParticipant::class)
        ->where('documentable_id', $participant->id)
        ->where('category', $teamCode)
        ->latest('id')
        ->firstOrFail();
    expect($teamDocument->status)->toBe('pending')
        ->and($teamDocument->is_public)->toBeFalse()
        ->and($teamDocument->file_path)->not->toBe('participant_documents/rf03-old-team.pdf');

    foreach ($this->athletes->slice(1, 5) as $athlete) {
        $athleteDocument = Document::query()
            ->where('documentable_type', User::class)
            ->where('documentable_id', $athlete->id)
            ->where('category', $athleteCode)
            ->latest('id')
            ->firstOrFail();
        expect($athleteDocument->status)->toBe('pending')
            ->and($athleteDocument->is_public)->toBeFalse()
            ->and($athleteDocument->file_path)->not->toBe("vault_documents/rf03-old-{$athlete->id}.pdf");
    }
});

test('resubmit restores flow status without creating a second invoice', function (string $flow, int $fee, bool $paid, string $expectedStatus): void {
    CommunityAthlete::where('community_id', $this->community->id)->update(['region_status' => 'rejected']);
    $category = ($this->makeCategory)([
        'registration_flow' => $flow,
        'registration_fee' => $fee,
        'payment_start' => $fee > 0 ? now()->subDay() : null,
        'payment_end' => $fee > 0 ? now()->addDays(3) : null,
    ]);
    $participant = ($this->makeParticipant)($category);

    if ($fee > 0) {
        Transaction::query()->create([
            'user_id' => $this->manager->id,
            'invoice_number' => 'INV-RF03-'.strtoupper(uniqid()),
            'type' => 'registration',
            'payable_type' => 'event_participant',
            'payable_id' => $participant->id,
            'amount' => $fee,
            'fee' => 0,
            'total' => $fee,
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? now() : null,
            'expired_at' => now()->addDays(3),
        ]);
    }
    $invoiceCount = Transaction::query()->where('payable_type', 'event_participant')->where('payable_id', $participant->id)->count();

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($participant->fresh()->status)->toBe($expectedStatus)
        ->and($participant->fresh()->revision_notes)->toBeNull()
        ->and(Transaction::query()->where('payable_type', 'event_participant')->where('payable_id', $participant->id)->count())->toBe($invoiceCount);
})->with([
    'screening first paid' => ['screening_first', 500000, true, 'registered'],
    'payment first paid' => ['payment_first', 500000, true, 'registered'],
    'payment first unpaid' => ['payment_first', 500000, false, 'pending_payment'],
    'invitation paid' => ['by_invitation', 500000, true, 'registered'],
    'invitation unpaid' => ['by_invitation', 500000, false, 'pending_payment'],
    'free' => ['instant_free', 0, false, 'registered'],
]);

test('another community manager unrelated user and inactive owner cannot revise registration', function (): void {
    $participant = ($this->makeParticipant)(($this->makeCategory)());
    $unrelated = User::factory()->create(['is_active' => true]);
    $unrelated->assignRole('member');

    $this->withSession(['current_community_id' => $this->otherCommunity->id])
        ->actingAs($this->otherManager)
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->otherCommunity->id,
            'members' => ($this->validMembers)(),
        ])
        ->assertForbidden();

    $this->actingAs($unrelated)
        ->put(route('panel.community-events.resubmit', $participant), ['members' => ($this->validMembers)()])
        ->assertForbidden();

    $this->official->update(['status' => 'inactive', 'ended_at' => now()]);
    $this->actingAs($this->manager)
        ->get(route('panel.community-events.revise', $participant))
        ->assertForbidden();

    expect($participant->fresh()->status)->toBe('revision_needed');
});

test('non revision registration states reject both revision form and resubmit', function (string $status): void {
    $participant = ($this->makeParticipant)(($this->makeCategory)(), $status);
    $before = $participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray();

    $get = $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->get(route('panel.community-events.revise', $participant));
    expect($get->getStatusCode())->toBeIn([403, 404, 409, 422]);

    $put = $this->actingAs($this->manager)
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
        ]);
    expect($put->getStatusCode())->toBeIn([403, 404, 409, 422]);

    expect($participant->fresh()->status)->toBe($status)
        ->and($participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray())->toBe($before);
})->with(['confirmed', 'registered', 'pending_payment']);

test('invalid live category roster leaves old snapshot notes and status untouched', function (): void {
    $participant = ($this->makeParticipant)(($this->makeCategory)(['min_roster' => 5, 'max_roster' => 5]));
    $before = $participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray();
    $invalidMembers = ($this->validMembers)();
    $invalidMembers[1]['jersey_number'] = $invalidMembers[0]['jersey_number'];

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->from(route('panel.community-events.revise', $participant))
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => $invalidMembers,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('members.1.jersey_number');

    $participant->refresh();
    expect($participant->status)->toBe('revision_needed')
        ->and($participant->revision_notes)->toBe('Perbaiki roster dan berkas.')
        ->and($participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray())->toBe($before);
});

test('confirmed roster cannot be mutated directly through admin roster update', function (): void {
    $participant = ($this->makeParticipant)(($this->makeCategory)(), 'confirmed');
    $before = $participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray();
    $permission = Permission::query()->firstOrCreate(['name' => 'event-participant.update', 'guard_name' => 'web']);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('superadmin');
    $admin->givePermissionTo($permission);

    $this->actingAs($admin)
        ->put(route('admin.events.participants.roster.update', [$this->event, $participant]), [
            'members' => ($this->validMembers)(),
        ])
        ->assertForbidden();

    expect($participant->fresh()->status)->toBe('confirmed')
        ->and($participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray())->toBe($before);
});

test('resubmit rejects forged foreign athlete upload and preserves snapshot', function (): void {
    $athleteCode = 'rf03_athlete_id';
    DocumentType::query()->updateOrCreate(
        ['code' => $athleteCode],
        ['name' => 'RF03 Athlete ID', 'scope' => 'athlete', 'applicable_entities' => ['free_association'], 'is_active' => true],
    );
    $category = ($this->makeCategory)(['required_documents' => [$athleteCode]]);
    $participant = ($this->makeParticipant)($category);
    $before = $participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray();

    $foreignAthlete = User::factory()->create(['is_active' => true]);
    $foreignAthlete->assignRole('athlete');

    $athleteDocuments = [];
    foreach ($this->athletes->slice(1, 5) as $athlete) {
        $athleteDocuments[$athlete->id][$athleteCode] = UploadedFile::fake()->create("athlete-{$athlete->id}.pdf", 100, 'application/pdf');
    }
    // Foreign athlete not in roster
    $athleteDocuments[$foreignAthlete->id][$athleteCode] = UploadedFile::fake()->create('foreign.pdf', 100, 'application/pdf');

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->from(route('panel.community-events.revise', $participant))
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
            'athlete_documents' => $athleteDocuments,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    $participant->refresh();
    expect($participant->status)->toBe('revision_needed')
        ->and($participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray())->toBe($before);
});

test('resubmit rejects wrong scope or unknown document code and preserves snapshot', function (): void {
    $teamCode = 'rf03_team_mandate';
    DocumentType::query()->updateOrCreate(
        ['code' => $teamCode],
        ['name' => 'RF03 Team Mandate', 'scope' => 'team', 'applicable_entities' => ['free_association'], 'is_active' => true],
    );
    $category = ($this->makeCategory)(['required_documents' => [$teamCode]]);
    $participant = ($this->makeParticipant)($category);
    $before = $participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray();

    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->from(route('panel.community-events.revise', $participant))
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
            'team_documents' => [
                'forged_unknown_code' => UploadedFile::fake()->create('forged.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    $participant->refresh();
    expect($participant->status)->toBe('revision_needed')
        ->and($participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray())->toBe($before);
});

test('resubmit enforces community subtype applicability and skips inapplicable document requirements', function (): void {
    $freeDocCode = 'rf03_free_code';
    $schoolDocCode = 'rf03_school_only_code';
    DocumentType::query()->updateOrCreate(
        ['code' => $freeDocCode],
        ['name' => 'Free Code', 'scope' => 'team', 'applicable_entities' => ['free_association'], 'is_active' => true],
    );
    DocumentType::query()->updateOrCreate(
        ['code' => $schoolDocCode],
        ['name' => 'School Only Code', 'scope' => 'team', 'applicable_entities' => ['school'], 'is_active' => true],
    );

    // Category requires both, but community is 'free_association'
    $category = ($this->makeCategory)(['required_documents' => [$freeDocCode, $schoolDocCode]]);
    $participant = ($this->makeParticipant)($category);

    // Sediakan freeDocCode saja -> Sukses karena schoolDocCode tidak berlaku untuk free_association
    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
            'team_documents' => [
                $freeDocCode => UploadedFile::fake()->create('free.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($participant->fresh()->status)->toBe('registered');
});

test('resubmit rejects inapplicable document upload for community subtype', function (): void {
    $freeDocCode = 'rf03_free_code';
    $schoolDocCode = 'rf03_school_only_code';
    DocumentType::query()->updateOrCreate(
        ['code' => $freeDocCode],
        ['name' => 'Free Code', 'scope' => 'team', 'applicable_entities' => ['free_association'], 'is_active' => true],
    );
    DocumentType::query()->updateOrCreate(
        ['code' => $schoolDocCode],
        ['name' => 'School Only Code', 'scope' => 'team', 'applicable_entities' => ['school'], 'is_active' => true],
    );

    $category = ($this->makeCategory)(['required_documents' => [$freeDocCode, $schoolDocCode]]);
    $participant = ($this->makeParticipant)($category);

    // Mencoba mengunggah schoolDocCode yang tidak applicable untuk free_association -> Ditolak
    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->from(route('panel.community-events.revise', $participant))
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
            'team_documents' => [
                $freeDocCode => UploadedFile::fake()->create('free.pdf', 100, 'application/pdf'),
                $schoolDocCode => UploadedFile::fake()->create('school.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($participant->fresh()->status)->toBe('revision_needed');
});

test('resubmit rejects missing mandatory document when not available in vault', function (): void {
    $teamCode = 'rf03_team_mandatory';
    DocumentType::query()->updateOrCreate(
        ['code' => $teamCode],
        ['name' => 'Mandatory Team Doc', 'scope' => 'team', 'applicable_entities' => ['free_association'], 'is_active' => true],
    );
    $category = ($this->makeCategory)(['required_documents' => [$teamCode]]);
    $participant = ($this->makeParticipant)($category);
    $before = $participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray();

    // Tidak ada dokumen di vault dan tidak ada unggahan dokumen tim
    $this->withSession(['current_community_id' => $this->community->id])
        ->actingAs($this->manager)
        ->from(route('panel.community-events.revise', $participant))
        ->put(route('panel.community-events.resubmit', $participant), [
            'panel_community_id' => $this->community->id,
            'members' => ($this->validMembers)(),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    $participant->refresh();
    expect($participant->status)->toBe('revision_needed')
        ->and($participant->members()->orderBy('id')->get(['user_id', 'jersey_number', 'is_captain', 'position'])->toArray())->toBe($before);
});
