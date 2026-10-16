<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\RegistrationDraftService;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\Payment\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::fake();
    Storage::fake('local');
    Storage::fake('public');

    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    // User Manager Komunitas
    $this->manager = User::factory()->create([
        'email' => 'manager_draft@test.com',
        'is_active' => true,
    ]);
    $this->manager->assignRole('community_manager');

    // Komunitas A
    $this->community = Community::create([
        'name' => 'Klub Basket Satria Draft',
        'slug' => 'klub-basket-satria-draft',
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

    // 5 Atlet Komunitas A
    $this->athletes = [];
    for ($i = 1; $i <= 5; $i++) {
        $u = User::factory()->create([
            'name' => "Pemain Draft {$i}",
            'gender' => 'male',
            'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
            'identity_number' => "327311111111000{$i}",
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
        'starts_at' => Carbon::now()->addDays(10),
        'ends_at' => Carbon::now()->addDays(15),
    ]);

    $this->docTeam = DocumentType::firstOrCreate(
        ['code' => 'surat_mandat'],
        ['name' => 'Surat Mandat Tim', 'scope' => 'team', 'is_active' => true]
    );
    $this->docAthlete = DocumentType::firstOrCreate(
        ['code' => 'kartu_keluarga'],
        ['name' => 'Kartu Keluarga', 'scope' => 'athlete', 'is_active' => true]
    );

    $this->category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket Putra U-18',
        'gender' => 'male',
        'max_teams' => 16,
        'max_teams_per_entity' => 1,
        'min_roster' => 1,
        'max_roster' => 12,
        'allow_club' => true,
        'allow_community' => true,
        'registration_flow' => 'payment_first',
        'registration_fee' => 500000.00,
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(5),
        'payment_start' => now()->subHour(),
        'payment_end' => now()->addDays(5),
        'required_documents' => ['surat_mandat', 'kartu_keluarga'],
    ]);
});

