<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use App\Modules\Event\Services\EventCommitteeAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CommitteeMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(EventCommitteeAccess::class)->owner($this->route('event'), $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject,end'],
        ];
    }
}
