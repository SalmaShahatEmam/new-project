<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ResourcePriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    

    protected function prepareForValidation()
    {
        $this->merge([
            "effective_date" => now(),
            "updated_by" => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            "price" => "required|numeric|min:0",
            'currency' => 'required|string',
            "location_id" => "required|exists:locations,id",
            "resource_id" => "required|exists:resources,id",
       
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
