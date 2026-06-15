<?php

declare(strict_types=1);

namespace App\Ai\Prompts;

class ExamPrompt
{
    /**
     * @param  list<array{topic: string, weakness: string, priority_score: float|int, score?: float|int, weight?: float}>  $priorityTopics
     * @param  list<string>  $completedVideos
     */
    public static function build(
        string $gradeLevel,
        string $examSubject,
        string $examDate,
        int $daysRemaining,
        array $priorityTopics,
        array $completedVideos,
        string $planIntensity
    ): string {
        $examLabel = trim($gradeLevel.' '.$examSubject);
        $topicsJson = json_encode(array_values($priorityTopics), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $videosJson = json_encode(array_values($completedVideos), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return implode("\n", [
            'You are an exam preparation assistant for Fidel Academy.',
            'Reply with ONLY one JSON object. No markdown fences. No backticks. No text outside the JSON.',
            '',
            'Student grade level: '.$gradeLevel,
            'Exam subject: '.$examSubject,
            'Exam label: '.$examLabel,
            'Exam date: '.$examDate,
            'Days remaining until exam: '.$daysRemaining,
            'Plan intensity: '.$planIntensity,
            '',
            'Priority topics (study these first):',
            $topicsJson ?: '[]',
            '',
            'Videos the student already completed:',
            $videosJson ?: '[]',
            '',
            'Rules:',
            '- Generate ORIGINAL practice questions only — never reproduce real exam questions verbatim.',
            '- Create new questions that test the same concepts as the priority topics.',
            '- Generate 3 to 5 practice questions per priority topic listed above.',
            '- Build daily_plan tasks that match the plan intensity and days remaining.',
            '- priority_topics in your JSON must reflect the topics provided with weakness and priority_score.',
            '',
            'Template:',
            '{"exam":"'.$examLabel.'","exam_date":"'.$examDate.'","days_remaining":'.$daysRemaining.',"priority_topics":[{"topic":"...","weakness":"high|medium|low","priority_score":0}],"daily_plan":[{"day":1,"task":"..."}],"practice_questions":[{"topic":"...","question":"...","difficulty":"easy|medium|hard"}],"confidence_tip":"one motivational sentence"}',
        ]);
    }

    public static function buildJsonRepair(string $invalidOutput): string
    {
        return implode("\n", [
            'The previous response was invalid JSON. Fix it.',
            'Return ONLY one valid JSON object with keys: exam, exam_date, days_remaining, priority_topics, daily_plan, practice_questions, confidence_tip.',
            'Practice questions must be original — never reproduce real exam questions.',
            'No markdown. No explanation outside the JSON.',
            '',
            'Broken output to fix:',
            $invalidOutput,
        ]);
    }

    public static function instructions(): string
    {
        return 'You are an educational exam preparation assistant for Fidel Academy. '
            . 'Return valid JSON only. Generate original practice questions — never copy real exam papers.';
    }

    public static function planIntensity(int $daysRemaining): string
    {
        if ($daysRemaining < 3) {
            return 'Focus only on the highest priority topic with intensive review — minimal breadth, maximum depth.';
        }

        if ($daysRemaining <= 7) {
            return 'Cover the top 2 priority topics with practice questions each day.';
        }

        if ($daysRemaining <= 14) {
            return 'Cover all 3 priority topics with a balanced daily breakdown.';
        }

        return 'Comprehensive coverage of all priority topics with revision days included.';
    }
}
