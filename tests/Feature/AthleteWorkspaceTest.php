<?php

declare(strict_types=1);

use App\Contracts\AthleteDocumentVault;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\AthleteEventWorkspace;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->athlete = User::factory()->create(['is_active' => true]);
    $this->athlete->assignRole('athlete');
});

test('athlete sees own communities without manager access or other memberships', function (): void {
    $own = Community::factory()->create(['is_active' => true, 'name' => 'Komunitas Milik Atlet']);
    $other = Community::factory()->create(['is_active' => true, 'name' => 'Komunitas Rahasia Lain']);
    CommunityAthlete::factory()->create(['community_id' => $own->id, 'user_id' => $this->athlete->id, 'status' => 'active', 'left_at' => null]);
    CommunityAthlete::factory()->create(['community_id' => $other->id]);
    $this->actingAs($this->athlete)->get('/panel/my-communities')->assertOk()
        ->assertSee($own->name)->assertDontSee($other->name)->assertSee('Turnamen Saya');
});

test('athlete uploads only category-required own private documents', function (): void {
    Storage::fake('local');
    $event = Event::factory()->create(['status' => 'published']);
    $code = 'athlete_workspace_identity';
    DocumentType::create(['code' => $code, 'name' => 'Berkas Atlet Pribadi', 'scope' => 'athlete', 'is_active' => true]);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'required_documents' => [$code]]);
    $draft = RegistrationDraft::create(['user_id' => User::factory()->create()->id, 'community_id' => Community::factory()->create()->id, 'event_id' => $event->id, 'event_category_id' => $category->id, 'payload' => ['team_name' => 'Tim Atlet', 'members' => [['user_id' => $this->athlete->id]]], 'revision' => 1, 'expires_at' => now()->addWeek()]);
    $url = route('my-events.documents.store', ['kind' => 'draft', 'id' => $draft->id, 'code' => $code]);
    $file = fn () => UploadedFile::fake()->createWithContent('identity.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    $workspace = app(AthleteEventWorkspace::class);
    expect($workspace->actionSummary((int) $this->athlete->id)['action_count'])->toBe(1);
    $workspace->uploadDocument((int) $this->athlete->id, 'draft', $draft->id, $code, $file());
    expect($workspace->actionSummary((int) $this->athlete->id)['action_count'])->toBe(0);
    $this->actingAs($this->athlete)->get('/my-events')->assertOk()->assertSee('Berkas Atlet Pribadi')->assertSee($url, false);
    $this->post($url, ['document' => $file()])->assertRedirect(route('my-events.index'))->assertSessionHasNoErrors();
    $document = Document::where('documentable_id', $this->athlete->id)->where('category', $code)->firstOrFail();
    expect($document->documentable_type)->toBe(User::class)->and($document->is_public)->toBeFalse()->and($document->status)->toBe('pending');
    Storage::disk('local')->assertExists($document->file_path);
    $this->postJson($url, ['document' => UploadedFile::fake()->create('fake.pdf', 1, 'text/plain')])->assertUnprocessable();
    $this->postJson(route('my-events.documents.store', ['kind' => 'draft', 'id' => $draft->id, 'code' => 'not-required']), ['document' => $file()])->assertUnprocessable();
    $stranger = User::factory()->create(['is_active' => true]);
    $this->actingAs($stranger)->post($url, ['document' => $file()])->assertForbidden();
    $this->get('/my-events')->assertOk()->assertDontSee('Tim Atlet')->assertDontSee($url, false);
    $draft->update(['expires_at' => now()->subDay()]);
    $this->actingAs($this->athlete)->post($url, ['document' => $file()])->assertUnprocessable();
});

