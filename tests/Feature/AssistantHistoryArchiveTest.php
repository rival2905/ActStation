<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Communication\Models\AssistantExchange;
use App\Modules\Communication\Models\AssistantThread;
use App\Modules\Communication\Services\AssistantHistoryArchive;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('member');
});

test('archive imports existing pairs once and stores encrypted payload', function (): void {
    $archive = app(AssistantHistoryArchive::class);
    $previous = [['role' => 'user', 'content' => 'Topik lama'], ['role' => 'assistant', 'content' => 'Jawaban lama']];
    $archive->record($this->user, $previous, 'Topik baru', ['content' => 'Jawaban baru', 'sources' => []]);
    $archive->record($this->user, $previous, 'Berikutnya', ['content' => 'Lanjut']);
    expect($archive->search($this->user)['items'])->toHaveCount(3);
    expect(DB::table('assistant_exchanges')->value('payload'))->not->toContain('Topik lama');
    expect($archive->search($this->user, 'TOPIK lama')['items'][0]['answer'])->toBe('Jawaban lama');
});

test('archive scopes pages and searches to owner without deleting older rows', function (): void {
    $archive = app(AssistantHistoryArchive::class);
    $other = User::factory()->create(['is_active' => true]);
    $other->assignRole('member');
    $archive->record($other, [], 'Rahasia', ['content' => 'Jangan bocor']);
    for ($i = 0; $i < 22; $i++) {
        $archive->record($this->user, [], 'Badminton topik '.$i, ['content' => 'Jawab '.$i]);
    }
    $first = $archive->search($this->user);
    expect($first['items'])->toHaveCount(20);
    expect($first['has_more'])->toBeTrue();
    expect($archive->search($this->user, '', 2)['items'])->toHaveCount(2);
    expect($archive->search($this->user, 'badminton 21')['items'])->toHaveCount(1);
    expect($archive->search($this->user, 'Rahasia')['items'])->toBe([]);
    expect(AssistantExchange::count())->toBe(23);
});

test('archive imports legacy thread on first browse without duplicate pairs', function (): void {
    AssistantThread::create(['user_id' => $this->user->id, 'messages' => [
        ['role' => 'user', 'content' => 'Legacy topik'], ['role' => 'assistant', 'content' => 'Legacy jawaban'],
    ]]);
    $archive = app(AssistantHistoryArchive::class);
    expect($archive->search($this->user)['items'])->toHaveCount(1);
    expect($archive->search($this->user)['items'])->toHaveCount(1);
});

test('archive rejects inactive users', function (): void {
    $this->user->update(['is_active' => false]);
    app(AssistantHistoryArchive::class)->search($this->user);
})->throws(HttpException::class);
