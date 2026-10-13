<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'total_roles' => Role::count(),
            'roles' => Role::count(),
            'total_permissions' => Permission::count(),
            'permissions' => Permission::count(),
            'total_activities' => ActivityLog::count(),
            'activities' => ActivityLog::count(),
        ];

        $recent_activities = ActivityLog::with('user')
            ->latest('id')
            ->take(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recent_activities'));
    }
}