test('workspace scans encrypted rosters in batches without hydrating unrelated event relations', function (): void {
    $event = Event::factory()->create(['status' => 'published']);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'required_documents' => []]);
    $base = ['user_id' => User::factory()->create()->id, 'community_id' => Community::factory()->create()->id, 'event_id' => $event->id, 'event_category_id' => $category->id, 'revision' => 1, 'expires_at' => now()->addWeek()];
    $draft = RegistrationDraft::create($base + ['payload' => ['members' => [['user_id' => (string) $this->athlete->id]]]]);
    $otherEvent = Event::factory()->create(['status' => 'published']);
    $otherCategory = EventCategory::factory()->create(['event_id' => $otherEvent->id, 'required_documents' => []]);
    $unrelated = array_replace($base, ['event_id' => $otherEvent->id, 'event_category_id' => $otherCategory->id, 'payload' => ['members' => []]]);
    foreach (range(1, 101) as $index) {
        RegistrationDraft::create($unrelated);
    }
    RegistrationDraft::create($base + ['payload' => ['members' => [['user_id' => $this->athlete->id]], '_submitted' => true]]);
    $expired = $base;
    $expired['expires_at'] = now()->subDay();
    RegistrationDraft::create($expired + ['payload' => ['members' => [['user_id' => $this->athlete->id]]]]);
    $hiddenEvent = Event::factory()->create(['status' => 'draft']);
    RegistrationDraft::create(array_replace($base, ['event_id' => $hiddenEvent->id, 'payload' => ['members' => [['user_id' => $this->athlete->id]]]]));
    $eventLoads = [];
    Event::retrieved(function (Event $event) use (&$eventLoads): void {
        $eventLoads[] = $event->id;
    });
    DB::enableQueryLog();
    try {
        $data = app(AthleteEventWorkspace::class)->getWorkspaceData($this->athlete->id);
        $draftQueries = collect(DB::getQueryLog())
            ->pluck('query')->filter(fn (string $sql): bool => str_contains($sql, 'from `event_registration_drafts`'));
        expect($data['drafts']->modelKeys())->toBe([$draft->id])
            ->and($eventLoads)->toBe([$event->id])
            ->and($draftQueries->count())->toBeGreaterThan(1);
        foreach ($draftQueries as $sql) {
            expect(strtolower($sql))->toContain('limit');
        }
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('workspace photo accepts images up to 2 MB and rejects invalid replacement with notification', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->athlete->update(['image' => null]);
    DocumentType::firstOrCreate(['code' => 'photo'], ['name' => 'Pasfoto Formal Atlet', 'scope' => 'athlete', 'is_active' => true]);
    $event = Event::factory()->create(['status' => 'published']);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'required_documents' => ['photo']]);
    $draft = RegistrationDraft::create(['user_id' => User::factory()->create()->id, 'community_id' => Community::factory()->create()->id, 'event_id' => $event->id, 'event_category_id' => $category->id, 'payload' => ['members' => [['user_id' => $this->athlete->id]]], 'revision' => 1, 'expires_at' => now()->addWeek()]);
    $url = route('my-events.documents.store', ['kind' => 'draft', 'id' => $draft->id, 'code' => 'photo']);
    $this->actingAs($this->athlete)->get('/my-events')->assertOk()->assertSee('Maks. 2 MB')->assertSee('accept="image/png,image/jpeg"', false);
    $avatar = null;
    foreach (['png', 'jpg'] as $extension) {
        $this->post($url, ['document' => UploadedFile::fake()->image('photo.'.$extension)->size(2048)])
            ->assertRedirect(route('my-events.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
        $image = $this->athlete->fresh()->image;
        expect($image)->not->toBeEmpty();
        Storage::disk('public')->assertExists($image);
        if ($avatar !== null) {
            expect($image)->toBe($avatar);
        }
        $avatar = $image;
    }
    $document = Document::where('documentable_id', $this->athlete->id)->where('category', 'photo')->firstOrFail();
    $before = $document->getAttributes();
    $this->from('/my-events')->post($url, ['document' => UploadedFile::fake()->image('large.png')->size(2049)])
        ->assertRedirect('/my-events')->assertSessionHasErrors(['document' => 'Ukuran berkas maksimal 2 MB. Pilih berkas yang lebih kecil.']);
    $this->get('/my-events')->assertOk()->assertSee('Ukuran berkas maksimal 2 MB. Pilih berkas yang lebih kecil.');
    $this->postJson($url, ['document' => UploadedFile::fake()->createWithContent('photo.pdf', "%PDF-1.4\n%%EOF")])
        ->assertUnprocessable()->assertJsonValidationErrors('document');
    $spoof = UploadedFile::fake()->createWithContent('photo.png', 'not an image');
    $this->postJson($url, ['document' => new UploadedFile($spoof->getPathname(), 'photo.png', null, null, true)])
        ->assertUnprocessable()->assertJsonValidationErrors('document');
    expect($document->fresh()->getAttributes())->toBe($before);
    expect($this->athlete->fresh()->image)->toBe($avatar);
    expect(Storage::disk('public')->allFiles('avatars'))->toHaveCount(1);
    expect($document->file_path)->not->toBe($avatar);
    Storage::disk('local')->assertExists($document->file_path);
});

