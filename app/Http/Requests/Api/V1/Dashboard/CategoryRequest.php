<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
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
            'parent_id' => ['nullable', 'exists:categories,id'],
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

       $rules["name.en"] = [
            'required',
            'string',
            'min:3',
            'max:100',

        ];
        $rules["name.ar"] = [
            'required',
            'string',
            'min:3',
            'max:100',

        ];

        
     return $rules;
    }

    private function putPatchRules(array $rules): array{
         $rules["name.en"] = [
            'required',
            'string',
            'min:3',
            'max:100',

        ];
        $rules["name.ar"] = [
            'required',
            'string',
            'min:3',
            'max:100',

        ];
        return $rules;
    }

    public function bodyParameters(): array
    {
        return scribe_fluent()
            ->parameter('is_active', 'boolean', '1')
            ->toArray();
    }
}
