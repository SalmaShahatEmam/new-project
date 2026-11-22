<?php

namespace App\Services\Assistant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class AiQuerySecurityValidator
{
    /**
     * Patterns that indicate dangerous or destructive SQL / mutations.
     */
    private const MUTATION_PATTERNS = [
        '/\b(drop|truncate|delete|insert|update|alter|create|replace|grant|revoke|rename|upsert)\b/i',
        '/(?:ضيف|أضف|احذف|حذف|غيّر|غير|عدّل|عدل|خلي|اجعل|عيّن|عين|اعتمد|ارفض|وافق)\s+.{0,50}(?:سعر|دور|صلاح|مشروع|مستخدم|مهندس|مورد|resource|role|permission|project|user|price)/u',
    ];

    /**
     * Patterns that indicate prompt injection or system secrets extraction.
     */
    private const INJECTION_PATTERNS = [
        '/\b(ignore\s+(all\s+)?(previous|prior)\s+instructions?)\b/i',
        '/\b(system\s+prompt|reveal\s+prompt|show\s+prompt|dump\s+database|eval\(|exec\()\b/i',
        '/\b(password_hash|remember_token|personal_access_tokens|fcm_token|\.env|db_password)\b/i',
    ];

    /**
     * Validate user prompt before processing.
     *
     * @throws ValidationException
     */
    public function validateInput(string $message): void
    {
        $sanitized = trim($message);

        if ($sanitized === '') {
            throw ValidationException::withMessages([
                'message' => ['The message content cannot be empty.'],
            ]);
        }

        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $sanitized)) {
                throw ValidationException::withMessages([
                    'message' => ['Security restriction: The submitted query violates security policies.'],
                ]);
            }
        }
    }

    /**
     * Verify whether a query contains destructive mutation intent.
     */
    public function isForbiddenMutation(string $query): bool
    {
        foreach (self::MUTATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize results before returning to client so no sensitive attributes
     * (passwords, tokens, OTPs, internal secrets) are leaked.
     */
    public function sanitizeResults(mixed $results): mixed
    {
        if (!is_array($results)) {
            return $results;
        }

        $forbiddenKeys = [
            'password',
            'remember_token',
            'fcm_token',
            'token',
            'latest_o_t_p_token',
            'sms',
            'otp',
            'access_token',
            'api_key',
        ];

        $cleaned = [];
        foreach ($results as $key => $value) {
            if (in_array(strtolower((string) $key), $forbiddenKeys, true)) {
                continue;
            }

            if (is_array($value)) {
                $cleaned[$key] = $this->sanitizeResults($value);
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }
}