test('workspace rejects documents over 2 MB for both registration kinds', function (string $kind): void {
    $url = route('my-events.documents.store', ['kind' => $kind, 'id' => '123', 'code' => 'identity']);
    $this->actingAs($this->athlete)->postJson($url, ['document' => UploadedFile::fake()->create('identity.pdf', 2049, 'application/pdf')])
        ->assertUnprocessable()->assertJsonValidationErrors('document');
})->with(['draft', 'participant']);

test('pasfoto preserves existing profile image and non-photo uploads never fill it', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $vault = app(AthleteDocumentVault::class);
    $this->athlete->update(['image' => 'avatars/existing.png']);
    Storage::disk('public')->put('avatars/existing.png', 'existing photo');
    $vault->upload((int) $this->athlete->id, 'photo', UploadedFile::fake()->image('new.png'));
    expect($this->athlete->fresh()->image)->toBe('avatars/existing.png');
    expect(Storage::disk('public')->get('avatars/existing.png'))->toBe('existing photo');
    expect(Storage::disk('public')->allFiles('avatars'))->toHaveCount(1);
    $this->athlete->update(['image' => null]);
    $vault->upload((int) $this->athlete->id, 'identity', UploadedFile::fake()->image('identity.png'));
    expect($this->athlete->fresh()->image)->toBeNull();
});

test('failed profile image storage leaves previous private pasfoto intact', function (): void {
    $local = Storage::fake('local');
    $public = Storage::fake('public');
    $this->athlete->update(['image' => null]);
    $old = Document::create(['documentable_type' => User::class, 'documentable_id' => $this->athlete->id, 'category' => 'photo', 'name' => 'Old photo', 'file_path' => 'athlete_documents/old.png', 'status' => 'verified', 'is_public' => false]);
    $local->put($old->file_path, 'old private photo');
    $failingPublic = Mockery::mock(FilesystemAdapter::class);
    $failingPublic->shouldReceive('putFileAs')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($local);
    Storage::shouldReceive('disk')->with('public')->andReturn($failingPublic);
    expect(fn () => app(AthleteDocumentVault::class)->upload((int) $this->athlete->id, 'photo', UploadedFile::fake()->image('new.png')))
        ->toThrow(RuntimeException::class, 'Gagal menyimpan foto profil.');
    expect($this->athlete->fresh()->image)->toBeNull();
    expect($old->fresh()->status)->toBe('verified');
    expect($local->allFiles('athlete_documents'))->toBe(['athlete_documents/old.png']);
    expect($public->allFiles('avatars'))->toBe([]);
});

test('athlete sees draft tournament and own document task', function (): void {
    $event = Event::factory()->create(['status' => 'published', 'title' => 'Turnamen Atlet Mendatang']);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'required_documents' => []]);
    RegistrationDraft::create(['user_id' => User::factory()->create()->id, 'community_id' => Community::factory()->create()->id, 'event_id' => $event->id, 'event_category_id' => $category->id, 'payload' => ['team_name' => 'Tim Atlet', 'members' => [['user_id' => $this->athlete->id]]], 'revision' => 1, 'expires_at' => now()->addWeek()]);
    $this->actingAs($this->athlete)->get('/my-events')->assertOk()
        ->assertSee($event->title)->assertSee('Tim Atlet')->assertSee('Pendaftaran berjalan');
});
