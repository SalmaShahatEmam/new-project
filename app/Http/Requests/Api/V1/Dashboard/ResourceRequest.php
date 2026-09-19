<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
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
        $rules = [
            "name.en" => "required|string|max:255",
            "name.ar" => "required|string|max:255",
            "description.en" => "nullable|string|max:255",
            "description.ar" => "nullable|string|max:255",
            "category_id" => "required|exists:categories,id",
         #   "unit_of_measure.en" => "required|string|max:255",
           # "unit_of_measure.ar" => "required|string|max:255",
            "unit_id" => "required|exists:units,id",
            "resource_code" => "required|string|max:255|unique:resources,resource_code," . $this->resource?->id,
        ];
        if($this->getMethod() == "POST")
            return $this->postRules($rules);
        elseif($this->getMethod() == "PUT" || $this->getMethod() == "PATCH" )
            return $this->putPatchRules($rules);
    }

    public function attributes(): array
    {
        return [

        ];
    }

    private function postRules(array $rules): array{
        return $rules;
    }

    private function putPatchRules(array $rules): array{
        return $rules;
    }

    public function bodyParameters(): array
    {
        return scribe_fluent()
            ->parameter('is_active', 'boolean', '1')
            ->toArray();
    }
}
