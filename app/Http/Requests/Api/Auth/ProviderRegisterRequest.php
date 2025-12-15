<?php

namespace App\Http\Requests\Api\Auth;

use App\Enum\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class ProviderRegisterRequest extends FormRequest
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
            "type" => UserTypeEnum::STORE,
        ]);
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
    
            'brand_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\pL\pN ]+$/u', // حروف وأرقام فقط
            ],

      
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/^[\pL\pN ]+$/u',
            ],

    
            'mobile' => [
                'required',
                'digits:9',
                'regex:/^5[0-9]{8}$/', // يبدأ بـ 05
                'unique:users,mobile',
            ],

          
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

      
            'nationalId' => [
                'required',
                'digits:10',
                'unique:users,nationalId',
            ],

            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120', 
            ],
            'password' => [
                'required',
                'string',
                'min:6',
            ],

     
            'password_confirmation' => [
                'required',
                'same:password',
            ],

         
            'commercial_register' => [
                'required',
                'digits:10',
            ],

         
            'branches_count' => [
                'required',
                'integer',
            ],
        ];
    }
}
