<?php

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Enum\SiteReportTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        if (! array_key_exists('user_id', $data) && auth()->check()) {
            $this->merge(['user_id' => auth()->id()]);
        }

        $site = $this->route('site');
        if ($site instanceof \App\Models\Site && ! array_key_exists('site_id', $data)) {
            $this->merge(['site_id' => $site->id]);
        }
    }

    public function rules(): array
    {
        $reportTypeValues = array_map(fn (SiteReportTypeEnum $type) => $type->value, SiteReportTypeEnum::cases());

        $rules = [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'report_type' => ['required', Rule::in($reportTypeValues)],
            'resources' => ['sometimes', 'array', 'min:1', 'prohibited_unless:report_type,materials,equipment'],
            'resources.*.resource_id' => ['required', 'integer', 'exists:resources,id'],
            'resources.*.quantity' => ['required', 'string', 'max:255'],
     
            'resources.*.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'resources.*.report_content' => ['required', 'string'],
            'resources.*.notes' => ['required', 'string'],
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'quantity' => ['nullable', 'string', 'max:255'],
        
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'report_content' => ['required_without:resources', 'string'],
            'notes' => ['nullable', 'string'],
        ];

        $reportType = $this->input('report_type');

        if (in_array($reportType, [SiteReportTypeEnum::LABOR->value, SiteReportTypeEnum::MATERIALS->value, SiteReportTypeEnum::EQUIPMENT->value], true)) {
            $rules['quantity'] = ['required', 'string', 'max:255'];
        }

        if (in_array($reportType, [SiteReportTypeEnum::MATERIALS->value, SiteReportTypeEnum::EQUIPMENT->value], true)) {
            $rules['resources'] = ['required_without:resource_id', 'array', 'min:1'];
            $rules['resources.*.supplier_id'] = $reportType === SiteReportTypeEnum::MATERIALS->value
                ? ['required', 'integer', 'exists:suppliers,id']
                : ['nullable', 'integer', 'exists:suppliers,id'];

            if ($reportType === SiteReportTypeEnum::MATERIALS->value) {
                $rules['quantity'] = ['required_without:resources', 'string', 'max:255'];
                $rules['supplier_id'] = ['required_without:resources', 'integer', 'exists:suppliers,id'];
            }

            $rules['resource_id'] = ['required_without:resources', 'integer', 'exists:resources,id'];
        
        }

     

        return $rules;
    }

    public function attributes(): array
    {
        return [];
    }
}
