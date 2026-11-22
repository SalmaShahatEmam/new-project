<?php

namespace App\Services\Assistant;

use App\Models\AiChatMessage;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Throwable;

class AiChatService
{
    public function __construct(
        private AssistantService $assistantService,
        private AiQuerySecurityValidator $securityValidator
    ) {
    }

    /**
     * Send a message to AI assistant and persist conversational exchange.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function sendMessage(User $user, string $message, ?string $conversationId = null): array
    {
        // 1. Security validation on input
        $this->securityValidator->validateInput($message);

        // 2. Resolve or create conversation with strict ownership check
        $conversation = $this->resolveConversation($user, $conversationId, $message);

        // 3. Persist user message
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // 4. Check for forbidden mutations
        if ($this->securityValidator->isForbiddenMutation($message)) {
            $answer = 'المساعد مخصص للاستعلام فقط ولا يمكنه إنشاء البيانات أو تعديلها أو حذفها.';
            $structuredResults = [];

            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => $answer,
                'structured_data' => $structuredResults,
            ]);

            $conversation->touch();

            return [
                'conversation_id' => $conversation->id,
                'answer' => $answer,
                'results' => $structuredResults,
                'type' => 'text',
            ];
        }

        // 5. Query AI Assistant Service
        try {
            $aiResponse = $this->assistantService->answer($message);
            $answer = $aiResponse['answer'] ?? '';
            $rawResults = $aiResponse['sources'] ?? [];
        } catch (Throwable $e) {
            $answer = 'عذرًا، حدث خطأ أثناء معالجة الطلب من مزود الذكاء الاصطناعي.';
            $rawResults = [];
        }

        // 6. Security sanitization of results
        $sanitizedResults = $this->securityValidator->sanitizeResults($rawResults);

        // 7. Persist assistant message
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'structured_data' => $sanitizedResults,
        ]);

        $conversation->touch();

        return [
            'conversation_id' => $conversation->id,
            'answer' => $answer,
            'results' => $sanitizedResults,
            'type' => 'text',
        ];
    }

    /**
     * Create a new conversation for user.
     */
    public function createConversation(User $user, ?string $title = null, ?array $metadata = null): AiConversation
    {
        return AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => $title ? trim($title) : 'New Conversation',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get conversation and chronological message history with strict ownership check.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function getConversationHistory(User $user, string $conversationId): AiConversation
    {
        $conversation = AiConversation::query()->findOrFail($conversationId);

        $this->verifyOwnership($user, $conversation);

        $conversation->load('messages');

        return $conversation;
    }

    /**
     * List user conversations with pagination and metadata.
     */
    public function listUserConversations(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return AiConversation::query()
            ->where('user_id', $user->id)
            ->withCount('messages')
            ->with(['messages' => fn ($query) => $query->latest('created_at')->limit(1)])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    /**
     * Delete conversation with strict ownership check.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function deleteConversation(User $user, string $conversationId): bool
    {
        $conversation = AiConversation::query()->findOrFail($conversationId);

        $this->verifyOwnership($user, $conversation);

        // Messages cascade delete automatically via foreign key or relationship
        $conversation->messages()->delete();

        return (bool) $conversation->delete();
    }

    /**
     * Rename conversation title with strict ownership check.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function renameConversation(User $user, string $conversationId, string $newTitle): AiConversation
    {
        $conversation = AiConversation::query()->findOrFail($conversationId);

        $this->verifyOwnership($user, $conversation);

        $conversation->update([
            'title' => trim($newTitle),
        ]);

        return $conversation->fresh();
    }

    /**
     * Clear messages within a conversation while preserving conversation record.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function clearMessages(User $user, string $conversationId): bool
    {
        $conversation = AiConversation::query()->findOrFail($conversationId);

        $this->verifyOwnership($user, $conversation);

        $conversation->messages()->delete();
        $conversation->touch();

        return true;
    }

    /**
     * Resolve conversation by ID or create new if null.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    private function resolveConversation(User $user, ?string $conversationId, string $initialMessage): AiConversation
    {
        if ($conversationId !== null && $conversationId !== '') {
            $conversation = AiConversation::query()->findOrFail($conversationId);
            $this->verifyOwnership($user, $conversation);
            return $conversation;
        }

        $title = Str::limit(trim($initialMessage), 50, '...');

        return $this->createConversation($user, $title);
    }

    /**
     * Verify conversation belongs to authenticated user (IDOR prevention).
     *
     * @throws AuthorizationException
     */
    private function verifyOwnership(User $user, AiConversation $conversation): void
    {
        if ((int) $conversation->user_id !== (int) $user->id) {
            throw new AuthorizationException(__('You are not authorized to access this conversation.'));
        }
    }
}
