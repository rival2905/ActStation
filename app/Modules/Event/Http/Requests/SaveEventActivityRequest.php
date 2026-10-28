<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class SaveEventActivityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['starts_at', 'ends_at', 'registration_deadline'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value)) {
                try {
                    $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, 'Asia/Jakarta');
                    if ($date && $date->format('Y-m-d\TH:i') === $value) {
                        $this->merge([$field => $date->utc()->format('Y-m-d H:i:s')]);
                    }
                } catch (\Exception) {
                    // Invalid input remains available to ordinary date validation.
                }
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'eligibility_scope' => ['sometimes', 'required', 'string', 'in:all,academic,school,university'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'registration_deadline' => ['nullable', 'date'],
            'capacity' => ['required', 'integer', 'min:0'],
            'min_members' => ['required', 'integer', 'min:1', 'max:100'],
            'max_members' => ['required', 'integer', 'min:1', 'max:100', 'gte:min_members'],
            'fee_mode' => ['nullable', 'string', 'in:separate,included_team,included_institution'],
            'included_slots' => ['nullable', 'integer', 'min:0'],
            'fee' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'allow_extra' => ['nullable', 'boolean'],
            'is_open' => ['nullable', 'boolean'],
        ];
    }
}
