<?php

namespace App\Http\Requests\Api\V1\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientEditProfile extends FormRequest
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
            "name" => ["required","string","min:2","max:100","regex:/^[\pL\pN ]+$/u"],
            "email" => ["required","email","unique:users,email,".$this->user()->id],
            "nationalId" => ["required","digits:10","unique:users,nationalId,".$this->user()->id],
        ];
    }
}
