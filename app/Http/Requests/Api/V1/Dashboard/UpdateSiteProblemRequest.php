<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Enum\SiteProblemPriorityEnum;
use App\Enum\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSiteProblemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->type === UserTypeEnum::ADMIN;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(['new', 'in_progress', 'solved'])],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', 'required', Rule::enum(SiteProblemPriorityEnum::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $input = $this->all();
            if (! array_key_exists('status', $input)
                && ! array_key_exists('assigned_to', $input)
                && ! array_key_exists('priority', $input)) {
                $validator->errors()->add('status', 'Provide a status, assigned_to, or priority value.');
            }
        });
    }
}
