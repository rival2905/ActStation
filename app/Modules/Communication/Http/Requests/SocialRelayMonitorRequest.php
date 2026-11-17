<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests;

use App\Modules\Communication\Http\Controllers\Admin\SocialRelayMonitorController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SocialRelayMonitorRequest extends FormRequest
{
    /** Route middleware enforces `setting.email`; this only validates read filters. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(array_keys(SocialRelayMonitorController::STATUSES))],
            'platform' => ['nullable', Rule::in(array_keys(SocialRelayMonitorController::PLATFORMS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
