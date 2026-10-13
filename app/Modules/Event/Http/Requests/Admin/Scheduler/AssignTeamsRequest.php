<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;

class AssignTeamsRequest extends FormRequest
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
            'phase_id' => ['required', 'integer'],
            'teams' => ['nullable', 'array'],
            'standings' => ['nullable', 'array'],
            'cross_pool' => ['nullable', 'boolean'],
        ];
    }
}
