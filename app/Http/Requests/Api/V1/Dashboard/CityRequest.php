<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use CodeZero\UniqueTranslation\UniqueTranslationRule;

class CityRequest extends FormRequest
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
            "name" => ['required', 'array'],
         //   "name.ar" => ['required', 'string', ],
        ];
        if($this->getMethod() == "POST")
           // $rules["name"]= ['unique:cities,name'];
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
        return $rules;
    }

    /* public function bodyParameters(): array
    {
        return scribe_fluent()
            ->parameter('is_active', 'boolean', '1')
            ->toArray();
    } */
}
