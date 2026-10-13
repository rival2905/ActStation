<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportPeriodRequest extends FormRequest
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
            'period' => ['nullable', 'string', 'in:today,week,month,year'],
            'refresh' => ['nullable', 'boolean'],
        ];
    }

    public function period(): string
    {
        return (string) $this->input('period', 'month');
    }

    public function shouldRefresh(): bool
    {
        return $this->boolean('refresh');
    }
}