test('draft rejects athlete gender mismatch before saving while mixed remains available', function (?string $gender, string $categoryGender): void {
    $this->category->update(['gender' => $categoryGender]);
    $this->athletes[0]->update(['gender' => $gender]);
    $this->actingAs($this->manager);
    $payload = ['event_category_id' => $this->category->id, 'team_name' => 'Gender Guard', 'members' => [['user_id' => $this->athletes[0]->id, 'jersey_number' => 7]]];
    $this->postJson(route('panel.registration-drafts.store', $this->event->slug), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('members.0.gender');
    $this->assertDatabaseMissing('event_registration_drafts', ['event_id' => $this->event->id]);
    $draft = RegistrationDraft::create(['user_id' => $this->manager->id, 'community_id' => $this->community->id, 'event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'payload' => ['team_name' => 'Preserve', 'members' => []], 'revision' => 1, 'expires_at' => now()->addDay()]);
    $this->putJson(route('panel.registration-drafts.update', [$this->event->slug, $draft->id]), $payload + ['revision' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('members.0.gender');
    expect($draft->fresh()->revision)->toBe(1)->and($draft->fresh()->payload['members'])->toBe([]);
    $this->category->update(['gender' => 'mixed']);
    $this->putJson(route('panel.registration-drafts.update', [$this->event->slug, $draft->id]), $payload + ['revision' => 1])->assertOk();
})->with([['male', 'female'], ['female', 'male'], [null, 'female']]);

test('registration athlete choices exclude other genders from each category', function (): void {
    $this->category->update(['gender' => 'female']);
    $this->athletes[1]->update(['gender' => 'female']);
    $this->athletes[2]->update(['gender' => null]);
    $response = $this->actingAs($this->manager)->get(route('panel.community-events.create', $this->event->slug))->assertOk();
    $dom = new \DOMDocument;
    @$dom->loadHTML($response->getContent());
    $checks = (new \DOMXPath($dom))->query('//*[@id="roster-cat-'.$this->category->id.'"]//tr[@data-meets-requirements="1"]//input[contains(@class,"athlete-check")]');
    expect(collect($checks)->map(fn ($check) => (int) $check->getAttribute('value'))->all())->toBe([$this->athletes[1]->id]);
});

test('draft creation stores allowlisted partial payload without secret token or arbitrary fields', function (): void {
    $response = $this->actingAs($this->manager)
        ->postJson(route('panel.registration-drafts.store', $this->event->slug), [
            'team_name' => 'Draft Tim A',
            'contact_person' => 'Budi PIC',
            'contact_phone' => '081234567890',
            'notes' => 'Catatan pendaftaran draft',
            'invitation_token' => 'SECRET_TOKEN_SHOULD_BE_STRIPPED_FROM_DRAFT_PAYLOAD_1234567890123456',
            'user_id' => 9999, // should be ignored
            'community_id' => 8888, // should be ignored
            'status' => 'confirmed', // should be ignored
            'participant_id' => 7777, // should be ignored
            'members' => [
                [
                    'user_id' => $this->athletes[0]->id,
                    'jersey_number' => 10,
                    'is_captain' => 1,
                    'position' => 'Guard',
                ],
            ],
        ]);

    $response->assertStatus(201);
    $data = $response->json('draft');

    expect($data)->toHaveKey('id')
        ->and(Str::isUuid($data['id']))->toBeTrue()
        ->and($data['revision'])->toBe(1)
        ->and($data['payload']['team_name'])->toBe('Draft Tim A')
        ->and($data['payload']['contact_person'])->toBe('Budi PIC')
        ->and($data['payload'])->not->toHaveKey('invitation_token')
        ->and($data['payload'])->not->toHaveKey('user_id')
        ->and($data['payload'])->not->toHaveKey('community_id')
        ->and($data['payload'])->not->toHaveKey('status')
        ->and($data['payload'])->not->toHaveKey('participant_id');

    $dbDraft = RegistrationDraft::find($data['id']);
    expect($dbDraft)->not->toBeNull()
        ->and($dbDraft->user_id)->toBe($this->manager->id)
        ->and($dbDraft->community_id)->toBe($this->community->id)
        ->and($dbDraft->event_id)->toBe($this->event->id)
        ->and($dbDraft->participant_id)->toBeNull()
        ->and($dbDraft->expires_at->isFuture())->toBeTrue();
});

test('draft show returns only for own active community and event scoping', function (): void {
    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => ['team_name' => 'Tim Scoped'],
        'revision' => 1,
        'expires_at' => now()->addDays(7),
    ]);

    // 1. Own manager access -> 200
    $this->actingAs($this->manager)
        ->getJson(route('panel.registration-drafts.show', ['slug' => $this->event->slug, 'draft' => $draft->id]))
        ->assertOk()
        ->assertJsonPath('draft.payload.team_name', 'Tim Scoped');

    // 2. Malformed UUID -> 422
    $this->actingAs($this->manager)
        ->getJson(route('panel.registration-drafts.show', ['slug' => $this->event->slug, 'draft' => 'not-a-uuid']))
        ->assertStatus(422);

    // 3. Cross-user / foreign manager access -> 403
    $otherManager = User::factory()->create(['is_active' => true]);
    $otherManager->assignRole('community_manager');
    $otherCommunity = Community::create([
        'name' => 'Klub Basket Sebelah',
        'slug' => 'klub-basket-sebelah',
        'sport_category_id' => $this->sport->id,
        'status' => 'verified',
        'city_id' => '3273',
    ]);
    CommunityOfficial::create([
        'community_id' => $otherCommunity->id,
        'user_id' => $otherManager->id,
        'position' => 'Manajer',
        'status' => 'active',
        'started_at' => now(),
    ]);

    $this->actingAs($otherManager)
        ->getJson(route('panel.registration-drafts.show', ['slug' => $this->event->slug, 'draft' => $draft->id]))
        ->assertStatus(403);

    // 4. Cross-event access -> 403
    $otherEvent = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
    ]);
    $this->actingAs($this->manager)
        ->getJson(route('panel.registration-drafts.show', ['slug' => $otherEvent->slug, 'draft' => $draft->id]))
        ->assertStatus(403);
});

test('draft show returns 410 when draft is expired', function (): void {
    $expiredDraft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => ['team_name' => 'Tim Expired'],
        'revision' => 1,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($this->manager)
        ->getJson(route('panel.registration-drafts.show', ['slug' => $this->event->slug, 'draft' => $expiredDraft->id]))
        ->assertStatus(410);
});

test('draft update enforces optimistic locking revision CAS and returns 409 on conflict', function (): void {
    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => ['team_name' => 'Initial Name'],
        'revision' => 1,
        'expires_at' => now()->addDays(7),
    ]);

    // Revision conflict: client sends wrong revision 99
    $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draft->id]), [
            'revision' => 99,
            'team_name' => 'Conflicting Name',
        ])
        ->assertStatus(409);

    // Matching revision 1 -> success and revision increments to 2
    $response = $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draft->id]), [
            'revision' => 1,
            'team_name' => 'Updated Name',
        ])
        ->assertOk();

    expect($response->json('draft.revision'))->toBe(2);
    expect($draft->fresh()->revision)->toBe(2);
    expect($draft->fresh()->payload['team_name'])->toBe('Updated Name');

    // Attempting update with old revision 1 now fails with 409
    $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draft->id]), [
            'revision' => 1,
            'team_name' => 'Stale Name',
        ])
        ->assertStatus(409);
});

