<?php

declare(strict_types=1);

namespace App\Ai\Services;

use App\Models\ExamPrep;
use InvalidArgumentException;

class ExamPrepPersistor
{
    public function storeExamPrepRequest(array $result, array $input): ExamPrep
    {
        $studentId = trim((string) ($input['student_id'] ?? ''));

        if ($studentId === '') {
            throw new InvalidArgumentException('Unable to persist exam prep without a valid student_id.');
        }

        return ExamPrep::create([
            'student_id' => $studentId,
            'grade_level' => (string) ($input['grade_level'] ?? ''),
            'exam_subject' => (string) ($input['exam_subject'] ?? ''),
            'exam_date' => (string) ($input['exam_date'] ?? ''),
            'days_remaining' => (int) ($input['days_remaining'] ?? 0),
            'request' => $this->buildRequestText($input),
            'response' => $this->buildResponseText($result),
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
            'exam_subject' => $input['exam_subject'] ?? null,
            'exam_date' => $input['exam_date'] ?? null,
            'days_remaining' => $input['days_remaining'] ?? null,
            'syllabus_topics' => $input['syllabus_topics'] ?? [],
            'student_scores' => $input['student_scores'] ?? [],
            'completed_videos' => $input['completed_videos'] ?? [],
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
