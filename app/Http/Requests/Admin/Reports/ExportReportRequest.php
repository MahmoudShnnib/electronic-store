<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;

final class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }
}
