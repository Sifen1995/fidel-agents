<?php

namespace App\Ai\Agents;

class StudyRecommenderAgent
{
    public function handle(array $input): array
    {
        return [
            'recommendations' => [
                'Focus on: ' . implode(', ', (array) ($input['weak_subjects'] ?? $input['weak_topics'] ?? ['core subjects'])),
                'Use mixed review sessions for better retention.',
                'Balance videos, notes, and practice questions.',
            ],
            'grade_level' => $input['grade_level'] ?? 'unspecified',
            'confidence' => 0.6,
        ];
    }
}
