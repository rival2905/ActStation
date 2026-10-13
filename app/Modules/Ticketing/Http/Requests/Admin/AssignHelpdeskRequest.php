<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignHelpdeskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query): void {
                    $query->whereExists(function ($sub): void {
                        $sub->selectRaw('1')
                            ->from('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereColumn('model_has_roles.model_id', 'users.id')
                            ->where('model_has_roles.model_type', (new User)->getMorphClass())
                            ->whereIn('roles.panel_access', ['internal', 'both']);
                    });
                }),
            ],
        ];
    }
}
