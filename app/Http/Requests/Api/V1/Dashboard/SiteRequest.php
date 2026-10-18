<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SiteRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([]);
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'contract_date' => [$isCreate ? 'required' : 'sometimes', 'date'],
            'duration_in_months' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:1'],
            'location_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:locations,id'],
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'user_ids' => ['sometimes', 'nullable', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [

        ];
    }

    public function bodyParameters(): array
    {
        return scribe_fluent()
            ->parameter('is_active', 'boolean', '1')
            ->toArray();
    }
}
