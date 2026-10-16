<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
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

test('athlete sees draft tournament and own document task', function (): void {
    $event = Event::factory()->create(['status' => 'published', 'title' => 'Turnamen Atlet Mendatang']);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'required_documents' => []]);
    RegistrationDraft::create(['user_id' => User::factory()->create()->id, 'community_id' => Community::factory()->create()->id, 'event_id' => $event->id, 'event_category_id' => $category->id, 'payload' => ['team_name' => 'Tim Atlet', 'members' => [['user_id' => $this->athlete->id]]], 'revision' => 1, 'expires_at' => now()->addWeek()]);
    $this->actingAs($this->athlete)->get('/my-events')->assertOk()
        ->assertSee($event->title)->assertSee('Tim Atlet')->assertSee('Pendaftaran berjalan');
});
