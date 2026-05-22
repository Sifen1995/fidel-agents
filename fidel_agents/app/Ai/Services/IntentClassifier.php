<?php


namespace App\Ai\Services;

use App\Ai\Agents\IntentClassifierAgent;

class IntentClassifier
{
    /**
     * Classify which agent should handle the request payload.
     *
     * Returned values:
     * - homework
     * - recommender
     * - exam_prep
     * - helpdesk
     */
    public function classify(array $input): string
    {
        // Respect tags here too, in case classifier is used independently.
        if (! empty($input['tags'] ?? null)) {
            $tags = $input['tags'];

            if (is_string($tags)) {
                $tags = preg_split('/[,\|\s]+/', $tags) ?: [];
            }

            if (is_array($tags)) {
                $normalized = array_map(
                    fn ($t) => strtolower(trim((string) $t)),
                    $tags
                );

                if (in_array('homework', $normalized, true)) {
                    return 'homework';
                }

                if (in_array('recommender', $normalized, true) || in_array('recommendation', $normalized, true)) {
                    return 'recommender';
                }

                if (in_array('exam_prep', $normalized, true) || in_array('exam-prep', $normalized, true) || in_array('exam', $normalized, true)) {
                    return 'exam_prep';
                }

                if (in_array('helpdesk', $normalized, true) || in_array('support', $normalized, true)) {
                    return 'helpdesk';
                }
            }
        }

        // Rule-based pre-checks to avoid an LLM call when possible.
        if (!empty($input['image_base64'] ?? $input['image'] ?? null)) {
            return 'homework';
        }

        $examKeys = ['exam', 'exam_name', 'exam_schedule', 'days_remaining', 'time_remaining', 'time_remaining_minutes'];
        foreach ($examKeys as $key) {
            if (!empty($input[$key] ?? null)) {
                return 'exam_prep';
            }
        }

        $recoKeys = ['weak_subjects', 'completed_videos', 'completed_topics', 'grade_level', 'weak_topics'];
        foreach ($recoKeys as $key) {
            if (!empty($input[$key] ?? null)) {
                return 'recommender';
            }
        }

        if (!empty($input['question'] ?? null) || !empty($input['help'] ?? null)) {
            return 'helpdesk';
        }

        $freeText = strtolower(trim((string) ($input['text'] ?? $input['message'] ?? '')));
        $hasRole = !empty($input['user_role'] ?? null) || !empty($input['role'] ?? null);
        if ($hasRole && $this->looksLikeHelpdeskText($freeText)) {
            return 'helpdesk';
        }

        // AI fallback via official Laravel AI Agent class.
        return (new IntentClassifierAgent())->classify($input);
    }

    private function looksLikeHelpdeskText(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $keywords = [
            'login', 'password', 'payment', 'billing', 'invoice',
            'upload', 'account', 'subscription', 'support', 'help',
            'request tutor', 'how do i', 'cannot access', 'error on site',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}