test('draft upload stages files privately without user vault mutations and sanitizes file paths in response', function (): void {
    $file = UploadedFile::fake()->create('mandat.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim With File',
            'team_documents' => [
                'surat_mandat' => $file,
            ],
        ], ['Accept' => 'application/json']);

    $response->assertStatus(201);
    $draftId = $response->json('draft.id');
    $files = $response->json('draft.files');

    // File metadata returned must NOT expose server paths
    expect($files)->toHaveKey('team_documents')
        ->and($files['team_documents'])->toHaveKey('surat_mandat')
        ->and($files['team_documents']['surat_mandat']['name'])->toBe('mandat.pdf')
        ->and($files['team_documents']['surat_mandat'])->not->toHaveKey('staged_path')
        ->and($files['team_documents']['surat_mandat'])->not->toHaveKey('path');

    // No document should be created in the User vault or EventParticipant vault while in draft
    expect(Document::query()->where('documentable_type', User::class)->count())->toBe(0);
    expect(Document::query()->where('documentable_type', EventParticipant::class)->count())->toBe(0);
});

test('removed athlete staged files stay reusable but are excluded from final submission', function (): void {
    $kept = $this->athletes[0];
    $removed = $this->athletes[1];
    $path = 'registration_drafts/removed-athlete.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 sample');
    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id, 'community_id' => $this->community->id,
        'event_id' => $this->event->id, 'event_category_id' => $this->category->id,
        'payload' => ['members' => [['user_id' => $kept->id]]],
        'files' => ['athlete_documents' => [$removed->id => ['kartu_keluarga' => ['staged_path' => $path, 'name' => 'removed.pdf']]]],
        'expires_at' => now()->addDay(),
    ]);
    $request = new Request(['members' => [['user_id' => $kept->id]]]);
    app(RegistrationDraftService::class)->resolveStagedFiles($draft, $request);

    expect($request->file('athlete_documents', []))->toBeEmpty();
    expect(Storage::disk('local')->exists($path))->toBeTrue();
    expect($draft->fresh()->files['athlete_documents'])->toHaveKey($removed->id);
    expect(CommunityAthlete::where('community_id', $this->community->id)->where('user_id', $removed->id)->value('status'))->toBe('active');
});

test('draft deletion safely cleans staged files without touching existing vault documents', function (): void {
    // Create an existing document in user vault
    $existingVaultDoc = Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $this->manager->id,
        'category' => 'ktp',
        'name' => 'KTP Manager',
        'file_path' => 'vault_documents/ktp_manager.pdf',
        'status' => 'verified',
    ]);

    $file = UploadedFile::fake()->create('draft_doc.pdf', 100, 'application/pdf');
    $createResponse = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim To Delete',
            'team_documents' => [
                'surat_mandat' => $file,
            ],
        ], ['Accept' => 'application/json']);

    $draftId = $createResponse->json('draft.id');

    // Delete draft
    $deleteResponse = $this->actingAs($this->manager)
        ->deleteJson(route('panel.registration-drafts.destroy', ['slug' => $this->event->slug, 'draft' => $draftId]));

    $deleteResponse->assertOk();
    expect(RegistrationDraft::find($draftId))->toBeNull();

    // Existing vault document remains completely untouched
    expect(Document::find($existingVaultDoc->id))->not->toBeNull();
});

