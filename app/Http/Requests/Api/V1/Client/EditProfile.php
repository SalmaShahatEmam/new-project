<?php

namespace App\Http\Requests\Api\V1\Client;

use Illuminate\Foundation\Http\FormRequest;

class EditProfile extends FormRequest
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
            "email" => ["nullable","email","unique:users,email,".$this->user()->id],
            "city_id" => ["nullable","exists:cities,id"],
            "image" => ["nullable","image","mimes:jpeg,png,jpg,gif,svg","max:2048"],
        ];
    }
}
