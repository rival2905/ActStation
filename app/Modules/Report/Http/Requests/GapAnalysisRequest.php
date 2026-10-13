<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GapAnalysisRequest extends FormRequest
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
            'level' => ['nullable', 'string', 'in:province,city,subdistrict'],
            'parent_code' => ['nullable', 'string', 'max:15'],
            'province_id' => ['nullable', 'string', 'max:15'],
            'city_id' => ['nullable', 'string', 'max:15'],
            'subdistrict_id' => ['nullable', 'string', 'max:15'],
            'school_level' => ['nullable', 'string', 'in:sd,smp,sma,smk'],
            'refresh' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
