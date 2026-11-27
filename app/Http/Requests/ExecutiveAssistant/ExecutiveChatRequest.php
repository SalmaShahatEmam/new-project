<?php

namespace App\Http\Requests\ExecutiveAssistant;

use Illuminate\Foundation\Http\FormRequest;
use Override;

class ExecutiveChatRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:5000'],
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'string', 'uuid', 'exists:ai_conversations,id'],
        ];
    }

    /**
     * Custom validation messages in Arabic.
     */
    public function messages(): array
    {
        return [
            'message.required' => 'حقل الرسالة مطلوب.',
            'message.string' => 'الرسالة يجب أن تكون نصية.',
            'message.max' => 'الرسالة طويلة جداً، الحد الأقصى 5000 حرف.',
            'userId.exists' => 'المستخدم المحدد غير موجود بالنظام.',
            'user_id.exists' => 'المستخدم المحدد غير موجود بالنظام.',
            'conversation_id.exists' => 'المحادثة المحددة غير موجودة.',
        ];
    }
}
