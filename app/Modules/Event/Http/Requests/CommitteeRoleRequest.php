<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use App\Modules\Event\Services\EventCommitteeAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CommitteeRoleRequest extends FormRequest
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
    public function after(): array
    {
        return [function (Validator $validator): void {
            $caps = $this->input('capabilities', []);
            if (is_array($caps) && count(array_filter($caps, 'is_string')) === count($caps) && array_intersect($caps, ['teams.review', 'payments.confirm']) && ! in_array('teams.view', $caps, true)) {
                $validator->errors()->add('capabilities', 'Aktifkan akses Lihat Tim Peserta untuk pemeriksaan atau konfirmasi pembayaran.');
            }
        }];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('event_committee_roles')->where('event_id', $this->route('event')->id)->ignore($this->route('role')?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'capabilities' => ['sometimes', 'array', 'max:6'],
            'capabilities.*' => ['string', 'distinct', Rule::in(array_keys(EventCommitteeAccess::CAPABILITIES))],
        ];
    }
}
