<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Enum\SiteStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SiteStatusEnum::class)],
        ];
    }
}
