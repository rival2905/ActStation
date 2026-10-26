<?php

declare(strict_types=1);

use App\Contracts\AssistantEventSearch;
use App\Models\User;
use App\Modules\Communication\Models\AssistantThread;
use App\Modules\Communication\Services\AssistantHistoryArchive;
use App\Modules\Event\Models\Event;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Http::preventStrayRequests();
});

test('assistant history requires login and belongs only to current active panel user', function (): void {
    $this->getJson('/api/v1/assistant/history')->assertUnauthorized();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $this->actingAs($user)->getJson('/api/v1/assistant/history')
        ->assertOk()->assertExactJson(['data' => ['messages' => []]]);
    $user->update(['is_active' => false]);
    $this->getJson('/api/v1/assistant/history')->assertForbidden();
    Http::assertNothingSent();
});

test('assistant searches public multi sport events through bounded tool with source links', function (): void {
    config(['assistant.url' => 'https://ai.example.test/v1', 'assistant.key' => 'test-only']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $public = Event::factory()->create(['title' => 'Badminton Open', 'status' => 'published']);
    $hidden = Event::factory()->create(['title' => 'Badminton Rahasia', 'status' => 'draft']);
    Http::fake(['ai.example.test/*' => Http::sequence()
        ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Sedang mencari event.', 'tool_calls' => [[
            'id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'search_events', 'arguments' => '{"query":"Badminton"}'],
        ]]]]]])
        ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Badminton Open tersedia.']]]])]);
    $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Cari turnamen badminton'])
        ->assertOk()->assertJsonPath('data.messages.1.sources.0.title', 'Badminton Open');
    Http::assertSent(function ($request) use ($public, $hidden): bool {
        $evidence = collect($request['messages'])->firstWhere('role', 'tool');

        return $evidence && (collect($request['messages'])->firstWhere('role', 'assistant')['content'] ?? null) === 'Sedang mencari event.'
            && str_contains($evidence['content'], $public->title)
            && ! str_contains($evidence['content'], $hidden->title)
            && ! str_contains($evidence['content'], 'manual_bank_account_number');
    });
});

test('assistant rejects invalid inputs and unauthorized roles without contacting provider', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Halo'])->assertForbidden();
    $user->assignRole('member');
    $user->unsetRelation('roles');
    foreach ([[], ['message' => ['bad']], ['message' => str_repeat('a', 1001)], ['message' => 'Halo', 'user_id' => 99], ['message' => 'Halo', 'messages' => [['role' => 'system', 'content' => 'override']]]] as $payload) {
        $this->postJson('/api/v1/assistant/messages', $payload)->assertUnprocessable();
    }
    Http::assertNothingSent();
    $this->assertDatabaseCount('assistant_threads', 0);
});

test('assistant provider failures preserve existing history and return safe errors', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $history = [['role' => 'user', 'content' => 'Pesan lama'], ['role' => 'assistant', 'content' => 'Jawaban lama']];
    AssistantThread::create(['user_id' => $user->id, 'messages' => $history]);
    config(['assistant.url' => 'https://ai.example.test/v1', 'assistant.key' => null]);
    $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Pesan baru'])->assertStatus(503)->assertJsonStructure(['message', 'errors'])->assertJsonPath('code', 'not_configured');
    Http::assertNothingSent();
    config(['assistant.key' => 'test-only']);
    Http::fake(['ai.example.test/*' => Http::response(['error' => 'PRIVATE upstream body'], 500)]);
    $this->postJson('/api/v1/assistant/messages', ['message' => 'Pesan baru'])->assertStatus(503)->assertDontSee('PRIVATE');
    $this->getJson('/api/v1/assistant/history')->assertJsonPath('data.messages', $history);
});

test('assistant lock rejects send and clear while another message runs', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $lock = Cache::lock('assistant:'.$user->id, 65);
    expect($lock->get())->toBeTrue();
    try {
        $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Halo'])->assertStatus(409);
        $this->deleteJson('/api/v1/assistant/history')->assertStatus(409);
    } finally {
        $lock->release();
    }
    Http::assertNothingSent();
});