test('replaying draft with existing participant_id is idempotent and creates no duplicate participant or invoice', function (): void {
    $existingParticipant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Tim Existing Replay',
        'contact_person' => 'Budi',
        'contact_phone' => '081234567890',
        'status' => 'pending_payment',
    ]);

    $existingInvoice = Transaction::create([
        'user_id' => $this->manager->id,
        'invoice_number' => 'INV-REPLAY-1',
        'type' => 'registration',
        'payable_type' => 'event_participant',
        'payable_id' => $existingParticipant->id,
        'amount' => 500000.00,
        'fee' => 0.00,
        'total' => 500000.00,
        'status' => 'pending',
    ]);

    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => ['team_name' => 'Tim Existing Replay'],
        'revision' => 1,
        'participant_id' => $existingParticipant->id,
        'expires_at' => now()->addDays(7),
    ]);

    $initialParticipantCount = EventParticipant::count();
    $initialTransactionCount = Transaction::count();

    // Replay submission with draft_id
    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'draft_id' => $draft->id,
            'draft_revision' => 1,
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Existing Replay',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10],
            ],
        ]);

    // Redirects to summary
    $response->assertRedirect(route('panel.community-events.create', $this->event->slug));

    // Zero duplicate participant or transaction created
    expect(EventParticipant::count())->toBe($initialParticipantCount);
    expect(Transaction::count())->toBe($initialTransactionCount);
});

test('draft update on expired draft returns 410', function (): void {
    $expiredDraft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => ['team_name' => 'Tim Expired Update'],
        'revision' => 1,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $expiredDraft->id]), [
            'revision' => 1,
            'team_name' => 'New Name Attempt',
        ])
        ->assertStatus(410);
});

test('submitting expired draft via final registration returns 410', function (): void {
    $expiredDraft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => ['team_name' => 'Tim Expired Submit'],
        'revision' => 1,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'draft_id' => $expiredDraft->id,
            'draft_revision' => 1,
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Expired Submit',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10],
            ],
        ]);

    $response->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Expired Submit')->count())->toBe(0);
});

test('draft update with If-Match header enforces revision CAS and succeeds', function (): void {
    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => ['team_name' => 'IfMatch Initial'],
        'revision' => 2,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($this->manager)
        ->withHeaders(['If-Match' => '"2"'])
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draft->id]), [
            'team_name' => 'IfMatch Updated',
        ])
        ->assertOk()
        ->assertJsonPath('draft.revision', 3)
        ->assertJsonPath('draft.payload.team_name', 'IfMatch Updated');

    expect($draft->fresh()->revision)->toBe(3);
});

test('draft update conflict fails CAS and cleans newly uploaded files while preserving acknowledged files', function (): void {
    // 1. Initial draft with an acknowledged staged file
    $initialFile = UploadedFile::fake()->create('initial_mandat.pdf', 100, 'application/pdf');
    $createResponse = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim CAS Files',
            'team_documents' => [
                'surat_mandat' => $initialFile,
            ],
        ], ['Accept' => 'application/json']);

    $createResponse->assertStatus(201);
    $draftId = $createResponse->json('draft.id');

    $draft = RegistrationDraft::findOrFail($draftId);
    $initialStagedPath = $draft->files['team_documents']['surat_mandat']['staged_path'];
    expect(Storage::disk('local')->exists($initialStagedPath))->toBeTrue();

    // 2. Client sends conflicting revision (revision 99 vs current revision 1) with a new file
    $newFile = UploadedFile::fake()->create('new_mandat.pdf', 100, 'application/pdf');
    $conflictResponse = $this->actingAs($this->manager)
        ->put(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draftId]), [
            'revision' => 99,
            'team_documents' => [
                'surat_mandat' => $newFile,
            ],
        ], ['Accept' => 'application/json']);

    $conflictResponse->assertStatus(409);

    // Initial acknowledged file MUST still exist!
    expect(Storage::disk('local')->exists($initialStagedPath))->toBeTrue();

    // Draft revision remains unchanged
    expect($draft->fresh()->revision)->toBe(1);
});

