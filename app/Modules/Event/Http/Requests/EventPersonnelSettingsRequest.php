<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use App\Contracts\TeamPersonnelProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventPersonnelSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $this->routeIs('admin.*') ? (bool) $this->user()?->can('event.update') : ($event && ((int) $event->user_id === (int) $this->user()?->id || $this->user()?->hasRole('superadmin')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('personnel_requirements')) {
            $this->merge(['personnel_requirements' => []]);
        }
    }

    public function rules(): array
    {
        return [
            'personnel_requirements' => ['present', 'array', 'max:100'],
            'personnel_requirements.*' => ['array'],
            'personnel_requirements.*.role_id' => ['required', 'integer', 'distinct', Rule::in(collect(app(TeamPersonnelProvider::class)->roles())->where('is_active', true)->pluck('id')->all())],
            'personnel_requirements.*.min' => ['required', 'integer', 'min:0', 'max:100'],
            'personnel_requirements.*.max' => ['required', 'integer', 'min:1', 'max:100', 'gte:personnel_requirements.*.min'],
        ];
    }
}
