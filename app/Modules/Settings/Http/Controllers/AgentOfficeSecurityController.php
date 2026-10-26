<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Requests\AgentOfficeSecurityRequest;
use App\Services\AgentOfficeAdminAccess;
use App\Services\AgentOfficeSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AgentOfficeSecurityController extends Controller
{
    public function index(Request $request, AgentOfficeAdminAccess $access, AgentOfficeSecurity $security): View
    {
        $access->authorise($request);
        try {
            $snapshot = $security->snapshot(false);
            $disabled = file_exists(AgentOfficeSecurity::privatePath('agent_office.registry_path').'.disabled');
            $pending = file_exists(AgentOfficeSecurity::privatePath('agent_office.registry_path').'.pending');
        } catch (Throwable) {
            abort(503, 'Registry keamanan tidak tersedia. Tidak ada perubahan diterapkan.');
        }

        return view('admin.settings.agent-office', ['entries' => $snapshot['allowlist'], 'version' => $snapshot['version'], 'disabled' => $disabled, 'pending' => $pending]);
    }

    public function reauthenticate(Request $request, AgentOfficeAdminAccess $access): RedirectResponse
    {
        $access->authorise($request);
        $input = $request->validate(['password' => ['required', 'string', 'max:1024'], 'otp' => ['required', 'string', 'regex:/^[0-9]{6}$/D']]);
        try {
            $access->reauthenticate($request, $input['password'], $input['otp']);
        } catch (Throwable) {
            return back()->withErrors(['reauth' => 'Verifikasi ditolak atau layanan keamanan tidak tersedia.']);
        }

        return back()->with('success', 'Verifikasi berlaku 120 detik untuk satu perubahan.');
    }

    public function update(AgentOfficeSecurityRequest $request, AgentOfficeAdminAccess $access, AgentOfficeSecurity $security): RedirectResponse
    {
        $user = $access->consume($request);
        try {
            $result = $security->replaceAllowlist($request->entries(), (int) $request->validated('expected_version'), (int) $user->id);
        } catch (Throwable $error) {
            abort($error->getMessage() === 'registry_version_conflict' ? 409 : 503, 'Publikasi ditolak; baca ulang versi efektif.');
        }

        return redirect()->route('admin.settings.agent-office')->with('gateway_status', $result['status'])->with('gateway_effective_version', $result['effective_version']);
    }

    public function reenable(Request $request, AgentOfficeAdminAccess $access, AgentOfficeSecurity $security): RedirectResponse
    {
        $access->authorise($request);
        $input = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'confirm_reenable' => ['required', 'accepted']]);
        $user = $access->consume($request);
        try {
            $security->reenable((int) $input['expected_version'], (int) $user->id);
        } catch (Throwable) {
            abort(503, 'Re-enable gagal. Emergency disable tetap harus diperiksa.');
        }

        return redirect()->route('admin.settings.agent-office')->with('success', 'Emergency disable dilepas. Konfigurasi deployment tetap menentukan aktivasi gateway.');
    }
}
