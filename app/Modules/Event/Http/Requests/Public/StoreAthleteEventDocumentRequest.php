<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreAthleteEventDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->is_active;
    }

    public function rules(): array
    {
        return ['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120']];
    }
}
