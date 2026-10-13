<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportExportRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'in:summary,users,transactions,events,gap'],
            'format' => ['nullable', 'string', 'in:csv,pdf'],
            'period' => ['nullable', 'string', 'in:today,week,month,year'],
        ];
    }

    public function exportType(): string
    {
        return (string) $this->input('type', 'summary');
    }

    public function exportFormat(): string
    {
        return (string) $this->input('format', 'csv');
    }

    public function period(): string
    {
        return (string) $this->input('period', 'month');
    }
}
