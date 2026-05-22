<?php

namespace App\Ai\Services;

use App\Models\HomeworkRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class HomeworkRequestPersistor
{
    public function storeHomeworkRequest(array $result, array $input): HomeworkRequest
    {
        $userId = $this->resolveUserId($input);

        return HomeworkRequest::create([
            'user_id' => $userId,
            'role_name' => (string) ($input['role_name'] ?? ''),
            'request' => $this->buildRequestText($result),
            'response' => $this->buildResponseText($result),
            'llm_confidence' => $this->resolveConfidence($result, $input),
        ]);
    }

    protected function resolveUserId(array $input): string
    {
        if (! empty($input['user_id'] ?? null)) {
            return (string) $input['user_id'];
        }

        if (Auth::check()) {
            return (string) Auth::id();
        }

        throw new InvalidArgumentException('Unable to persist homework request without a valid user_id.');
    }

    protected function buildRequestText(array $result): string
    {
        return trim((string) ($result['problem'] ?? ''));
    }

    protected function buildResponseText(array $result): string
    {
        $steps = Arr::get($result, 'steps', []);
        $finalAnswer = trim((string) ($result['final_answer'] ?? ''));
        $learningTip = trim((string) ($result['learning_tip'] ?? ''));

        $stepText = '';
        if (is_array($steps)) {
            $stepText = implode("\n", array_map('trim', $steps));
        }

        $response = trim($stepText);
        if ($finalAnswer !== '') {
            $response .= "\n\nFinal Answer: {$finalAnswer}";
        }

        if ($learningTip !== '') {
            $response .= "\n\nLearning Tip: {$learningTip}";
        }

        return trim($response);
    }

    protected function resolveConfidence(array $result, array $input): float
    {
        if (isset($result['llm_confidence'])) {
            return (float) $result['llm_confidence'];
        }

        return isset($input['llm_confidence']) ? (float) $input['llm_confidence'] : 0.0;
    }
}
