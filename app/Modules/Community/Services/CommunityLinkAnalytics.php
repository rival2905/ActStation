<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityLinkPage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunityLinkAnalytics
{
    /**
     * Record a page visit or link click atomically.
     */
    public function record(CommunityLinkPage $page, ?string $linkId = null): void
    {
        if ($page->status !== 'published') {
            abort(404, 'Page is not published.');
        }

        if ($page->suspended_at !== null) {
            abort(404, 'Page is suspended.');
        }

        $community = $page->relationLoaded('community')
            ? $page->getRelation('community')
            : Community::find($page->community_id);

        if (! $community || ! $community->is_active || $community->trashed()) {
            abort(404, 'Community is inactive or deleted.');
        }

        if ($linkId !== null) {
            if (! Str::isUuid($linkId)) {
                abort(404, 'Invalid link identifier.');
            }

            $publishedContent = is_array($page->published_content) ? $page->published_content : [];
            $links = $publishedContent['links'] ?? [];
            $matched = collect($links)->first(fn ($item): bool => is_array($item) && ($item['id'] ?? null) === $linkId);

            if (! $matched || empty($matched['visible'])) {
                abort(404, 'Link is not visible or not found in published snapshot.');
            }

            $itemKey = $linkId;
        } else {
            $itemKey = 'views';
        }

        $today = Carbon::now('UTC')->toDateString();

        // ponytail: direct SQL upsert guarantees atomic counter increment in MySQL without separate locking.
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement(
                'INSERT INTO community_link_metrics (community_link_page_id, metric_date, item_key, total, created_at, updated_at)
                 VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                 ON CONFLICT(community_link_page_id, metric_date, item_key) DO UPDATE SET total = total + 1, updated_at = CURRENT_TIMESTAMP',
                [$page->id, $today, $itemKey]
            );
        } else {
            DB::statement(
                'INSERT INTO community_link_metrics (community_link_page_id, metric_date, item_key, total, created_at, updated_at)
                 VALUES (?, ?, ?, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE total = total + 1, updated_at = NOW()',
                [$page->id, $today, $itemKey]
            );
        }
    }

    /**
     * Get aggregate metrics for the last 30 UTC days.
     *
     * @return array{views: int, clicks: int, totals: array{views: int, clicks: int}, by_link: array<string, int>, daily: list<array{date: string, views: int, clicks: int}>}
     */
    public function summary(CommunityLinkPage $page): array
    {
        $now = Carbon::now('UTC');
        $endDate = $now->copy()->startOfDay();
        $startDate = $endDate->copy()->subDays(29);

        $rows = DB::table('community_link_metrics')
            ->where('community_link_page_id', $page->id)
            ->whereBetween('metric_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $views = 0;
        $clicks = 0;
        $byLink = [];
        $dailyMap = [];

        for ($i = 29; $i >= 0; $i--) {
            $d = $now->copy()->subDays($i)->toDateString();
            $dailyMap[$d] = [
                'date' => $d,
                'views' => 0,
                'clicks' => 0,
            ];
        }

        foreach ($rows as $row) {
            $date = (string) $row->metric_date;
            $total = (int) $row->total;

            if ($row->item_key === 'views') {
                $views += $total;
                if (isset($dailyMap[$date])) {
                    $dailyMap[$date]['views'] += $total;
                }
            } else {
                $clicks += $total;
                $byLink[$row->item_key] = ($byLink[$row->item_key] ?? 0) + $total;
                if (isset($dailyMap[$date])) {
                    $dailyMap[$date]['clicks'] += $total;
                }
            }
        }

        return [
            'views' => $views,
            'clicks' => $clicks,
            'totals' => [
                'views' => $views,
                'clicks' => $clicks,
            ],
            'by_link' => $byLink,
            'daily' => array_values($dailyMap),
        ];
    }
}
