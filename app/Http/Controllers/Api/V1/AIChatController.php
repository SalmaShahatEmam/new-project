<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\CreateConversationRequest;
use App\Http\Requests\Ai\RenameConversationRequest;
use App\Http\Requests\Ai\SendMessageRequest;
use App\Http\Resources\Ai\AiConversationDetailResource;
use App\Http\Resources\Ai\AiConversationResource;
use App\Services\Assistant\AiChatService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AIChatController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected AiChatService $aiChatService
    ) {
        $this->middleware('auth:api');
    }

    /**
     * Send a message / continue conversation.
     *
     * POST /api/v1/ai/chat
     */
    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
    try {
            $user = $request->user();
            $validated = $request->validated();

            $result = $this->aiChatService->sendMessage(
                user: $user,
                message: $validated['message'],
                conversationId: $validated['conversation_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation_id' => $result['conversation_id'],
                    'answer' => $result['answer'],
                    'results' => $result['results'],
                    'type' => $result['type'],
                ],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('Forbidden: You do not own this conversation.'),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        } catch (Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('AI Chat Error', [
                'error_id' => $errorId,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : __('The AI service encountered an error. Please try again later.'),
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * Create a new conversation.
     *
     * POST /api/v1/ai/chat/conversations
     */
    public function createConversation(CreateConversationRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $conversation = $this->aiChatService->createConversation(
                user: $user,
                title: $validated['title'] ?? null,
                metadata: $validated['metadata'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => __('Conversation created successfully.'),
                'data' => new AiConversationResource($conversation),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to create conversation.'),
            ], 500);
        }
    }

    /**
     * List user conversations with pagination.
     *
     * GET /api/v1/ai/chat/conversations
     */
    public function listConversations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = (int) $request->query('per_page', $request->query('limit', 15));
            $perPage = max(1, min($perPage, 50));

            $paginated = $this->aiChatService->listUserConversations($user, $perPage);

            return response()->json([
                'success' => true,
                'data' => AiConversationResource::collection($paginated->items()),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to retrieve conversations.'),
            ], 500);
        }
    }

    /**
     * Get conversation details and chronological history.
     *
     * GET /api/v1/ai/chat/conversations/{id}
     */
    public function getConversation(string $id): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $conversation = $this->aiChatService->getConversationHistory($user, $id);

            return response()->json([
                'success' => true,
                'data' => new AiConversationDetailResource($conversation),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('Forbidden: You do not own this conversation.'),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to retrieve conversation history.'),
            ], 500);
        }
    }

    /**
     * Delete a conversation.
     *
     * DELETE /api/v1/ai/chat/conversations/{id}
     */
    public function deleteConversation(string $id): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $this->aiChatService->deleteConversation($user, $id);

            return response()->json([
                'success' => true,
                'message' => __('Conversation deleted successfully.'),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('Forbidden: You do not own this conversation.'),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to delete conversation.'),
            ], 500);
        }
    }

    /**
     * Rename/update conversation title.
     *
     * PATCH /api/v1/ai/chat/conversations/{id}
     */
    public function renameConversation(RenameConversationRequest $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $conversation = $this->aiChatService->renameConversation($user, $id, $validated['title']);

            return response()->json([
                'success' => true,
                'message' => __('Conversation renamed successfully.'),
                'data' => new AiConversationResource($conversation),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('Forbidden: You do not own this conversation.'),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to rename conversation.'),
            ], 500);
        }
    }

    /**
     * Clear messages within a conversation while preserving the conversation.
     *
     * DELETE /api/v1/ai/chat/conversations/{id}/messages
     */
    public function clearMessages(string $id): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $this->aiChatService->clearMessages($user, $id);

            return response()->json([
                'success' => true,
                'message' => __('Conversation messages cleared successfully.'),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('Forbidden: You do not own this conversation.'),
            ], 403);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Conversation not found.'),
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to clear messages.'),
            ], 500);
        }
    }
}
