<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use App\Contracts\VenueCatalog;
use App\Modules\Event\Services\EventCommitteeAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateEventVenueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');
        if ($this->routeIs('admin.*')) {
            return $this->user()?->can('event.update') && $this->user()?->can('venue.create') && $this->user()?->can('venue-court.create');
        }

        return app(VenueCatalog::class)->allowsPanelCreation($this->routeIs('*.venues.courts.store'))
            && app(EventCommitteeAccess::class)->allows($event, $this->user(), 'schedule.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && ! $this->routeIs('*.venues.courts.store')
                && ! str_starts_with($this->string('city_id')->toString(), $this->string('province_id')->toString().'.')) {
                $validator->errors()->add('city_id', 'Kota/kabupaten harus berasal dari provinsi yang dipilih.');
            }
        }];
    }

    public function rules(): array
    {
        $rules = ['confirm_master_data' => ['required', 'accepted'], 'creation_type' => ['nullable', 'in:venue,court']];
        if ($this->routeIs('*.venues.courts.store')) {
            return $rules + [
                'venue_id' => ['required', 'integer'], 'court_name' => ['required', 'string', 'max:255'],
                'surface' => ['required', 'in:Wood,Interlock,Vinyl,Concrete,Rubber,Grass,Artificial turf,Other'],
                'condition' => ['required', 'in:Good,Fair,Poor'], 'is_indoor' => ['required', 'boolean'],
            ];
        }

        return $rules + [
            'venue_name' => ['required', 'string', 'max:255'], 'address' => ['required', 'string', 'max:2000'],
            'province_id' => ['required', 'string', 'regex:/^\d{2}$/', 'exists:wilayah,kode'],
            'city_id' => ['required', 'string', 'regex:/^\d{2}\.\d{2}$/', 'exists:wilayah,kode'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
