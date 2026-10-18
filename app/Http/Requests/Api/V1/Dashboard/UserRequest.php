<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Enum\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UserRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $data = [
            "is_active" => 1,
            'status' => auth()->user()?->type === 'admin' ? 'ACTIVE' : 'PENDING',
            "type" => UserTypeEnum::EMPLOYEE
        ];

        if ($this->isMethod('POST')) {
            $data['password'] = Str::random(12);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $rules = [];
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
        return [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'role_id'       => 'required|exists:roles,id',
        ];
    }

    private function putPatchRules(array $rules): array{
        $user = $this->route('admin') ?? $this->route('admin');

      
        return [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . ($user),
            'role_id'       => 'required|exists:roles,id',
            "status"     => 'required|in:ACTIVE,INACTIVE,PENDING',
            'password' => 'nullable'
        ];
    }

    public function bodyParameters(): array
    {
        return scribe_fluent()
            ->parameter('is_active', 'boolean', '1')
            ->toArray();
    }
}
