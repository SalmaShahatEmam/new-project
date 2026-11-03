<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class CombinedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
