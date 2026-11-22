<?php

namespace App\Services\ExecutiveAssistant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiClientService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
        $this->model = (string) config('services.gemini.model', env('GEMINI_MODEL', 'gemini-1.5-flash'));
        $this->baseUrl = (string) config('services.gemini.base_url', env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'));
        $this->timeout = (int) config('services.gemini.timeout', env('GEMINI_TIMEOUT', 45));
    }

    /**
     * Set explicit API key (useful for tests or dynamic config).
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Send generateContent request to Gemini API.
     *
     * @param array $contents
     * @param string|null $systemInstruction
     * @param array|null $tools
     * @return array
     */
    public function generateContent(array $contents, ?string $systemInstruction = null, ?array $tools = null): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException(__('GEMINI_API_KEY is not configured in environment or services.php.'));
        }

        $endpoint = rtrim($this->baseUrl, '/') . "/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => $contents,
        ];

        if (!empty($systemInstruction)) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ];
        }

        if (!empty($tools)) {
            $payload['tools'] = [
                [
                    'function_declarations' => $tools,
                ],
            ];
            $payload['tool_config'] = [
                'function_calling_config' => [
                    'mode' => 'AUTO',
                ],
            ];
        }

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->post($endpoint, $payload);

        if (!$response->successful()) {
            $errorBody = $response->json() ?? [];
            $errorMessage = data_get($errorBody, 'error.message', 'HTTP error ' . $response->status());
            Log::error('Gemini API call failed', [
                'status' => $response->status(),
                'error' => $errorBody,
            ]);
            throw new RuntimeException("Gemini API error ({$response->status()}): {$errorMessage}");
        }

        $data = $response->json();
        return $data ?? [];
    }

    /**
     * Extract function call from Gemini response if present.
     *
     * @param array $response
     * @return array|null Returns ['name' => string, 'args' => array] or null
     */
    public function extractFunctionCall(array $response): ?array
    {
        $candidate = data_get($response, 'candidates.0.content.parts.0');
        if (isset($candidate['functionCall'])) {
            return [
                'name' => (string) data_get($candidate, 'functionCall.name'),
                'args' => (array) data_get($candidate, 'functionCall.args', []),
                'id' => data_get($candidate, 'functionCall.id'),
            ];
        }

        // Sometimes parts have multiple items or functionCall in subsequent part
        $parts = data_get($response, 'candidates.0.content.parts', []);
        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                return [
                    'name' => (string) data_get($part, 'functionCall.name'),
                    'args' => (array) data_get($part, 'functionCall.args', []),
                    'id' => data_get($part, 'functionCall.id'),
                ];
            }
        }

        return null;
    }

    /**
     * Extract text answer from Gemini response.
     *
     * @param array $response
     * @return string
     */
    public function extractText(array $response): string
    {
        $parts = data_get($response, 'candidates.0.content.parts', []);
        $texts = [];

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }

        return trim(implode("\n", $texts));
    }
}
