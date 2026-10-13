<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleBreakRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }
}
