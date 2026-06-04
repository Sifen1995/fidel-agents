<?php

declare(strict_types=1);

namespace App\Ai\Services;

use App\Models\RecommendRequest;
use InvalidArgumentException;

class RecommendRequestPersistor
{
    public function storeRecommendRequest(array $result, array $input): RecommendRequest
    {
        $studentId = trim((string) ($input['student_id'] ?? ''));

        if ($studentId === '') {
            throw new InvalidArgumentException('Unable to persist recommendation without a valid student_id.');
        }

        return RecommendRequest::create([
            'student_id' => $studentId,
            'grade_level' => (string) ($input['grade_level'] ?? ''),
            'request' => $this->buildRequestText($input),
            'response' => $this->buildResponseText($result),
            'focus_subject' => (string) ($result['focus_subject'] ?? ''),
            'llm_provider' => (string) ($result['llm_provider'] ?? ''),
            'llm_model' => (string) ($result['llm_model'] ?? ''),
            'processed_offline' => (bool) ($result['processed_offline'] ?? false),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function buildRequestText(array $input): string
    {
        $payload = [
            'student_id' => $input['student_id'] ?? null,
            'grade_level' => $input['grade_level'] ?? null,
            'quiz_results' => $input['quiz_results'] ?? [],
            'completed_videos' => $input['completed_videos'] ?? [],
            'content_catalogue' => $input['content_catalogue'] ?? [],
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function buildResponseText(array $result): string
    {
        return json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
