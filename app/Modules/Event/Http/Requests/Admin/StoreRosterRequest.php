<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRosterRequest extends FormRequest
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
            'members' => ['present', 'array'],
            'members.*.user_id' => ['required', 'integer'],
            'members.*.team_role' => ['nullable', 'string', 'max:50'],
            'members.*.jersey_number' => ['nullable', 'integer', 'min:0', 'max:99'],
            'members.*.is_captain' => ['nullable', 'boolean'],
            'members.*.position' => ['nullable', 'string', 'max:50'],
        ];
    }
}
