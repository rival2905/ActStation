<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AgentOfficeAdminAuth;
use Illuminate\Console\Command;
use Throwable;

class AgentOfficeEnrollMfa extends Command
{
    protected $signature = 'agent-office:enroll-mfa {--user=} {--output=}';

    protected $description = 'Enroll designated human operator from authorized server console into exclusive private file';

    public function handle(AgentOfficeAdminAuth $auth): int
    {
        $id = $this->option('user');
        $output = $this->option('output');
        try {
            if (! is_string($id) || ! preg_match('/^[1-9][0-9]*$/D', $id) || ! is_string($output) || ! in_array((int) $id, config('agent_office.operator_ids', []), true)) {
                throw new \RuntimeException('operator_denied');
            }
            $user = User::find((int) $id);
            if (! $user || ! $user->is_active || ! $user->hasPermissionTo('setting.agent-office-security', 'web') || ! $user->roles->contains(fn ($role): bool => in_array($role->panel_access, ['internal', 'both'], true))) {
                throw new \RuntimeException('operator_denied');
            }
            $auth->enroll((int) $id, $output);
            $this->info('Enrollment saved to exclusive private file. Import using authorized authenticator, then securely remove enrollment file.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('MFA enrollment denied or unavailable. Existing enrollment not replaced.');

            return self::FAILURE;
        }
    }
}
