<?php

namespace App\Ai\Agents;

class ExamPrepAgent
{
    public function handle(array $input): array
    {
        return [
            'summary' => 'Exam preparation plan generated.',
            'exam' => $input['exam'] ?? $input['exam_name'] ?? 'general exam',
            'priorities' => $input['weak_topics'] ?? ['review core concepts'],
            'recommendations' => [
                'Create a study schedule',
                'Practice with past papers',
                'Review weak areas first',
            ],
            'confidence' => 0.7,
        ];
    }
}
