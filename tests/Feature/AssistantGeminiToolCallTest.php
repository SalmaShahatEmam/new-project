<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Assistant\AssistantService;
use App\Services\ExecutiveAssistant\ExecutiveAssistantQueryService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AssistantGeminiToolCallTest extends TestCase
{
    public function test_it_executes_a_gemini_tool_and_returns_the_grounded_follow_up(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'test-model',
            'services.gemini.base_url' => 'https://assistant.test/v1',
        ]);

        $user = new User();
        $user->id = 7;
        $toolResult = ['found' => true, 'project' => ['id' => 3, 'name' => 'Retal']];
        $queryService = Mockery::mock(ExecutiveAssistantQueryService::class);
        $queryService->shouldReceive('getProjectExecutiveStatus')
            ->once()
            ->with($user, ['project_name' => 'Retal'])
            ->andReturn($toolResult);
        $this->app->instance(ExecutiveAssistantQueryService::class, $queryService);

        Http::fakeSequence('https://assistant.test/v1/models/test-model:generateContent?key=test-key')
            ->push(['candidates' => [['content' => ['parts' => [[
                'functionCall' => [
                    'name' => 'get_project_executive_status',
                    'args' => ['project_name' => 'Retal'],
                    'id' => 'call-1',
                ],
            ]]]]])
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'Retal is on track.']]]]]]);

        $result = app(AssistantService::class)->answer('What is the status of Retal?', $user);

        $this->assertSame('Retal is on track.', $result['answer']);
        Http::assertSentCount(2);
        Http::assertSent(function ($request) use ($toolResult): bool {
            $contents = $request->data()['contents'] ?? [];

            return data_get($contents, '2.parts.0.functionResponse.name') === 'get_project_executive_status'
                && data_get($contents, '2.parts.0.functionResponse.id') === 'call-1'
                && data_get($contents, '2.parts.0.functionResponse.response') === $toolResult;
        });
    }

    public function test_it_does_not_expose_database_tools_without_an_authenticated_user(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'test-model',
            'services.gemini.base_url' => 'https://assistant.test/v1',
        ]);
        Http::fakeSequence('https://assistant.test/v1/models/test-model:generateContent?key=test-key')
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'I could not find enough information.']]]]]]);

        app(AssistantService::class)->answer('What is the project status?');

        Http::assertSent(fn ($request): bool => ! isset($request->data()['tools']));
    }
}
