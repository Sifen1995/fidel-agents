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
        $payload = array_merge([
            'request_id' => null,
            'subject' => 'Unknown',
            'grade_level' => 'Unknown',
            'problem' => '',
            'steps' => [],
            'final_answer' => '',
            'learning_tip' => '',
        ], $payload);

        if (empty($payload['steps']) || !is_array($payload['steps'])) {
            $payload['steps'] = ['See final answer.'];
        }

        $payload['steps'] = array_values(array_map(function ($step) {
            return is_string($step) ? $step : (is_array($step) ? json_encode($step) : (string) $step);
        }, $payload['steps']));

        if (empty($payload['final_answer'])) {
            $payload['final_answer'] = 'No answer provided.';
        }
        if (empty($payload['problem'])) {
            $payload['problem'] = 'Unable to extract problem.';
        }
        if (empty($payload['learning_tip'])) {
            $payload['learning_tip'] = 'Review the steps above.';
        }

        $rules = [
            'subject' => 'required|string',
            'grade_level' => 'required|string',
            'problem' => 'required|string',
            'steps' => 'required|array|min:1',
            'steps.*' => 'required|string',
            'final_answer' => 'required|string',
            'learning_tip' => 'required|string',
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
