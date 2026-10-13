<?php

declare(strict_types=1);

namespace App\Modules\Report\Services;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Pengecualian eksplisit: Report membaca lewat DB::table() read-only, TANPA model Eloquent modul lain.
class DashboardMetricService
{
    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(string $period = 'month', bool $refresh = false): array
    {
        $cacheKey = 'report:dashboard:metrics:'.$period;
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function () use ($period): array {
            [$startDate, $endDate] = $this->getDateRange($period);

            $totalUsers = DB::table('users')->whereNull('deleted_at')->count();
            $activeUsers = DB::table('users')->whereNull('deleted_at')->where('is_active', true)->count();
            $newUsers = DB::table('users')->whereNull('deleted_at')->whereBetween('created_at', [$startDate, $endDate])->count();

            $txStats = DB::table('transactions')
                ->where('status', 'paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total), 0) as total_rev')
                ->first();

            $publishedEvents = DB::table('events')
                ->whereNull('deleted_at')
                ->whereIn('status', ['published', 'ongoing', 'completed'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $participantsCount = DB::table('event_participants')
                ->whereIn('status', ['confirmed', 'attended'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $ticketsSold = DB::table('tickets')
                ->whereIn('status', ['active', 'used'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();

            $ticketsValidated = DB::table('ticket_validations')
                ->whereBetween('validated_at', [$startDate, $endDate])
                ->count();

            $verifiedCommunities = DB::table('communities')
                ->whereNull('deleted_at')
                ->where('status', 'verified')
                ->count();

            $orderStats = DB::table('orders')
                ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total), 0) as gmv')
                ->first();

            $revenueChart = DB::table('transactions')
                ->where('status', 'paid')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as date, COALESCE(SUM(total), 0) as amount')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('date ASC')
                ->get()
                ->map(fn ($r): array => ['date' => (string) $r->date, 'amount' => (float) $r->amount])
                ->all();

            $usersChart = DB::table('users')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('date ASC')
                ->get()
                ->map(fn ($r): array => ['date' => (string) $r->date, 'count' => (int) $r->count])
                ->all();

            $eventsPie = DB::table('events')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->map(fn ($r): array => ['status' => (string) $r->status, 'count' => (int) $r->count])
                ->all();

            $communitiesPie = DB::table('communities as c')
                ->leftJoin('sport_categories as sc', 'sc.id', '=', 'c.sport_category_id')
                ->whereNull('c.deleted_at')
                ->selectRaw("COALESCE(sc.name, 'Umum') as category_name, COUNT(c.id) as count")
                ->groupBy('c.sport_category_id', 'sc.name')
                ->get()
                ->map(fn ($r): array => ['category_name' => (string) $r->category_name, 'count' => (int) $r->count])
                ->all();

            return [
                'period' => $period,
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => $endDate->toDateTimeString(),
                'summary' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'new_users' => $newUsers,
                    'paid_transactions_count' => (int) ($txStats->total_count ?? 0),
                    'revenue' => (float) ($txStats->total_rev ?? 0),
                    'published_events' => $publishedEvents,
                    'participants_count' => $participantsCount,
                    'tickets_sold' => $ticketsSold,
                    'tickets_validated' => $ticketsValidated,
                    'verified_communities' => $verifiedCommunities,
                    'orders_count' => (int) ($orderStats->cnt ?? 0),
                    'gmv' => (float) ($orderStats->gmv ?? 0),
                ],
                'charts' => [
                    'revenue_trend' => $revenueChart,
                    'users_trend' => $usersChart,
                    'events_distribution' => $eventsPie,
                    'communities_by_sport' => $communitiesPie,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserMetrics(string $period = 'month'): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);

        $totalUsers = DB::table('users')->whereNull('deleted_at')->count();
        $activeUsers = DB::table('users')->whereNull('deleted_at')->where('is_active', true)->count();
        $newUsers = DB::table('users')->whereNull('deleted_at')->whereBetween('created_at', [$startDate, $endDate])->count();

        $roleDistribution = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', 'App\\Models\\User')
            ->selectRaw('r.name as role_name, COUNT(*) as count')
            ->groupBy('r.name')
            ->get();

        /** @var LengthAwarePaginator $users */
        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return [
            'period' => $period,
            'summary' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'new_users' => $newUsers,
            ],
            'role_distribution' => $roleDistribution,
            'users' => $users,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransactionMetrics(string $period = 'month'): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);

        $stats = DB::table('transactions')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_count,
                COALESCE(SUM(total), 0) as total_amount,
                COUNT(CASE WHEN status = \'paid\' THEN 1 END) as paid_count,
                COALESCE(SUM(CASE WHEN status = \'paid\' THEN total ELSE 0 END), 0) as paid_amount,
                COUNT(CASE WHEN status = \'failed\' THEN 1 END) as failed_count,
                COUNT(CASE WHEN status = \'pending\' THEN 1 END) as pending_count
            ')
            ->first();

        $byStatus = DB::table('transactions')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total), 0) as amount')
            ->groupBy('status')
            ->get();

        $byType = DB::table('transactions')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('type, COUNT(*) as count, COALESCE(SUM(total), 0) as amount')
            ->groupBy('type')
            ->get();

        /** @var LengthAwarePaginator $transactions */
        $transactions = DB::table('transactions as t')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->whereBetween('t.created_at', [$startDate, $endDate])
            ->select('t.*', 'u.name as user_name', 'u.email as user_email')
            ->orderBy('t.created_at', 'desc')
            ->paginate(20);

        return [
            'period' => $period,
            'summary' => [
                'total_count' => (int) ($stats->total_count ?? 0),
                'total_amount' => (float) ($stats->total_amount ?? 0),
                'paid_count' => (int) ($stats->paid_count ?? 0),
                'paid_amount' => (float) ($stats->paid_amount ?? 0),
                'failed_count' => (int) ($stats->failed_count ?? 0),
                'pending_count' => (int) ($stats->pending_count ?? 0),
            ],
            'by_status' => $byStatus,
            'by_type' => $byType,
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventMetrics(string $period = 'month'): array
    {
        [$startDate, $endDate] = $this->getDateRange($period);

        $eventsSummary = DB::table('events')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_events,
                COUNT(CASE WHEN status = \'published\' THEN 1 END) as published_count,
                COUNT(CASE WHEN status = \'ongoing\' THEN 1 END) as ongoing_count,
                COUNT(CASE WHEN status = \'completed\' THEN 1 END) as completed_count
            ')
            ->first();

        $participantsCount = DB::table('event_participants')
            ->whereIn('status', ['confirmed', 'attended'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $topEvents = DB::table('events as e')
            ->leftJoin('event_participants as ep', 'ep.event_id', '=', 'e.id')
            ->whereNull('e.deleted_at')
            ->whereBetween('e.created_at', [$startDate, $endDate])
            ->selectRaw('e.id, e.title, e.status, COUNT(ep.id) as participants_count')
            ->groupBy('e.id', 'e.title', 'e.status')
            ->orderByDesc('participants_count')
            ->limit(5)
            ->get();

        /** @var LengthAwarePaginator $events */
        $events = DB::table('events as e')
            ->leftJoin('sport_categories as sc', 'sc.id', '=', 'e.sport_category_id')
            ->whereNull('e.deleted_at')
            ->whereBetween('e.created_at', [$startDate, $endDate])
            ->select('e.*', 'sc.name as sport_category_name')
            ->orderBy('e.created_at', 'desc')
            ->paginate(20);

        return [
            'period' => $period,
            'summary' => [
                'total_events' => (int) ($eventsSummary->total_events ?? 0),
                'published_count' => (int) ($eventsSummary->published_count ?? 0),
                'ongoing_count' => (int) ($eventsSummary->ongoing_count ?? 0),
                'completed_count' => (int) ($eventsSummary->completed_count ?? 0),
                'participants_count' => $participantsCount,
            ],
            'top_events' => $topEvents,
            'events' => $events,
        ];
    }
}
