<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
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
            "name" => "required|array",
            "contact" => "required|string|max:255",
            "map_link" => "nullable|string|max:255",
            
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
