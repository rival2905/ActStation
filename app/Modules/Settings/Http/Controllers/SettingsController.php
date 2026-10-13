<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Requests\UpdateEmailSettingsRequest;
use App\Modules\Settings\Http\Requests\UpdateGeneralSettingsRequest;
use App\Modules\Settings\Http\Requests\UpdatePaymentSettingsRequest;
use App\Modules\Settings\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    public function general(): View
    {
        $settings = $this->settingService->getGroup('general');

        return view('admin.settings.general', compact('settings'));
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $this->settingService->setMany('general', $request->validated());

        return redirect()
            ->route('admin.settings.general')
            ->with('success', 'Pengaturan umum berhasil disimpan.');
    }

    public function payment(): View
    {
        $settings = $this->settingService->getGroup('payment', maskSensitive: true);

        return view('admin.settings.payment', compact('settings'));
    }

    public function updatePayment(UpdatePaymentSettingsRequest $request): RedirectResponse
    {
        $this->settingService->setMany('payment', $request->validated());

        return redirect()
            ->route('admin.settings.payment')
            ->with('success', 'Pengaturan pembayaran berhasil disimpan.');
    }

    public function email(): View
    {
        $settings = $this->settingService->getGroup('email', maskSensitive: true);

        return view('admin.settings.email', compact('settings'));
    }

    public function updateEmail(UpdateEmailSettingsRequest $request): RedirectResponse
    {
        $this->settingService->setMany('email', $request->validated());

        return redirect()
            ->route('admin.settings.email')
            ->with('success', 'Pengaturan email berhasil disimpan.');
    }
}
