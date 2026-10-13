<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;

class SaveScheduleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_time' => ['required', 'string'],
            'end_time_daily' => ['required', 'string'],
            'break_between_games' => ['required', 'integer', 'min:0'],
            'durations' => ['nullable', 'array'],
        ];
    }
}
