<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Requests\Panel;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketSalesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket_sales_starts_at' => ['bail', 'nullable', 'date_format:Y-m-d\TH:i', function (string $attribute, mixed $value, Closure $fail): void {
                $end = $this->route('event')?->ends_at;
                if (! $end || CarbonImmutable::parse($value, 'Asia/Jakarta')->gte($end)) {
                    $fail('Pembukaan penjualan harus sebelum event berakhir. Lengkapi tanggal event terlebih dahulu.');
                }
            }],
        ];
    }
}
