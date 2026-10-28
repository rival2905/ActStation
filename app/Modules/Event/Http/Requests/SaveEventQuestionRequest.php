<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests;

use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventCommitteeAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveEventQuestionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['options', 'school_levels', 'institution_types'] as $field) {
            $value = $this->input($field);
            if (is_array($value)) {
                $this->merge([$field => array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''))]);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && app(EventCommitteeAccess::class)->owner($event, $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', 'in:institution,team,participant'],
            'type' => ['required', 'in:text,number,select,file'],
            'label' => ['required', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'is_required' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array', 'max:50'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
            'school_levels' => ['nullable', 'array', 'max:20'],
            'school_levels.*' => ['string', 'max:50'],
            'institution_types' => ['nullable', 'array', 'max:20'],
            'institution_types.*' => ['string', 'max:50'],
            'depends_on_question_id' => ['nullable', 'integer', 'min:1'],
            'conditional_value' => ['nullable', 'string', 'max:255'],
            'order' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
