<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RegionOverseerRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'city_id' => ['required', 'string', 'max:10'],
        ];
    }
}
