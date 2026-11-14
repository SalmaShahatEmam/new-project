<?php

namespace App\Http\Requests\Api\Dashboard;

use App\Enum\OrderItemPriorityEnum;
use App\Enum\OrderItemStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'user_id' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'order_number' => [$this->isMethod('post') ? 'prohibited' : 'sometimes', 'string', 'max:255'],
            'sections' => ['nullable', 'in:labour,equipment,materials,subcontractors'],
            'items' => ['required', 'array'],
            'items.*' => ['required', 'array'],
            'items.*.BOQ_item' => ['required_without:items.*.boq_item', 'nullable', 'string'],
            'items.*.boq_item' => ['required_without:items.*.BOQ_item', 'nullable', 'string'],
            'items.*.resource_id' => ['required', 'integer', 'exists:resources,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string'],
            'items.*.site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'items.*.status' => ['nullable', Rule::enum(OrderItemStatusEnum::class)],
            'items.*.priority' => ['required', Rule::enum(OrderItemPriorityEnum::class)],
            'items.*.delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items.*.notes' => ['nullable', 'string'],
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
        $deliveryDate = now()->addDays(14)->toDateString();
        $itemsExample = json_encode([
            [
                'resource_id' => 1,
                'site_id' => 1,
                'boq_item' => 'BOQ-EL-014',
                'quantity' => 12,
                'reason' => 'Replacement materials for the electrical room',
                'priority' => 'urgent',
                'delivery_date' => $deliveryDate,
                'notes' => 'Deliver to the site store',
            ],
            [
                'resource_id' => 2,
                'boq_item' => 'BOQ-PL-008',
                'quantity' => 6,
                'reason' => null,
                'priority' => 'very_urgent',
                'delivery_date' => $deliveryDate,
                'notes' => null,
            ],
        ], JSON_THROW_ON_ERROR);

        return scribe_fluent()
            ->parameter('sections', 'string, optional. Order sections or departments.', 'Electrical')
            ->parameter('items', 'array, required. Example rows use fake data; resource_id and site_id must match existing records. Each row requires a BOQ item, resource, quantity, priority, and a delivery date today or later.', $itemsExample)
            ->parameter('items.*.resource_id', 'integer, required. Existing resource ID.', '1')
            ->parameter('items.*.quantity', 'integer, required. Requested quantity (minimum 1).', '3')
            ->parameter('items.*.site_id', 'integer, optional. Existing site ID.', '2')
            ->parameter('items.*.BOQ_item', 'string, required if boq_item is not provided. BOQ item value supplied by the user.', 'BOQ-001')
            ->parameter('items.*.boq_item', 'string, required if BOQ_item is not provided. BOQ item value supplied by the user.', 'BOQ-001')
            ->parameter('items.*.reason', 'string, optional. Reason for the request.', 'Required for site work')
            ->parameter('items.*.status', 'string, optional. Defaults to unapproved. One of: unapproved, approved, rejected, returned_for_revision.', 'unapproved')
            ->parameter('items.*.priority', 'string, required. Priority selected by the user. One of: normal, urgent, very_urgent.', 'urgent')
            ->parameter('items.*.delivery_date', 'date, required. Requested delivery date must be today or a future date (YYYY-MM-DD).', $deliveryDate)
            ->parameter('items.*.notes', 'string, optional. Additional notes.', 'Deliver to site')
            ->toArray();
    }
}
