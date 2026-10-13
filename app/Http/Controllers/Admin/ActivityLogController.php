<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['user_id', 'event', 'auditable_type', 'date_from', 'date_to']);

        $logs = ActivityLog::with('user')
            ->filter($request)
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

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
