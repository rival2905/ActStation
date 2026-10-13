<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplyHelpdeskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
