<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransitionStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:draft,published,ongoing,completed,cancelled,archived'],
            'cancel_reason' => ['nullable', 'string', 'required_if:status,cancelled'],
        ];
    }
}
