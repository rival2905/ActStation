<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,paid,processing,shipped,delivered,cancelled'],
        ];
    }
}
