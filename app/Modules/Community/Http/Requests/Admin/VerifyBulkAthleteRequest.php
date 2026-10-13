<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBulkAthleteRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'decision' => ['required', 'string', 'in:verified,rejected'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
