<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHelpdeskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
        ];
    }
}
