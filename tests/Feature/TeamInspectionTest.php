<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\DocumentType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Storage::fake('local');
    foreach (['organizer' => 'external', 'staff' => 'internal'] as $name => $panel) {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['panel_access' => $panel]);
    }
    foreach (['event-participant.view', 'event-participant.confirm'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $this->organizer = User::factory()->create(['is_active' => true]);
    $this->organizer->assignRole('organizer');
    $this->event = Event::factory()->create(['user_id' => $this->organizer->id]);
    DocumentType::firstOrCreate(['code' => 'inspection_identity'], ['name' => 'Identitas Anggota', 'scope' => 'athlete', 'is_active' => true]);
    $this->category = EventCategory::factory()->create(['event_id' => $this->event->id, 'required_documents' => ['inspection_identity']]);
    $this->athlete = User::factory()->create(['name' => 'Atlet Inspeksi']);
    $this->participant = EventParticipant::factory()->create(['event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'status' => 'registered']);
    $this->participant->members()->create(['user_id' => $this->athlete->id, 'team_role' => 'player']);
    Storage::disk('local')->put('inspection/identity.pdf', '%PDF-1.4 inspection');
    $this->doc = Document::create(['documentable_type' => User::class, 'documentable_id' => $this->athlete->id, 'category' => 'inspection_identity', 'name' => 'Identitas Anggota', 'file_path' => 'inspection/identity.pdf', 'status' => 'pending', 'is_public' => false]);
    $this->url = '/panel/organizer/events/'.$this->event->id.'/team-inspections/participant/'.$this->participant->id;
});

test('shared inspection renders roster and allows current document review without approving registration', function (): void {
    $secondAthlete = User::factory()->create(['name' => 'Atlet Kedua']);
    $this->participant->members()->create(['user_id' => $secondAthlete->id, 'team_role' => 'player']);
    $response = $this->actingAs($this->organizer)->get($this->url)->assertOk()->assertSee('Atlet Inspeksi')->assertSee('Verifikasi dokumen')->assertSee('Atlet 1')->assertSee('Atlet 2');
    $doc = $response->viewData('members')[0]['documents'][0];
    $this->post($doc['review_url'], ['status' => 'verified', 'version' => $doc['version']])->assertRedirect($this->url.'#inspection-document-'.$this->athlete->id.'-inspection_identity');
    expect($this->doc->fresh()->status)->toBe('verified');
    expect((int) $this->doc->fresh()->verified_by)->toBe($this->organizer->id);
    expect($this->participant->fresh()->status)->toBe('registered');
});

test('rejection explains required reason and saves a rejection with reason', function (): void {
    $row = $this->actingAs($this->organizer)->get($this->url)->viewData('members')[0]['documents'][0];
    $this->post($row['review_url'], ['status' => 'rejected', 'version' => $row['version']])
        ->assertSessionHasErrors(['notes' => 'Isi alasan penolakan dokumen terlebih dahulu.']);
    $this->post($row['review_url'], ['status' => 'rejected', 'version' => $row['version'], 'notes' => 'Berkas tidak terbaca.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->doc->fresh()->status)->toBe('rejected');
    expect($this->doc->fresh()->notes)->toBe('Berkas tidak terbaca.');
});

test('organizer verifies every required document for one athlete atomically', function (): void {
    DocumentType::create(['code' => 'inspection_second', 'name' => 'Berkas Kedua', 'scope' => 'athlete']);
    $this->category->update(['required_documents' => ['inspection_identity', 'inspection_second']]);
    $second = $this->doc->replicate();
    $second->category = 'inspection_second';
    $second->save();
    $response = $this->actingAs($this->organizer)->get($this->url)->assertOk()->assertSee('Verifikasi semua dokumen');
    $documents = collect($response->viewData('members')[0]['documents'])->map(fn ($row) => ['code' => $row['code'], 'version' => $row['version']])->all();
    $url = $this->url.'/documents/'.$this->athlete->id.'/verify-all';
    $stale = $documents;
    $stale[1]['version'] = str_repeat('0', 64);
    $this->post($url, ['documents' => $stale])->assertStatus(409);
    expect($this->doc->fresh()->status)->toBe('pending');
    $this->post($url, ['documents' => [$documents[0]]])->assertStatus(422);
    $this->post($url, ['documents' => $documents])->assertRedirect($this->url.'#inspection-athlete-'.$this->athlete->id)->assertSessionHasNoErrors();
    expect($this->doc->fresh()->status)->toBe('verified');
    expect($second->fresh()->status)->toBe('verified');
    expect($this->participant->fresh()->status)->toBe('registered');
});

test('athlete bulk review rejects missing files and unauthorized targets', function (): void {
    $row = $this->actingAs($this->organizer)->get($this->url)->viewData('members')[0]['documents'][0];
    $payload = ['documents' => [['code' => $row['code'], 'version' => $row['version']]]];
    $url = $this->url.'/documents/'.$this->athlete->id.'/verify-all';
    $other = User::factory()->create(['is_active' => true]);
    $other->assignRole('organizer');
    $this->actingAs($other)->post($url, $payload)->assertForbidden();
    $this->actingAs($this->organizer)->post($this->url.'/documents/'.$other->id.'/verify-all', $payload)->assertNotFound();
    Storage::disk('local')->delete('inspection/identity.pdf');
    $this->post($url, $payload)->assertNotFound();
    expect($this->doc->fresh()->status)->toBe('pending');
    $this->participant->update(['status' => 'cancelled']);
    $this->post($url, $payload)->assertForbidden();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('staff');
    $admin->givePermissionTo('event-participant.view');
    $this->actingAs($admin)->post('/admin/events/'.$this->event->id.'/team-inspections/participant/'.$this->participant->id.'/documents/'.$this->athlete->id.'/verify-all', $payload)->assertForbidden();
    $draft = RegistrationDraft::create(['event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'community_id' => Community::factory()->create()->id, 'user_id' => $this->organizer->id, 'payload' => ['members' => [['user_id' => $this->athlete->id]]], 'expires_at' => now()->addDay()]);
    $this->actingAs($this->organizer)->post('/panel/organizer/events/'.$this->event->id.'/team-inspections/draft/'.$draft->id.'/documents/'.$this->athlete->id.'/verify-all', $payload)->assertForbidden();
});

test('inspection previews documents inline without changing download behavior', function (): void {
    $response = $this->actingAs($this->organizer)->get($this->url)->assertOk()
        ->assertSee('Lihat semua dokumen')->assertSee('Lihat dokumen')->assertSee('inspection-document-modal')
        ->assertSee('Atlet 1')->assertSee('id="inspection-athlete-'.$this->athlete->id.'"', false)
        ->assertSee('id="inspection-document-'.$this->athlete->id.'-inspection_identity"', false);
    $row = $response->viewData('members')[0]['documents'][0];
    $preview = $this->get($row['download_url'].'?preview=1')->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($preview->headers->get('Content-Disposition'))->toStartWith('inline;');
    expect($preview->headers->get('Cache-Control'))->toContain('no-store');
    expect($this->get($row['download_url'])->headers->get('Content-Disposition'))->toStartWith('attachment;');
    expect($this->doc->fresh()->status)->toBe('pending');
    expect($this->participant->fresh()->status)->toBe('registered');
});

test('restored team draft evidence remains inspectable without widening storage scope', function (): void {
    DocumentType::create(['code' => 'inspection_restored_team', 'name' => 'Surat Tim', 'scope' => 'team', 'is_active' => true]);
    $this->category->update(['required_documents' => ['inspection_restored_team']]);
    $draft = RegistrationDraft::create(['event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'community_id' => Community::factory()->create()->id, 'user_id' => $this->organizer->id, 'payload' => ['team_name' => 'Restored draft'], 'expires_at' => now()->addDay()]);
    $path = 'registration_drafts/'.$draft->id.'/restored/team.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 restored');
    $files = ['team_documents' => ['inspection_restored_team' => ['staged_path' => $path, 'name' => 'Surat.pdf']]];
    $draft->update(['files' => $files]);
    $url = '/panel/organizer/events/'.$this->event->id.'/team-inspections/draft/'.$draft->id;
    $row = $this->actingAs($this->organizer)->get($url)->assertOk()->viewData('documents')[0];
    $this->get($row['download_url'].'?preview=1')->assertOk();
    $files['team_documents']['inspection_restored_team']['staged_path'] = 'registration_drafts/foreign/restored/team.pdf';
    $draft->update(['files' => $files]);
    $this->get($url)->assertNotFound();
});

test('draft details remain read only including forged review and staged files', function (): void {
    $draft = RegistrationDraft::create(['event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'community_id' => Community::factory()->create()->id, 'user_id' => $this->organizer->id, 'payload' => ['team_name' => 'Draft Detail', 'members' => [['user_id' => $this->athlete->id, 'team_role' => 'coach']]], 'expires_at' => now()->addDay()]);
    $path = 'registration_drafts/'.$draft->id.'/athlete/identity.pdf';
    Storage::disk('local')->put($path, '%PDF draft');
    $draft->update(['files' => ['athlete_documents' => [$this->athlete->id => ['inspection_identity' => ['staged_path' => $path, 'name' => 'Draft Identity.pdf']]]]]);
    $url = '/panel/organizer/events/'.$this->event->id.'/team-inspections/draft/'.$draft->id;
    $r = $this->actingAs($this->organizer)->get($url)->assertOk()->assertSee('Draft Detail')->assertSee('Coach')->assertDontSee('name="status"', false);
    $row = $r->viewData('members')[0]['documents'][0];
    $this->get($row['download_url'])->assertOk();
    $this->get($row['download_url'].'?preview=1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->post($url.'/documents/'.$this->athlete->id.'/inspection_identity/review', ['status' => 'verified', 'version' => 'forged'])->assertForbidden();
    expect($this->doc->fresh()->status)->toBe('pending');
    expect($draft->fresh()->participant_id)->toBeNull();
});

test('inspection blocks other organizers outsiders and unrequired documents', function (): void {
    $other = User::factory()->create(['is_active' => true]);
    $other->assignRole('organizer');
    $this->actingAs($other)->get($this->url)->assertForbidden();
    $this->actingAs($this->organizer)->get($this->url.'/documents/'.$other->id.'/inspection_identity/download')->assertNotFound();
    $this->get($this->url.'/documents/'.$this->athlete->id.'/unrelated/download')->assertNotFound();
    $this->actingAs($other)->get($this->url.'/documents/'.$this->athlete->id.'/inspection_identity/download?preview=1')->assertForbidden();
    $this->actingAs($this->organizer)->get($this->url.'/documents/'.$other->id.'/inspection_identity/download?preview=1')->assertNotFound();
    $this->get($this->url.'/documents/'.$this->athlete->id.'/unrelated/download?preview=1')->assertNotFound();
});

test('inspection only embeds safe supported document content', function (string $content, string $type, int $status): void {
    Storage::disk('local')->put('inspection/identity.pdf', $content);
    $url = $this->url.'/documents/'.$this->athlete->id.'/inspection_identity/download';
    $preview = $this->actingAs($this->organizer)->get($url.'?preview=1')->assertStatus($status);
    if ($status === 200) {
        $preview->assertHeader('Content-Type', $type);
        expect($preview->headers->get('Content-Disposition'))->toStartWith('inline;');
    }
    $this->get($url)->assertOk();
})->with([
    'PNG under misleading extension' => [base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/ZioAAAAASUVORK5CYII='), 'image/png', 200],
    'HTML under PDF extension' => ['<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', 'text/html', 415],
    'SVG active content' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml', 415],
]);

test('review rejects stale versions missing files public files and missing rejection notes', function (): void {
    $r = $this->actingAs($this->organizer)->get($this->url)->assertOk();
    $row = $r->viewData('members')[0]['documents'][0];
    $this->post($row['review_url'], ['status' => 'rejected', 'version' => $row['version']])->assertSessionHasErrors('notes');
    Storage::disk('local')->put('inspection/new.pdf', '%PDF replacement');
    $this->doc->update(['file_path' => 'inspection/new.pdf']);
    $this->post($row['review_url'], ['status' => 'verified', 'version' => $row['version']])->assertStatus(409);
    $this->doc->update(['is_public' => true]);
    $this->get($row['download_url'])->assertNotFound();
    $this->get($row['download_url'].'?preview=1')->assertNotFound();
    $this->doc->update(['is_public' => false]);
    Storage::disk('local')->delete('inspection/new.pdf');
    $this->get($row['download_url'])->assertNotFound();
});

test('team and support staff documents share inspection without exposing users outside roster', function (): void {
    DocumentType::create(['code' => 'inspection_mandate', 'name' => 'Mandat Tim', 'scope' => 'team']);
    $this->category->update(['required_documents' => ['inspection_identity', 'inspection_mandate']]);
    Storage::disk('local')->put('inspection/mandate.pdf', '%PDF mandate');
    $teamDoc = Document::create(['documentable_type' => EventParticipant::class, 'documentable_id' => $this->participant->id, 'category' => 'inspection_mandate', 'name' => 'Mandat Tim', 'file_path' => 'inspection/mandate.pdf', 'status' => 'pending', 'is_public' => false]);
    foreach (['coach', 'assistant_coach', 'medical'] as $role) {
        $user = User::factory()->create(['name' => 'Anggota '.$role]);
        $this->participant->members()->create(['user_id' => $user->id, 'team_role' => $role]);
        Document::create(['documentable_type' => User::class, 'documentable_id' => $user->id, 'category' => 'inspection_identity', 'name' => 'Identitas Anggota', 'file_path' => 'inspection/identity.pdf', 'status' => 'pending', 'is_public' => false]);
    }
    $r = $this->actingAs($this->organizer)->get($this->url)->assertOk()->assertSee('Anggota coach')->assertSee('Anggota assistant_coach')->assertSee('Anggota medical');
    expect($r->viewData('members'))->toHaveCount(4);
    foreach ($r->viewData('members') as $member) {
        expect($member['documents'])->toHaveCount(1);
        $this->get($member['documents'][0]['download_url'])->assertOk();
    }
    $row = $r->viewData('documents')[0];
    $this->post($row['review_url'], ['status' => 'rejected', 'version' => $row['version'], 'notes' => 'Tanda tangan belum ada'])->assertRedirect();
    expect($teamDoc->fresh()->status)->toBe('rejected');
});

test('source category and event mismatches cannot expose documents', function (): void {
    $other = Event::factory()->create(['user_id' => $this->organizer->id]);
    $this->actingAs($this->organizer)->get('/panel/organizer/events/'.$other->id.'/team-inspections/participant/'.$this->participant->id)->assertNotFound();
    $category = EventCategory::factory()->create(['event_id' => $other->id]);
    $this->participant->update(['event_category_id' => $category->id]);
    $this->get($this->url)->assertNotFound();
});

test('cancelled registration is read only and admin reviewer can verify documents', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('staff');
    $admin->givePermissionTo(['event-participant.view', 'event-participant.confirm']);
    $url = '/admin/events/'.$this->event->id.'/team-inspections/participant/'.$this->participant->id;
    $r = $this->actingAs($admin)->get($url)->assertOk();
    $row = $r->viewData('members')[0]['documents'][0];
    $this->post($row['review_url'], ['status' => 'verified', 'version' => $row['version']])->assertRedirect();
    $this->participant->update(['status' => 'cancelled']);
    $r = $this->get($url)->assertOk()->assertDontSee('name="status"', false);
    $row = $r->viewData('members')[0]['documents'][0];
    $this->post($row['review_url'], ['status' => 'verified', 'version' => $row['version']])->assertForbidden();
});

test('early draft without selected category still exposes read only team detail', function (): void {
    $draft = RegistrationDraft::create(['event_id' => $this->event->id, 'community_id' => Community::factory()->create()->id, 'user_id' => $this->organizer->id, 'payload' => ['team_name' => 'Draft Awal'], 'expires_at' => now()->addDay()]);
    $this->actingAs($this->organizer)->get('/panel/organizer/events/'.$this->event->id.'/team-inspections/draft/'.$draft->id)->assertOk()->assertSee('Draft Awal')->assertDontSee('name="status"', false);
});

test('draft staged path cannot point outside its own private directory', function (): void {
    $draft = RegistrationDraft::create(['event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'community_id' => Community::factory()->create()->id, 'user_id' => $this->organizer->id, 'payload' => ['members' => [['user_id' => $this->athlete->id]]], 'files' => ['athlete_documents' => [$this->athlete->id => ['inspection_identity' => ['staged_path' => 'inspection/identity.pdf']]]], 'expires_at' => now()->addDay()]);
    $url = '/panel/organizer/events/'.$this->event->id.'/team-inspections/draft/'.$draft->id;
    $this->actingAs($this->organizer)->get($url.'/documents/'.$this->athlete->id.'/inspection_identity/download')->assertNotFound();
    $draft->update(['participant_id' => $this->participant->id, 'files' => []]);
    $this->get($url)->assertNotFound();
});

test('document applicability excludes other entity evidence from inspection and download', function (): void {
    DocumentType::where('code', 'inspection_identity')->update(['applicable_entities' => ['school']]);
    $r = $this->actingAs($this->organizer)->get($this->url)->assertOk();
    expect($r->viewData('members')[0]['documents'])->toBeEmpty();
    $this->get($this->url.'/documents/'.$this->athlete->id.'/inspection_identity/download')->assertNotFound();
});

test('admin detail uses same engine but read permission does not grant review', function (): void {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('staff');
    $admin->givePermissionTo('event-participant.view');
    $url = '/admin/events/'.$this->event->id.'/team-inspections/participant/'.$this->participant->id;
    $r = $this->actingAs($admin)->get($url)->assertOk()->assertSee('Atlet Inspeksi')->assertDontSee('name="status"', false);
    $this->get($r->viewData('members')[0]['documents'][0]['download_url'].'?preview=1')->assertOk();
    $this->post($url.'/documents/'.$this->athlete->id.'/inspection_identity/review', ['status' => 'verified', 'version' => 'forged'])->assertForbidden();
});
