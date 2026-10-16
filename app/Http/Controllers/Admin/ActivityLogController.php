<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->only(['user_id', 'event', 'auditable_type', 'date_from', 'date_to', 'search']);

        $query = ActivityLog::with('user')
            ->filter($request);

        $sort = (string) $request->input('sort', 'id');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'created_at', 'event', 'auditable_type', 'auditable_id', 'ip_address'];
        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->latest('id');
        }

        $perPage = (int) $request->input('per_page', 10);
        $logs = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data' => $logs->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'created_at_formatted' => $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-',
                    'user_name' => $log->user?->name ?? 'Sistem',
                    'event' => $log->event,
                    'event_badge_type' => match ($log->event) {
                        'created', 'login' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'secondary'
                    },
                    'auditable_type_short' => class_basename((string) $log->auditable_type),
                    'auditable_id' => $log->auditable_id ?? '-',
                    'ip_address' => $log->ip_address ?? '-',
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'has_details' => ! empty($log->old_values) || ! empty($log->new_values),
                ]),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem() ?? 0,
                'to' => $logs->lastItem() ?? 0,
            ]);
        }

        $users = User::orderBy('name')->get();

        $events = ActivityLog::select('event')
            ->distinct()
            ->pluck('event')
            ->toArray();

        if (empty($events)) {
            $events = ['created', 'updated', 'deleted', 'login', 'logout', 'login_failed'];
        }

        $auditableTypes = ActivityLog::select('auditable_type')
            ->whereNotNull('auditable_type')
            ->distinct()
            ->pluck('auditable_type')
            ->toArray();

        return view('admin.activity-logs.index', compact('logs', 'users', 'events', 'auditableTypes', 'filters'));
    }

    public function export(Request $request): StreamedResponse
    {
        $fileName = 'activity-logs-'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'created_at',
                'user_name',
                'user_email',
                'event',
                'auditable_type',
                'auditable_id',
                'role_context',
                'ip_address',
                'user_agent',
            ]);

            $logs = ActivityLog::with('user')
                ->filter($request)
                ->latest('id')
                ->cursor();

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at?->toIso8601String() ?? '',
                    $log->user?->name ?? 'Sistem',
                    $log->user?->email ?? '',
                    $log->event,
                    $log->auditable_type ?? '',
                    $log->auditable_id ?? '',
                    $log->role_context ?? '',
                    $log->ip_address ?? '',
                    $log->user_agent ?? '',
                ]);
            }

            fclose($handle);
        }, $fileName, $headers);
    }
}
