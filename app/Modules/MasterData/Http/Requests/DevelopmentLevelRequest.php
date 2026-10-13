<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Models\DevelopmentLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DevelopmentLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sport_category_id' => ['nullable', 'exists:sport_categories,id'],
            'name' => ['required', 'string', 'max:100'],
            'min_age' => ['required', 'integer', 'min:0', 'max:100'],
            'max_age' => ['nullable', 'integer', 'gte:min_age', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('min_age') || $validator->errors()->has('max_age')) {
                return;
            }

            $sportCategoryId = $this->filled('sport_category_id') ? (int) $this->input('sport_category_id') : null;
            $minAge = (int) $this->input('min_age');
            $maxAge = $this->filled('max_age') ? (int) $this->input('max_age') : null;

            $level = $this->route('developmentLevel');
            $ignoreId = is_object($level) ? $level->id : $level;

            $query = DevelopmentLevel::query();

            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }

            if ($sportCategoryId !== null) {
                $query->where('sport_category_id', $sportCategoryId);
            } else {
                $query->whereNull('sport_category_id');
            }

            // Overlap check: [A, B] and [C, D] overlap iff A <= D and C <= B
            $query->where(function ($q) use ($maxAge): void {
                if ($maxAge !== null) {
                    $q->where('min_age', '<=', $maxAge);
                }
            })->where(function ($q) use ($minAge): void {
                $q->whereNull('max_age')
                    ->orWhere('max_age', '>=', $minAge);
            });

            if ($query->exists()) {
                $validator->errors()->add('min_age', 'Rentang usia bertabrakan dengan jenjang pembinaan yang sudah ada.');
            }
        });
    }
}
