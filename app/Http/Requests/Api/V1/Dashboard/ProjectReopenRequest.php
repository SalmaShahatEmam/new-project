<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Enum\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class ProjectReopenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check() && auth('api')->user()->type === UserTypeEnum::ADMIN;
    }

    public function rules(): array
    {
        return [
            'reopen_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
