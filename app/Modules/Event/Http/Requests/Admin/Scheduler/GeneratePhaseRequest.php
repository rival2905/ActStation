<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePhaseRequest extends FormRequest
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
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer'],
            'config' => ['nullable', 'array'],
            'positions' => ['nullable', 'array'],
            'positions.*' => ['integer'],
            'knockout_phase_id' => ['nullable', 'integer'],
        ];
    }
}
