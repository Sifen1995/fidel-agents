<?php

declare(strict_types=1);

namespace App\Ai\Services;

class RuleEngineService
{
    private const WEAK_THRESHOLD = 60.0;

    /**
     * Analyse quiz performance and return the weakest subject to focus on.
     *
     * @param  list<array{subject?: string, score?: float|int, taken_at?: string}>  $quizResults
     * @param  list<string|mixed>  $completedVideos
     * @return array{subject: string, score: float, priority_rank: int, note?: string}
     */
    public function analyse(array $quizResults, array $completedVideos): array
    {
        unset($completedVideos);

        $averages = $this->averageScorePerSubject($quizResults);

        if ($averages === []) {
            return [
                'subject' => 'General',
                'score' => 0.0,
                'priority_rank' => 1,
                'note' => 'No quiz data available.',
            ];
        }

        usort($averages, fn (array $a, array $b): int => $a['score'] <=> $b['score']);

        $belowThreshold = array_values(array_filter(
            $averages,
            fn (array $row): bool => $row['score'] < self::WEAK_THRESHOLD
        ));

        if ($belowThreshold !== []) {
            $weakest = $belowThreshold[0];

            return [
                'subject' => $weakest['subject'],
                'score' => $weakest['score'],
                'priority_rank' => 1,
            ];
        }

        $lowest = $averages[0];

        return [
            'subject' => $lowest['subject'],
            'score' => $lowest['score'],
            'priority_rank' => 1,
            'note' => 'Student is performing well overall; focusing on the lowest-scoring subject for continued growth.',
        ];
    }

    /**
     * @param  list<array{subject?: string, score?: float|int}>  $quizResults
     * @return list<array{subject: string, score: float}>
     */
    private function averageScorePerSubject(array $quizResults): array
    {
        $totals = [];

        foreach ($quizResults as $row) {
            if (! is_array($row)) {
                continue;
            }

            $subject = trim((string) ($row['subject'] ?? ''));
            if ($subject === '') {
                continue;
            }

            if (! isset($totals[$subject])) {
                $totals[$subject] = ['total' => 0.0, 'count' => 0];
            }

            $totals[$subject]['total'] += (float) ($row['score'] ?? 0);
            $totals[$subject]['count']++;
        }

        $averages = [];
        foreach ($totals as $subject => $data) {
            $averages[] = [
                'subject' => $subject,
                'score' => round($data['total'] / $data['count'], 2),
            ];
        }

        return $averages;
    }

    /**
     * Rank exam syllabus topics by study priority.
     *
     * priority_score = weight × (100 - score). Higher score = needs more study time.
     *
     * @param  list<array{topic?: string, weight?: float|int}>  $syllabusTopics
     * @param  list<array{topic?: string, score?: float|int}>  $studentScores
     * @return list<array{topic: string, weight: float, score: float, priority_score: float, weakness: string}>
     */
    public function analyseExamTopics(array $syllabusTopics, array $studentScores): array
    {
        $scoresByTopic = [];
        foreach ($studentScores as $row) {
            if (! is_array($row)) {
                continue;
            }
            $topic = trim((string) ($row['topic'] ?? ''));
            if ($topic === '') {
                continue;
            }
            $scoresByTopic[strtolower($topic)] = (float) ($row['score'] ?? 50);
        }

        $ranked = [];
        foreach ($syllabusTopics as $row) {
            if (! is_array($row)) {
                continue;
            }

            $topic = trim((string) ($row['topic'] ?? ''));
            if ($topic === '') {
                continue;
            }

            $weight = (float) ($row['weight'] ?? 0);
            $score = $scoresByTopic[strtolower($topic)] ?? 50.0;
            $priorityScore = round($weight * (100 - $score), 2);

            $ranked[] = [
                'topic' => $topic,
                'weight' => $weight,
                'score' => $score,
                'priority_score' => $priorityScore,
                'weakness' => $this->weaknessLevel($score),
            ];
        }

        usort($ranked, fn (array $a, array $b): int => $b['priority_score'] <=> $a['priority_score']);

        return $ranked;
    }

    private function weaknessLevel(float $score): string
    {
        if ($score < 50) {
            return 'high';
        }

        if ($score < 70) {
            return 'medium';
        }

        return 'low';
    }
}
