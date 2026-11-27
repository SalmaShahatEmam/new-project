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
            $reply = '<p>⚠️ <strong>تنبيه:</strong> المساعد الذكي مخصص للاستعلام وتلخيص المواقف التنفيذية فقط، ولا يملك صلاحية إنشاء البيانات أو تعديلها أو حذفها.</p>';
            
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

            // Execute the tool safely against the local database
            $toolResult = $this->executeTool($user, $toolName, $toolArgs);

            // Synthesize database result into human-friendly, professional executive Arabic text
            $reply = $this->synthesizeResponse($message, $toolResult, $toolName);

            // Persist assistant message with natural text & structured data
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $reply,
                'structured_data' => is_array($toolResult) ? $toolResult : ['result' => $toolResult],
                'metadata' => [
                    'tool_executed' => $toolName,
                    'tool_args' => $toolArgs,
                ],
            ]);
            $conversation->touch();

            return [
                'success' => true,
                'reply' => $reply,
                'toolExecuted' => $toolName,
                'toolArgs' => $toolArgs,
                'result' => $toolResult,
                'data' => $toolResult,
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
            'result' => $directReply,
            'reply' => $directReply,
            'toolExecuted' => null,
            'conversation_id' => $conversation->id,
        ];
    }

    /**
     * Synthesize database tool result into human-friendly, professional executive Arabic text.
     */
    protected function synthesizeResponse(string $question, mixed $toolResult, string $toolName): string
    {
        $systemInstruction = <<<EOT
أنت "المساعد الذكي لإدارة المشاريع" في منظومة رتال للتطوير العمراني (Retal System).
مهمتك الأساسية هي مساعدة العميل ومديري المشاريع بإعادة صياغة البيانات المسترجعة من قاعدة البيانات إلى تقرير تنفيذي وإجابة واضحة، مهنية، مفصلة وشاملة باللغة العربية ومنسقة بشكل جذاب ومفهوم للفرونت إند (Frontend).

القواعد الصارمة لصياغة وتنسيق الرد:
1. التنسيق بهيكل HTML نظيف للواجهات:
   - قم بصياغة الإجابة بالكامل باستخدام وسوم HTML نقية وتعبيرية جاهزة للعرض المباشر في واجهات وتطبيقات الفرونت إند (Frontend UI).
   - استخدم الوسوم التالية: <p>, <strong>, <span>, <ul>, <ol>, <li>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <br>, <code>.
   - نسق القوائم والتفاصيل باستخدام <ol> أو <ul> مع <li>، واستخدم <strong> لتوضيح العناوين والأسماء والتكاليف، و <span> لتوضيح الحالات وتواريخ البدء والتسليم.
   - لا تضع أبداً وسوم المستند الكامل مثل <html> أو <head> أو <body> أو <!DOCTYPE> أو <style>.
   - لا تغلف الرد إطلاقاً بعلامات كود الماركداون (ممنوع استخدام ```html أو ```). يجب أن يكون الرد نص HTML مباشراً ونظيفاً.
2. الشمول والتفصيل الدائم:
   - لا تكتفِ أبداً بذكر رقم مجرد أو جملة مقتضبة.
   - إذا كانت البيانات تحتوي على قائمة مشاريع أو مواد أو مقاولين أو أسعار، اعرض التفاصيل المتاحة كاملة (أسماء المشاريع، الحالات، تاريخ البدء، موعد التسليم/الانتهاء، المهندسين المسؤولين، التكلفة، والكميات) في نقاط منسقة ومنظمة ومرقمة.
3. الدقة والأمانة: اعتمد حصراً على بيانات قاعدة البيانات المعطاة لك. لا تخترع أي معلومة أو تاريخ أو اسم من عندك.
4. الربط الميداني: اذكر دائماً التفاصيل المرتبطة (مثل المشروع التابع له الخامة، أو المهندسين المسؤولين عن المشروع، أو حالة أوامر التوريد والتكلفة).
5. تجنب المصطلحات البرمجية: لا تذكر كلمات مثل JSON أو null أو أسماء الجداول التقنية.
EOT;

        $prompt = "سؤال المستخدم:\n{$question}\n\nبيانات قاعدة البيانات المسترجعة:\n" . json_encode($toolResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\nالمطلوب: أعد صياغة هذه البيانات في رد تنفيذي مفصل وشامل وسلس منسق بوسوم HTML المناسبة للفرونت إند يوضح كافة التفاصيل المتاحة للعميل (الأسماء، الحالات، التواريخ، المهندسين، والتكاليف) دون اختصار مخل.";

        try {
            $response = $this->geminiClient->generateContent(
                contents: [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                systemInstruction: $systemInstruction,
                tools: null
            );

            $reply = $this->geminiClient->extractText($response);

            if (!empty($reply)) {
                // Strip markdown code fences if generated
                $reply = preg_replace('/^```(?:html)?\s*/i', '', $reply);
                $reply = preg_replace('/\s*```$/', '', $reply);
                $reply = trim($reply);

                return $reply;
            }
        } catch (Throwable $e) {
            Log::warning('Failed to synthesize assistant response via Gemini', [
                'error' => $e->getMessage(),
                'tool' => $toolName,
            ]);
        }

        // Fallback for projects list if Gemini synthesis is unavailable
        if (isset($toolResult['projects']) && is_array($toolResult['projects'])) {
            $total = $toolResult['total_count'] ?? count($toolResult['projects']);
            $html = "<p>يبلغ إجمالي المشاريع المسجلة <strong>{$total} مشاريع</strong>، وإليك تفاصيلها المتاحة في المنظومة:</p>";
            $html .= "<ol>";
            foreach ($toolResult['projects'] as $p) {
                $pName = htmlspecialchars($p['name'] ?? 'مشروع بدون اسم');
                $pStatus = htmlspecialchars($p['status'] ?? 'غير محدد');
                $pStart = !empty($p['start_date']) ? " | <strong>تاريخ البدء:</strong> {$p['start_date']}" : "";
                $pEnd = !empty($p['end_date']) ? " | <strong>تاريخ التسليم:</strong> {$p['end_date']}" : "";
                $engineersList = '';
                if (!empty($p['engineers']) && is_array($p['engineers'])) {
                    $engNames = array_filter(array_column($p['engineers'], 'name'));
                    if (!empty($engNames)) {
                        $engineersList = " | <strong>المهندسون:</strong> " . htmlspecialchars(implode('، ', $engNames));
                    }
                }
                $cost = !empty($p['total_cost']) ? " | <strong>التكلفة:</strong> " . number_format((float) $p['total_cost'], 2) . " ر.س" : "";
                $html .= "<li><strong>{$pName}</strong> (<span>الحالة: {$pStatus}</span>){$pStart}{$pEnd}{$engineersList}{$cost}</li>";
            }
            $html .= "</ol>";
            return $html;
        }

        if (is_scalar($toolResult)) {
            return "<p><strong>النتيجة:</strong> " . htmlspecialchars((string) $toolResult) . "</p>";
        }

        return '<p>تم استرداد بيانات الاستعلام بنجاح من النظام.</p>';
    }

    /**
     * Dispatch tool call to query service.
     */
    protected function executeTool(User $user, string $toolName, array $args): mixed
    {
        return match ($toolName) {
            'count_projects' => $this->queryService->countProjects($user, $args),
            'get_project_executive_status' => $this->queryService->getProjectExecutiveStatus($user, $args),
            'check_material_stock_and_quantities' => $this->queryService->checkMaterialStockAndQuantities($user, $args),
            'query_subcontractor_and_milestone_delays' => $this->queryService->querySubcontractorAndMilestoneDelays($user, $args),
            'get_orders_and_approvals_status' => $this->queryService->getOrdersAndApprovalsStatus($user, $args),
            'get_employees_and_staff_details' => $this->queryService->getEmployeesAndStaffDetails($user, $args),
            'get_sites_and_locations_summary' => $this->queryService->getSitesAndLocationsSummary($user, $args),
            'get_combined_and_aggregated_reports' => $this->queryService->getCombinedAndAggregatedReports($user, $args),
            'get_suppliers_and_procurement_details' => $this->queryService->getSuppliersAndProcurementDetails($user, $args),
            'get_resource_prices_and_market_rates' => $this->queryService->getResourcePricesAndMarketRates($user, $args),
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