test('draft clear_files deletes requested staged files only after CAS update succeeds', function (): void {
    $file = UploadedFile::fake()->create('to_clear.pdf', 100, 'application/pdf');
    $createResponse = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Clear Files',
            'team_documents' => [
                'surat_mandat' => $file,
            ],
        ], ['Accept' => 'application/json']);

    $draftId = $createResponse->json('draft.id');
    $draft = RegistrationDraft::findOrFail($draftId);
    $stagedPath = $draft->files['team_documents']['surat_mandat']['staged_path'];
    expect(Storage::disk('local')->exists($stagedPath))->toBeTrue();

    // Clear file with matching revision 1
    $clearResponse = $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draftId]), [
            'revision' => 1,
            'clear_files' => ['team_documents.surat_mandat'],
        ]);

    $clearResponse->assertOk();
    expect($clearResponse->json('draft.files.team_documents'))->toBeEmpty();
    expect(Storage::disk('local')->exists($stagedPath))->toBeFalse();
});

test('draft replay after participant deletion is rejected via preserved tombstone', function (): void {
    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Tim Tombstone Check',
        'contact_person' => 'Budi',
        'contact_phone' => '081234567890',
        'status' => 'pending_payment',
    ]);

    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'payload' => [
            'team_name' => 'Tim Tombstone Check',
            '_tombstone' => true,
            '_submitted' => true,
            '_participant_id' => $participant->id,
        ],
        'revision' => 1,
        'participant_id' => null, // Emulating MySQL ON DELETE SET NULL on participant FK
        'expires_at' => now()->addDays(7),
    ]);

    // Delete the participant
    $participant->delete();

    // Replay attempt must be rejected with 409, not fall through to create a new participant
    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'draft_id' => $draft->id,
            'draft_revision' => 1,
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Tombstone Check',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10],
            ],
        ]);

    $response->assertSessionHas('error');
    expect(EventParticipant::where('team_name', 'Tim Tombstone Check')->count())->toBe(0);
});

test('draft upload rejects athlete documents for unauthorized athlete not in community roster', function (): void {
    $unauthorizedUser = User::factory()->create(['is_active' => true]);
    $unauthorizedUser->assignRole('athlete');

    $file = UploadedFile::fake()->create('unauth_kk.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Unauth Athlete',
            'athlete_documents' => [
                $unauthorizedUser->id => [
                    'kartu_keluarga' => $file,
                ],
            ],
        ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('athlete_documents');
});

test('draft upload with missing category rejects upload with 422 and stores no file no-categoryforeign', function (): void {
    $file = UploadedFile::fake()->create('mandat.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'team_name' => 'Tim No Category',
            'team_documents' => [
                'surat_mandat' => $file,
            ],
        ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('event_category_id');

    expect(Storage::disk('local')->allFiles('registration_drafts'))->toBeEmpty();
});

test('draft upload rejects forged selected foreign athlete ID with 422 and stores no file selectedforeign', function (): void {
    $foreignUser = User::factory()->create(['is_active' => true]);
    $foreignUser->assignRole('athlete');

    $file = UploadedFile::fake()->create('kk_forged.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Forged Member',
            'members' => [
                ['user_id' => $foreignUser->id, 'jersey_number' => 7],
            ],
            'athlete_documents' => [
                $foreignUser->id => [
                    'kartu_keluarga' => $file,
                ],
            ],
        ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('athlete_documents');

    expect(Storage::disk('local')->allFiles('registration_drafts'))->toBeEmpty();
});

test('draft upload rejects wrong scope or unknown document code with 422 and stores no file wrongscope upload nofile', function (): void {
    $file = UploadedFile::fake()->create('wrong.pdf', 100, 'application/pdf');

    // Case 1: team doc code uploaded to athlete
    $response1 = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Wrong Scope 1',
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10],
            ],
            'athlete_documents' => [
                $this->athletes[0]->id => [
                    'surat_mandat' => $file,
                ],
            ],
        ], ['Accept' => 'application/json']);

    $response1->assertStatus(422)
        ->assertJsonValidationErrors('athlete_documents');

    // Case 2: athlete doc code uploaded to team
    $response2 = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Wrong Scope 2',
            'team_documents' => [
                'kartu_keluarga' => $file,
            ],
        ], ['Accept' => 'application/json']);

    $response2->assertStatus(422)
        ->assertJsonValidationErrors('team_documents');

    expect(Storage::disk('local')->allFiles('registration_drafts'))->toBeEmpty();
});

