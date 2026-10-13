<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class AuthActivitySubscriber
{
    public function handleLogin(Login $event): void
    {
        try {
            $user = $event->user;
            $roleContext = method_exists($user, 'roles') ? $user->roles?->first()?->name : null;

            ActivityLog::create([
                'user_id' => $user->getAuthIdentifier(),
                'event' => 'login',
                'auditable_type' => null,
                'auditable_id' => null,
                'role_context' => $roleContext,
                'old_values' => null,
                'new_values' => null,
                'ip_address' => request()?->ip(),
                'user_agent' => request() ? substr((string) request()->userAgent(), 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log auth login event: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    public function handleLogout(Logout $event): void
    {
        try {
            $user = $event->user;
            $roleContext = ($user && method_exists($user, 'roles')) ? $user->roles?->first()?->name : null;

            ActivityLog::create([
                'user_id' => $user?->getAuthIdentifier(),
                'event' => 'logout',
                'auditable_type' => null,
                'auditable_id' => null,
                'role_context' => $roleContext,
                'old_values' => null,
                'new_values' => null,
                'ip_address' => request()?->ip(),
                'user_agent' => request() ? substr((string) request()->userAgent(), 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log auth logout event: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    public function handleFailed(Failed $event): void
    {
        try {
            $user = $event->user;
            $roleContext = ($user && method_exists($user, 'roles')) ? $user->roles?->first()?->name : null;

            $credentials = $event->credentials;
            unset($credentials['password']);
            $newValues = ActivityLog::redact($credentials);

            ActivityLog::create([
                'user_id' => $user?->getAuthIdentifier(),
                'event' => 'login_failed',
                'auditable_type' => null,
                'auditable_id' => null,
                'role_context' => $roleContext,
                'old_values' => null,
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request() ? substr((string) request()->userAgent(), 0, 255) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log auth failed event: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}