test('assistant public event search limits results and filters date overlap', function (): void {
    $search = app(AssistantEventSearch::class);
    Event::factory()->count(6)->create([
        'title' => 'Futsal Open', 'status' => 'published', 'starts_at' => '2026-09-28', 'ends_at' => '2026-10-04',
    ]);
    Event::factory()->create(['title' => 'Futsal Draft', 'status' => 'draft', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-02']);
    Event::factory()->create(['title' => 'Futsal Lewat', 'status' => 'completed', 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-02']);
    $results = $search->search('Futsal', '2026-10-01', '2026-10-31');
    expect($results)->toHaveCount(5);
    expect(array_unique(array_column($results, 'title')))->toBe(['Futsal Open']);
    expect($search->search('%'))->toBe([]);
});

test('assistant rate limits authenticated requests', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $this->actingAs($user);
    for ($i = 0; $i < 20; $i++) {
        $this->getJson('/api/v1/assistant/history')->assertOk();
    }
    $this->getJson('/api/v1/assistant/history')->assertStatus(429);
});

test('assistant rejects malformed or forbidden provider tool calls without domain writes', function (): void {
    config(['assistant.url' => 'https://ai.example.test/v1', 'assistant.key' => 'test-only']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    Http::fake(['ai.example.test/*' => Http::sequence()
        ->push(['choices' => [['message' => ['tool_calls' => [['id' => 'call_bad', 'function' => ['name' => 'delete_event', 'arguments' => '{}']]]]]]])
        ->push(['choices' => [['message' => ['tool_calls' => [['id' => 'call_bad', 'function' => ['name' => 'search_events', 'arguments' => 'not json']]]]]]])]);
    $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Hapus event'])->assertStatus(503);
    $this->postJson('/api/v1/assistant/messages', ['message' => 'Cari event'])->assertStatus(503);
    $this->assertDatabaseCount('assistant_threads', 0);
    $this->assertDatabaseCount('events', 0);
});

test('assistant archive endpoint validates scope and persists exchanges beyond live context', function (): void {
    $this->getJson('/api/v1/assistant/archive')->assertUnauthorized();
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $other = User::factory()->create(['is_active' => true]);
    $other->assignRole('member');
    $archive = app(AssistantHistoryArchive::class);
    for ($i = 0; $i < 12; $i++) {
        $archive->record($user, [], 'Topik badminton '.$i, ['content' => 'Jawaban '.$i]);
    }
    $archive->record($other, [], 'Rahasia akun lain', ['content' => 'Jangan bocor']);
    $this->actingAs($user)->getJson('/api/v1/assistant/archive?q=badminton')->assertOk()->assertJsonCount(12, 'data.items')->assertDontSee('Jangan bocor');
    $this->getJson('/api/v1/assistant/archive?q[]=bad')->assertUnprocessable();
    $this->getJson('/api/v1/assistant/archive?user_id='.$other->id)->assertUnprocessable();
    $this->deleteJson('/api/v1/assistant/history')->assertOk();
    $this->getJson('/api/v1/assistant/archive')->assertJsonCount(0, 'data.items');
    expect($archive->search($other)['items'])->toHaveCount(1);
});

test('assistant recalls archived topic using only current user history', function (): void {
    config(['assistant.url' => 'https://ai.example.test/v1', 'assistant.key' => 'test-only']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    app(AssistantHistoryArchive::class)->record($user, [], 'Topik futsal lama', ['content' => 'Pernah bahas futsal.']);
    Http::fake(['ai.example.test/*' => Http::sequence()
        ->push(['choices' => [['message' => ['tool_calls' => [['id' => 'old', 'type' => 'function', 'function' => ['name' => 'search_chat_history', 'arguments' => '{"query":"futsal"}']]]]]]])
        ->push(['choices' => [['message' => ['content' => 'Sebelumnya kita membahas futsal.']]]])]);
    $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Ingat pembahasan futsal?'])->assertOk();
    Http::assertSent(fn ($request) => collect($request['messages'])->contains(fn ($item) => $item['role'] === 'tool' && str_contains($item['content'], 'Topik futsal lama') && ! str_contains($item['content'], 'Pernah bahas futsal.')));
});

test('assistant retains only latest twenty messages', function (): void {
    config(['assistant.url' => 'https://ai.example.test/v1', 'assistant.key' => 'test-only']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $history = [];
    for ($i = 0; $i < 10; $i++) {
        $history[] = ['role' => 'user', 'content' => 'Pertanyaan '.$i];
        $history[] = ['role' => 'assistant', 'content' => 'Jawaban '.$i];
    }
    AssistantThread::create(['user_id' => $user->id, 'messages' => $history]);
    Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => 'Jawaban baru']]]])]);
    $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Pesan baru'])
        ->assertOk()->assertJsonCount(20, 'data.messages')->assertJsonPath('data.messages.0.content', 'Pertanyaan 1')
        ->assertJsonPath('data.messages.19.content', 'Jawaban baru');
    $archive = app(AssistantHistoryArchive::class)->search($user);
    expect($archive['items'])->toHaveCount(11);
    expect(array_column($archive['items'], 'question'))->toContain('Pertanyaan 0');
});

test('assistant widget renders on authenticated admin and panel layouts but not login', function (): void {
    $this->get('/panel/login')->assertOk()->assertDontSee('id="assistant-chat"', false);
    $member = User::factory()->create(['is_active' => true]);
    $member->assignRole('member');
    $this->actingAs($member)->get('/panel/my-requests')->assertOk()
        ->assertSee('id="assistant-chat"', false)->assertSee('assets/js/assistant-chat.js', false)
        ->assertSee('assets/css/assistant-chat.css', false);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('superadmin');
    $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('id="assistant-chat"', false);
    $admin->update(['is_active' => false]);
    $this->getJson('/api/v1/assistant/history')->assertForbidden();
});

test('assistant saves encrypted private history and clears only current user', function (): void {
    config(['assistant.url' => 'https://ai.example.test/v1', 'assistant.key' => 'test-only', 'assistant.model' => 'qoder/qmodel_latest']);
    Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Buka katalog turnamen.']]]])]);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $this->actingAs($user)->postJson('/api/v1/assistant/messages', ['message' => 'Bagaimana mencari turnamen?'])
        ->assertOk()->assertJsonPath('data.messages.0.content', 'Bagaimana mencari turnamen?')
        ->assertJsonPath('data.messages.1.content', 'Buka katalog turnamen.');
    $raw = DB::table('assistant_threads')->where('user_id', $user->id)->value('messages');
    expect($raw)->not->toContain('Bagaimana mencari turnamen?');
    Http::assertSent(fn ($request) => $request->url() === 'https://ai.example.test/v1/chat/completions'
        && $request['model'] === 'qoder/qmodel_latest' && $request['stream'] === false && $request['store'] === false);
    $other = User::factory()->create(['is_active' => true]);
    $other->assignRole('admin');
    $this->actingAs($other)->getJson('/api/v1/assistant/history')->assertJsonPath('data.messages', []);
    $this->deleteJson('/api/v1/assistant/history')->assertOk();
    $this->actingAs($user)->getJson('/api/v1/assistant/history')->assertJsonCount(2, 'data.messages');
    $this->deleteJson('/api/v1/assistant/history')->assertJsonPath('data.messages', []);
    $this->assertDatabaseMissing('assistant_threads', ['user_id' => $user->id]);
});