test('submitting draft with mismatched category is rejected before writes categoryfinalmismatch', function (): void {
    $otherCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket Putri U-18',
        'gender' => 'female',
        'max_teams' => 16,
        'registration_fee' => 500000.00,
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addDays(5),
        'payment_start' => now()->subHour(),
        'payment_end' => now()->addDays(5),
    ]);

    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'payload' => [
            'team_name' => 'Tim Category Mismatch',
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10],
            ],
        ],
        'revision' => 1,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'draft_id' => $draft->id,
            'draft_revision' => 1,
            'event_category_id' => $otherCategory->id,
            'team_name' => 'Tim Category Mismatch',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10],
            ],
        ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('event_category_id');

    expect(EventParticipant::where('team_name', 'Tim Category Mismatch')->count())->toBe(0);
});

test('submitted draft files cleared from DB atomically and show reports no tombstone or path leakage finalfilesmetadataempty', function (): void {
    CommunityAthlete::where('community_id', $this->community->id)->update(['region_status' => 'pending']);
    $file = UploadedFile::fake()->createWithContent('mandat.pdf', '%PDF-1.4 fake pdf content');
    $athleteFile = UploadedFile::fake()->createWithContent('kk.pdf', '%PDF-1.4 athlete kk content');

    $createResponse = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Final Files Empty',
            'team_documents' => [
                'surat_mandat' => $file,
            ],
            'athlete_documents' => [
                $this->athletes[0]->id => [
                    'kartu_keluarga' => $athleteFile,
                ],
            ],
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10, 'is_captain' => 1],
            ],
        ], ['Accept' => 'application/json']);

    $createResponse->assertStatus(201);
    $draftId = $createResponse->json('draft.id');

    $submitResponse = $this->actingAs($this->manager)
        ->post(route('panel.community-events.store', $this->event->slug), [
            'draft_id' => $draftId,
            'draft_revision' => 1,
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Final Files Empty',
            'contact_person' => 'Budi',
            'contact_phone' => '081234567890',
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10, 'is_captain' => 1],
            ],
        ]);

    expect(session('error'))->toBeNull();
    $submitResponse->assertSessionHasNoErrors();
    $submitResponse->assertRedirect(route('panel.community-events.create', $this->event->slug));

    $draft = RegistrationDraft::findOrFail($draftId);
    expect($draft->isSubmitted())->toBeTrue();
    expect($draft->files)->toBe(['team_documents' => [], 'athlete_documents' => []]);

    $showResponse = $this->actingAs($this->manager)
        ->getJson(route('panel.registration-drafts.show', ['slug' => $this->event->slug, 'draft' => $draftId]));

    $showResponse->assertOk()
        ->assertJsonPath('draft.is_submitted', true)
        ->assertJsonPath('draft.files.team_documents', [])
        ->assertJsonPath('draft.files.athlete_documents', []);

    $payload = $showResponse->json('draft.payload');
    expect($payload)->not->toHaveKey('_tombstone')
        ->and($payload)->not->toHaveKey('_submitted')
        ->and($payload)->not->toHaveKey('_participant_id');
});

test('deleting finalized draft is rejected with 409 finaldraftdelete409', function (): void {
    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'team_name' => 'Tim Final Delete Check',
        'contact_person' => 'Budi',
        'contact_phone' => '081234567890',
        'status' => 'pending_payment',
    ]);

    $draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'Tim Final Delete Check'],
        'revision' => 1,
        'participant_id' => $participant->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($this->manager)
        ->deleteJson(route('panel.registration-drafts.destroy', ['slug' => $this->event->slug, 'draft' => $draft->id]));

    $response->assertStatus(409);
    expect(RegistrationDraft::find($draft->id))->not->toBeNull();
});

