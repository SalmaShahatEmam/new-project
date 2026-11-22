<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExecutiveAssistant\ExecutiveChatRequest;
use App\Models\User;
use App\Services\ExecutiveAssistant\ExecutiveAssistantService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ExecutiveAssistantController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ExecutiveAssistantService $assistantService
    ) {
    }

    /**
     * Executive AI chat message endpoint.
     *
     * POST /api/executive-assistant/chat
     * POST /api/voice-assistant/chat (alias)
     * POST /api/v1/executive-assistant/chat (alias)
     */
    public function chat(ExecutiveChatRequest $request): JsonResponse
    {
        try {
            // 1. Resolve user: Sanctum auth token priority, fallback to userId in body
            $user = $request->user('api') ?? auth('api')->user() ?? $request->user();

            if (!$user) {
                $userId = $request->input('userId') ?? $request->input('user_id');
                if ($userId) {
                    $user = User::query()->find($userId);
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => __('غير مصرح: يجب تسجيل الدخول أو تمرير معرف مستخدم صالح (userId).'),
                ], 401);
            }

            // 2. Process message via ExecutiveAssistantService
            $result = $this->assistantService->handleChat(
                user: $user,
                message: $request->input('message'),
                conversationId: $request->input('conversation_id')
            );

            return response()->json([
                'success' => true,
                'reply' => $result['reply'],
                'toolExecuted' => $result['toolExecuted'],
                'conversation_id' => $result['conversation_id'],
            ], 200);

        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('غير مصرح لك بالوصول إلى هذه المحادثة أو البيانات.'),
            ], 403);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('السجل أو المحادثة المطلوبة غير موجودة.'),
            ], 404);

        } catch (Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('Executive AI Chat Error', [
                'error_id' => $errorId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : __('حدث خطأ أثناء معالجة الطلب في المساعد الذكي. يرجى المحاولة لاحقاً.'),
                'error_id' => $errorId,
            ], 500);
        }
    }
}
