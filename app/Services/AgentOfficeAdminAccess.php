<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class AgentOfficeAdminAccess
{
    public function authorise(Request $request): User
    {
        $lockStore = config('session.block_store') ?? config('cache.default');
        abort_unless(in_array(config('session.driver'), ['file', 'database', 'redis'], true)
            && in_array(config('cache.stores.'.$lockStore.'.driver'), ['file', 'database', 'redis'], true), 503);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $user = User::find($actor->getKey());
        $allowed = false;
        try {
            $allowed = $user && $user->is_active
                && in_array((int) $user->id, config('agent_office.operator_ids', []), true)
                && $user->hasPermissionTo('setting.agent-office-security', 'web')
                && $user->roles->contains(fn ($role): bool => in_array($role->panel_access, ['internal', 'both'], true));
        } catch (Throwable) {
            $allowed = false;
        }
        abort_unless($allowed, 403);

        return $user;
    }

    public function reauthenticate(Request $request, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $otp): void
    {
        $user = $this->authorise($request);
        $request->session()->forget('agent_office_reauth');
        app(AgentOfficeAdminAuth::class)->verify((int) $user->id, $password, $otp, $user->password);
        app(AgentOfficeSecurity::class)->audit(['event' => 'admin_reauthenticated', 'operator_id' => (int) $user->id]);
        $request->session()->put('agent_office_reauth', ['binding' => $this->binding($request, $user), 'expires' => time() + 120]);
    }

    public function consume(Request $request): User
    {
        $user = $this->authorise($request);
        $proof = $request->session()->pull('agent_office_reauth');
        abort_unless(is_array($proof) && is_int($proof['expires'] ?? null) && $proof['expires'] > time() && $proof['expires'] <= time() + 120
            && is_string($proof['binding'] ?? null) && hash_equals($this->binding($request, $user), $proof['binding']), 403);

        return $user;
    }

    private function binding(Request $request, User $user): string
    {
        $path = AgentOfficeSecurity::privatePath('agent_office.admin_mfa_path');
        abort_unless(is_file($path), 403);

        return hash('sha256', json_encode([$request->session()->getId(), (int) $user->id, $user->password, config('agent_office.operator_ids', []), hash_file('sha256', $path)], JSON_THROW_ON_ERROR));
    }
}
