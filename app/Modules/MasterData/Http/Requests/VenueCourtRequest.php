<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VenueCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_indoor' => $this->boolean('is_indoor', true),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $venue = $this->route('venue');
        $venueId = is_object($venue) ? $venue->id : $venue;

        $court = $this->route('court');
        $courtId = is_object($court) ? $court->id : $court;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('venue_courts', 'name')
                    ->where('venue_id', $venueId)
                    ->ignore($courtId),
            ],
            'surface' => ['nullable', 'string', 'max:50'],
            'is_indoor' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
