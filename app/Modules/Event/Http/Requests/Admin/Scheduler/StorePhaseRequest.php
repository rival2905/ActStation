<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin\Scheduler;

use Illuminate\Foundation\Http\FormRequest;

class StorePhaseRequest extends FormRequest
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
            'event_category_id' => ['required', 'integer', 'exists:event_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'phase_type' => ['required', 'string', 'in:pool,knockout,placement'],
            'phase_order' => ['nullable', 'integer', 'min:0'],
            'config' => ['nullable', 'array'],
            'advancement_rules' => ['nullable', 'array'],
        ];
    }
}
