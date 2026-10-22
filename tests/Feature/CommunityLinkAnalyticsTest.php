<?php

declare(strict_types=1);

use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use App\Modules\Community\Services\CommunityLinkAnalytics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

test('analytics increments daily counters without raw visitor data and scopes each page', function (): void {
    $community = Community::factory()->create(['is_active' => true]);
    $linkId = (string) Str::uuid();
    $page = CommunityLinkPage::create(['community_id' => $community->id, 'handle' => 'metrics-'.Str::lower(Str::random(10)), 'status' => 'published', 'draft_content' => [], 'published_content' => ['links' => [['id' => $linkId, 'url' => 'https://example.com/', 'visible' => true]]]]);
    $service = app(CommunityLinkAnalytics::class);
    $service->record($page);
    $service->record($page);
    $service->record($page, $linkId);
    $summary = $service->summary($page);
    expect($summary['views'])->toBe(2)->and($summary['clicks'])->toBe(1)->and($summary['by_link'][$linkId])->toBe(1)->and($summary['daily'])->toHaveCount(30);
    expect(DB::table('community_link_metrics')->where('community_link_page_id', $page->id)->count())->toBe(2);
    $this->travel(-31)->days();
    $service->record($page);
    $this->travelBack();
    expect($service->summary($page)['views'])->toBe(2);
    $page->update(['status' => 'unpublished']);
    try {
        $service->record($page);
        test()->fail('Unpublished page counted');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
});
