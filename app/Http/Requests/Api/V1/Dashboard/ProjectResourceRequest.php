<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ProjectResourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "resources" => "required|array|min:1",
            "resources.*.resource_id" => "required|exists:resources,id",
            "resources.*.quantity" => "required|numeric|min:1",
            "resources.*.location_id" => "required|exists:locations,id",
            "section_name" => "nullable|string|max:255",
            "engineer_id" => "nullable|integer|exists:users,id",
            "resources.*.note" => "nullable|string|max:1000",
        ];
    }
}
