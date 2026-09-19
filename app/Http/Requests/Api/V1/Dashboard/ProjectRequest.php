<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            "user_id" => auth()->id(),
            "status" => $this->get('status', 'pending'),
            "end_date" =>$this->dead_line
        ]);
    }

    public function rules(): array
    {
        $rules = [
            "name" => "required|string|max:255",
            "description" => "nullable|string",
            "deadline" => "nullable|date",
            
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