test('draft update changing category clears staged files and members safely after commit', function (): void {
    $file = UploadedFile::fake()->create('mandat.pdf', 100, 'application/pdf');

    $createResponse = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $this->category->id,
            'team_name' => 'Tim Category Switch',
            'team_documents' => [
                'surat_mandat' => $file,
            ],
            'members' => [
                ['user_id' => $this->athletes[0]->id, 'jersey_number' => 10],
            ],
        ], ['Accept' => 'application/json']);

    $createResponse->assertStatus(201);
    $draftId = $createResponse->json('draft.id');
    $stagedPath = RegistrationDraft::find($draftId)->files['team_documents']['surat_mandat']['staged_path'];
    expect(Storage::disk('local')->exists($stagedPath))->toBeTrue();

    $newCategory = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Basket U-20',
        'gender' => 'male',
        'max_teams' => 16,
        'registration_fee' => 500000.00,
    ]);

    $updateResponse = $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draftId]), [
            'revision' => 1,
            'event_category_id' => $newCategory->id,
        ]);

    $updateResponse->assertOk()
        ->assertJsonPath('draft.event_category_id', $newCategory->id)
        ->assertJsonPath('draft.files.team_documents', [])
        ->assertJsonPath('draft.payload.members', []);

    // Old staged file was deleted on disk
    expect(Storage::disk('local')->exists($stagedPath))->toBeFalse();
});

test('draft multipart payload JSON string merges allowlisted fields and preserves empty members exactly', function (): void {
    $payloadJson = json_encode([
        'team_name' => 'Tim Multipart JSON',
        'contact_person' => 'Budi JSON',
        'contact_phone' => '081234567890',
        'notes' => '',
        'members' => [],
        'user_id' => 9999, // should be ignored
        'token' => 'illegal', // should be ignored
    ]);

    $response = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'payload' => $payloadJson,
        ], ['Accept' => 'application/json']);

    $response->assertStatus(201);
    $data = $response->json('draft');

    expect($data['payload']['team_name'])->toBe('Tim Multipart JSON')
        ->and($data['payload']['notes'])->toBe('')
        ->and($data['payload']['members'])->toBe([])
        ->and($data['payload'])->not->toHaveKey('user_id')
        ->and($data['payload'])->not->toHaveKey('token');
});

test('draft multipart payload with invalid JSON returns 422 not 500', function (): void {
    $response = $this->actingAs($this->manager)
        ->post(route('panel.registration-drafts.store', $this->event->slug), [
            'payload' => 'not-a-valid-json{',
        ], ['Accept' => 'application/json']);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('payload');
});

test('creating second draft in category with max_teams_per_entity 1 is rejected', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Single Team Category',
        'max_teams' => 10,
        'max_teams_per_entity' => 1,
    ]);

    // 1st draft in category succeeds
    $res1 = $this->actingAs($this->manager)
        ->postJson(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'team_name' => 'Tim Pertama',
        ]);
    $res1->assertStatus(201);

    // 2nd draft in same category fails
    $res2 = $this->actingAs($this->manager)
        ->postJson(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $category->id,
            'team_name' => 'Tim Kedua',
        ]);
    $res2->assertStatus(422)
        ->assertJsonValidationErrors('event_category_id');

    // updating self in same category succeeds
    $draftId = $res1->json('draft.id');
    $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draftId]), [
            'event_category_id' => $category->id,
            'revision' => 1,
            'team_name' => 'Tim Pertama Diedit',
        ])
        ->assertStatus(200);

    // draft in another category cannot switch to full category
    $category2 = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Other Category',
        'max_teams' => 10,
        'max_teams_per_entity' => 1,
    ]);
    $resDraft2 = $this->actingAs($this->manager)
        ->postJson(route('panel.registration-drafts.store', $this->event->slug), [
            'event_category_id' => $category2->id,
            'team_name' => 'Tim Kategori 2',
        ]);
    $resDraft2->assertStatus(201);
    $draft2Id = $resDraft2->json('draft.id');

    $this->actingAs($this->manager)
        ->putJson(route('panel.registration-drafts.update', ['slug' => $this->event->slug, 'draft' => $draft2Id]), [
            'event_category_id' => $category->id,
            'revision' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('event_category_id');
});
