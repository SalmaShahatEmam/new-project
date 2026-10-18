<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Enum\UserTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Assistant\AssistantService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AssistantController extends Controller
{
    public function ask(Request $request, AssistantService $assistant): JsonResponse
    {
        if ($request->user()?->type !== UserTypeEnum::ADMIN) {
            throw new AuthorizationException(__('Unauthorized'));
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
//return response()->json($assistant->answer($validated['question']));
        try {
            return response()->json($assistant->answer($validated['question']));
        } catch (Throwable $exception) {
            $errorId = (string) Str::uuid();
            Log::channel('single')->error('Admin assistant request failed.', [
                'error_id' => $errorId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'The assistant is unavailable. Check its provider configuration and try again.',
                'error_id' => $errorId,
            ], 503);
        }
    }
}