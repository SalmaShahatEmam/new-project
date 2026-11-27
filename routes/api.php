<?php

use App\Http\Controllers\Api\V1\AIChatController;
use App\Http\Controllers\Api\V1\ExecutiveAssistantController;
use App\Http\Controllers\Api\V1\MaterialImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'throttle:10,1'])->post(
    'materials/ai-import',
    [MaterialImportController::class, 'store']
);

// Executive Project Assistant Chat Endpoint (also supports voice-assistant alias)
Route::post('executive-assistant/chat', [ExecutiveAssistantController::class, 'chat']);
Route::post('voice-assistant/chat', [ExecutiveAssistantController::class, 'chat']);
Route::post('v1/executive-assistant/chat', [ExecutiveAssistantController::class, 'chat']);

Route::middleware(['auth:api'])->prefix('v1/ai/chat')->group(function () {
    Route::post('/', [AIChatController::class, 'sendMessage']);
    Route::post('sendmessage', [AIChatController::class, 'sendMessage']);
    Route::post('conversations', [AIChatController::class, 'createConversation']);
    Route::get('conversations', [AIChatController::class, 'listConversations']);
    Route::get('conversations/{id}', [AIChatController::class, 'getConversation']);
    Route::delete('conversations/{id}', [AIChatController::class, 'deleteConversation']);
    Route::patch('conversations/{id}', [AIChatController::class, 'renameConversation']);
    Route::delete('conversations/{id}/messages', [AIChatController::class, 'clearMessages']);
});
