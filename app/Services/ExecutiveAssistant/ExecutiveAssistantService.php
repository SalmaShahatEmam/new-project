<?php

namespace App\Services\ExecutiveAssistant;

use App\Models\AiChatMessage;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExecutiveAssistantService
{
    public const SYSTEM_INSTRUCTION = <<<EOT
أنت "المساعد الذكي لإدارة المشاريع" في منظومة رتال للتطوير العمراني (Retal System).
مهمتك الأساسية هي مساعدة مديري المشاريع والمهندسين التنفيذيين بالإجابة على استفساراتهم وتلخيص المواقف الميدانية استناداً إلى البيانات الحقيقية فقط.

القواعد الصارمة للرد:
1. اللغة والأسلوب: تحدث باللغة العربية الفصحى المعاصرة، بأسلوب تنفيذي موجز، مباشر، وخالٍ من الإنشاء والعبارات الترحيبية المفرطة.
2. استخدام الأدوات (Tools): عندما يسأل المستخدم عن مشروع، مواد، مقاولين، معدات، أو أرقام، استدعِ الدالة المناسبة فوراً دون تخمين أي رقم من عندك.
3. الأمانة في البيانات: اعتمد كلياً على البيانات المرجعة من قاعدة البيانات. إذا كان هناك نقص في البيانات أو لم تجد سجلاً، اذكر ذلك للمستخدم بوضوح ومهنية.
4. هيكل الرد التنفيذي:
   - ملخص الموقف العام (نسبة الإنجاز والحالة).
   - البنود الحرجة والمعوقات (إن وجدت) في (المواد، المعدات، العمالة، المقاولين).
   - التوصيات التنفيذية المقترحة.
EOT;

    public function __construct(
        protected GeminiClientService $geminiClient,
        protected ExecutiveAssistantToolRegistry $toolRegistry,
        protected ExecutiveAssistantQueryService $queryService
    ) {
    }

    /**
     * Process user message, run Gemini tool-calling loop, and return executive response.
     *
     * @param User $user
     * @param string $message
     * @param string|null $conversationId
     * @return array
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function handleChat(User $user, string $message, ?string $conversationId = null): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException(__('Message cannot be empty.'));
        }

        // 1. Resolve or create conversation
        $conversation = $this->resolveConversation($user, $conversationId, $message);

        // 2. Persist user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // 3. Security check: prohibit mutations
        if ($this->isForbiddenMutation($message)) {
            $reply = 'المساعد الذكي مخصص للاستعلام وتلخيص المواقف التنفيذية فقط، ولا يملك صلاحية إنشاء البيانات أو تعديلها أو حذفها.';
            
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $reply,
                'structured_data' => null,
            ]);
            $conversation->touch();

            return [
                'success' => true,
                'reply' => $reply,
                'toolExecuted' => null,
                'conversation_id' => $conversation->id,
            ];
        }

        // 4. Initial call to Gemini with tools
        $tools = $this->toolRegistry->getTools();
        $initialContents = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $message],
                ],
            ],
        ];

        try {
            $initialResponse = $this->geminiClient->generateContent(
                contents: $initialContents,
                systemInstruction: self::SYSTEM_INSTRUCTION,
                tools: $tools
            );
        } catch (Throwable $e) {
            Log::error('Gemini initial call error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw new RuntimeException('تعذر الاتصال بخدمة الذكاء الاصطناعي (Gemini). برجاء التحقق من إعدادات الربط وحالة المفتاح.');
        }

        // 5. Check if Gemini invoked a function call
        $functionCall = $this->geminiClient->extractFunctionCall($initialResponse);

        if ($functionCall !== null) {
            $toolName = $functionCall['name'];
            $toolArgs = $functionCall['args'];
            $callId = $functionCall['id'] ?? null;

            // Execute the tool safely against the local database
            $toolResult = $this->executeTool($user, $toolName, $toolArgs);

            // Turn 2: Echo model turn including thoughtSignature & functionCall
            $modelTurn = data_get($initialResponse, 'candidates.0.content', [
                'role' => 'model',
                'parts' => [
                    [
                        'functionCall' => [
                            'name' => $toolName,
                            'args' => (object) $toolArgs,
                        ],
                    ],
                ],
            ]);

            // Turn 3: Function response turn (role is 'user' for Gemini REST API v1beta)
            $funcResponse = [
                'name' => $toolName,
                'response' => [
                    'name' => $toolName,
                    'content' => $toolResult,
                ],
            ];
            if ($callId) {
                $funcResponse['id'] = $callId;
            }

            $followUpContents = [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $message],
                    ],
                ],
                $modelTurn,
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'functionResponse' => $funcResponse,
                        ],
                    ],
                ],
            ];

            try {
                $finalResponse = $this->geminiClient->generateContent(
                    contents: $followUpContents,
                    systemInstruction: self::SYSTEM_INSTRUCTION,
                    tools: $tools
                );

                $finalReply = $this->geminiClient->extractText($finalResponse);
                if ($finalReply === '') {
                    $finalReply = 'تم استرداد البيانات من النظام بنجاح.';
                }
            } catch (Throwable $e) {
                Log::error('Gemini tool response interpretation error', [
                    'error' => $e->getMessage(),
                    'tool' => $toolName,
                ]);
                $finalReply = 'تم تنفيذ الاستعلام، ولكن حدث خطأ في صياغة التلخيص التنفيذي: ' . json_encode($toolResult, JSON_UNESCAPED_UNICODE);
            }

            // Persist assistant message with structured data
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $finalReply,
                'structured_data' => $toolResult,
                'metadata' => [
                    'tool_executed' => $toolName,
                    'tool_args' => $toolArgs,
                ],
            ]);
            $conversation->touch();

            return [
                'success' => true,
                'reply' => $finalReply,
                'toolExecuted' => $toolName,
                'conversation_id' => $conversation->id,
            ];
        }

        // 6. Direct conversational response without tool
        $directReply = $this->geminiClient->extractText($initialResponse);
        if ($directReply === '') {
            $directReply = 'أهلاً بك، أنا المساعد الذكي لإدارة المشاريع في منظومة رتال. كيف يمكنني مساعدتك اليوم بخصوص المشاريع أو المواد أو الجداول الزمنية؟';
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $directReply,
            'structured_data' => null,
        ]);
        $conversation->touch();

        return [
            'success' => true,
            'reply' => $directReply,
            'toolExecuted' => null,
            'conversation_id' => $conversation->id,
        ];
    }

    /**
     * Dispatch tool call to query service.
     */
    protected function executeTool(User $user, string $toolName, array $args): array
    {
        return match ($toolName) {
            'get_project_executive_status' => $this->queryService->getProjectExecutiveStatus($user, $args),
            'check_material_stock_and_quantities' => $this->queryService->checkMaterialStockAndQuantities($user, $args),
            'query_subcontractor_and_milestone_delays' => $this->queryService->querySubcontractorAndMilestoneDelays($user, $args),
            'get_orders_and_approvals_status' => $this->queryService->getOrdersAndApprovalsStatus($user, $args),
            default => [
                'found' => false,
                'message' => "الأداة '{$toolName}' غير مدعومة في النظام.",
            ],
        };
    }

    /**
     * Check if message asks to mutate data.
     */
    protected function isForbiddenMutation(string $message): bool
    {
        $normalized = mb_strtolower($message);
        
        return (bool) preg_match(
            '/\b(create|insert|update|delete|drop|alter|truncate|destroy)\b|(?:أضف|اضف|احذف|حذف|عدّل|عدل|غيّر|غير|امسح|أنشئ|انشئ)\s+.{0,50}(?:مشروع|مادة|سعر|طلب|مستخدم|موقع)/u',
            $normalized
        );
    }

    /**
     * Resolve existing conversation or create a new one.
     */
    protected function resolveConversation(User $user, ?string $conversationId, string $message): AiConversation
    {
        if (!empty($conversationId)) {
            $conversation = AiConversation::query()->findOrFail($conversationId);
            if ((int) $conversation->user_id !== (int) $user->id) {
                throw new AuthorizationException(__('Forbidden: You do not own this conversation.'));
            }
            return $conversation;
        }

        $title = Str::limit($message, 45, '...');

        return AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => $title ?: 'محادثة المساعد الذكي',
            'metadata' => [
                'type' => 'executive_assistant',
                'model' => config('services.gemini.model', 'gemini-1.5-flash'),
            ],
        ]);
    }
}
