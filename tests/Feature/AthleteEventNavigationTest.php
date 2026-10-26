<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->athlete = User::factory()->create(['is_active' => true]);
    $this->athlete->assignRole('athlete');
    $this->community = Community::factory()->create(['is_active' => true, 'status' => 'verified']);
    CommunityAthlete::factory()->create(['community_id' => $this->community->id, 'user_id' => $this->athlete->id, 'status' => 'active', 'left_at' => null]);
    $this->event = Event::factory()->create(['status' => 'published', 'registration_open' => true]);
    $this->category = EventCategory::factory()->create(['event_id' => $this->event->id, 'required_documents' => []]);
});

test('athlete catalog opens event instead of pushing community creation', function (): void {
    $this->actingAs($this->athlete)->get('/panel/events')->assertOk()
        ->assertSee('Turnamen Terbuka')
        ->assertSee('href="'.route('events.show', $this->event->slug).'"', false)
        ->assertSee('Saya Ingin Ikut')
        ->assertDontSee('Buat Komunitas untuk Mendaftar')
        ->assertDontSee('Anda belum memiliki komunitas terdaftar')
        ->assertDontSee('Pendaftaran Turnamen Basket');
    $this->get(route('panel.community-events.create', $this->event->slug))
        ->assertRedirect(route('panel.community.create'));
});

test('athlete already selected sees own team not another interest action', function (): void {
    RegistrationDraft::create(['user_id' => User::factory()->create()->id, 'community_id' => $this->community->id, 'event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'payload' => ['team_name' => 'Tim Sendiri', 'members' => [['user_id' => $this->athlete->id]]], 'revision' => 1, 'expires_at' => now()->addWeek()]);
    $this->actingAs($this->athlete)->get('/panel/events')->assertOk()
        ->assertSee('Lihat Tim Saya')
        ->assertDontSee('Saya Ingin Ikut');
});

test('athlete manager retains team registration without turning membership into authority', function (): void {
    CommunityOfficial::create(['community_id' => $this->community->id, 'user_id' => $this->athlete->id, 'position' => 'Pengurus', 'status' => 'active', 'started_at' => now()]);
    $this->actingAs($this->athlete)->get('/panel/events')->assertOk()->assertSee('Daftarkan Tim');
});

test('sidebar counts registrations needing upload not files or pending reviews', function (): void {
    Storage::fake('local');
    $codes = ['nav_photo', 'nav_card'];
    foreach ($codes as $code) {
        DocumentType::create(['code' => $code, 'name' => $code, 'scope' => 'athlete', 'is_active' => true]);
    }
    $this->category->update(['required_documents' => $codes]);
    RegistrationDraft::create(['user_id' => User::factory()->create()->id, 'community_id' => $this->community->id, 'event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'payload' => ['members' => [['user_id' => $this->athlete->id]]], 'revision' => 1, 'expires_at' => now()->addWeek()]);
    $this->actingAs($this->athlete)->get('/panel/my-requests')->assertOk()
        ->assertSee('data-athlete-action-count="1"', false);
    foreach ($codes as $code) {
        Document::create(['documentable_type' => User::class, 'documentable_id' => $this->athlete->id, 'category' => $code, 'name' => $code, 'file_path' => 'private/nav-'.$code.'.pdf', 'file_size' => 10, 'mime_type' => 'application/pdf', 'status' => 'pending', 'is_public' => false, 'uploaded_by' => $this->athlete->id]);
        Storage::disk('local')->put('private/nav-'.$code.'.pdf', '%PDF-test');
    }
    $this->get('/panel/my-requests')->assertOk()->assertDontSee('data-athlete-action-count=', false);
    Document::where('documentable_id', $this->athlete->id)->where('category', $codes[0])->update(['status' => 'rejected']);
    $this->get('/my-events')->assertOk()->assertSee('data-athlete-action-count="1"', false);
});
