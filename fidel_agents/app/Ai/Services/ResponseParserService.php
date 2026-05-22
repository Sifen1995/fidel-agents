<?php

namespace App\Ai\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResponseParserService
{
    public function parse(string $raw): array
    {
        $raw = trim($raw);
        $payload = $this->decodeJson($raw);

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['response' => ['Unable to parse AI response as valid JSON.']]);
        }

        return $payload;
    }

    public function validate(array $payload): array
    {
        $rules = [
            'request_id' => 'required|string',
            'subject' => 'required|string',
            'grade_level' => 'required|string',
            'problem' => 'required|string',
            'steps' => 'required|array|min:1',
            'steps.*' => 'required|string',
            'final_answer' => 'required|string',
            'learning_tip' => 'required|string',
            'ocr_confidence' => 'nullable|numeric',
            'llm_confidence' => 'nullable|numeric',
            'processed_offline' => 'nullable|boolean',
            'ocr_provider' => 'nullable|string',
            'ocr_model' => 'nullable|string',
            'llm_provider' => 'nullable|string',
            'llm_model' => 'nullable|string',
        ];

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $payload;
    }

    private function decodeJson(string $raw): mixed
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $raw, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
