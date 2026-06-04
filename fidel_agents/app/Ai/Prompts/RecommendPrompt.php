<?php

declare(strict_types=1);

namespace App\Ai\Prompts;

class RecommendPrompt
{
    /**
     * @param  array{subject: string, score: float|int, priority_rank?: int, note?: string}  $weakness
     * @param  list<array<string, mixed>>  $filteredCatalogue
     */
    public static function build(
        string $gradeLevel,
        array $weakness,
        array $filteredCatalogue
    ): string {
        $subject = (string) ($weakness['subject'] ?? 'General');
        $score = $weakness['score'] ?? 0;
        $note = isset($weakness['note']) ? "\nNote: {$weakness['note']}" : '';
        $catalogueJson = json_encode(array_values($filteredCatalogue), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return implode("\n", [
            'You are a study recommender for Fidel Academy.',
            'Reply with ONLY one JSON object. No markdown fences. No backticks. No text before or after the JSON.',
            '',
            'Student grade level: ' . $gradeLevel,
            'Weak subject needing focus: ' . $subject,
            'Exact average quiz score for this subject: ' . $score . '%' . $note,
            '',
            'Allowed content catalogue (ONLY use content_id values from this list):',
            $catalogueJson ?: '[]',
            '',
            'Rules:',
            '- ONLY recommend content_id values that appear in the catalogue above — never invent content_ids.',
            '- Match titles exactly from the catalogue.',
            '- recommendations must be an array of objects with type, title, content_id.',
            '- daily_breakdown must be an array of {"day": number, "task": string} for 5 days.',
            '',
            'Template:',
            '{"focus_subject":"' . $subject . '","reason":"one sentence","recommendations":[{"type":"video","title":"exact title","content_id":"exact uuid"}],"study_plan":"30 minutes daily for 5 days","daily_breakdown":[{"day":1,"task":"specific task"}],"motivational_tip":"one encouraging sentence"}',
        ]);
    }

    public static function buildJsonRepair(string $invalidOutput): string
    {
        return implode("\n", [
            'The previous response was invalid JSON. Fix it.',
            'Return ONLY one valid JSON object with keys: focus_subject, reason, recommendations, study_plan, daily_breakdown, motivational_tip.',
            'ONLY use content_id values from the catalogue in the original prompt.',
            'No markdown. No explanation outside the JSON.',
            '',
            'Broken output to fix:',
            $invalidOutput,
        ]);
    }

    public static function instructions(): string
    {
        return 'You are an educational study recommender for Fidel Academy. '
            . 'Return valid JSON only. Never invent content_ids not present in the provided catalogue.';
    }
}
