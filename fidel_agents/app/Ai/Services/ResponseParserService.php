<?php

namespace App\Ai\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Validation\ValidationException;

class ResponseParserService
{
    /**
     * Parse LLM output; repair common Ollama JSON mistakes, then fall back to field extraction.
     *
     * @param  array{problem?: string, subject?: string, grade_level?: string}  $context
     */
    public function parseLenient(string $raw, array $context = []): array
    {
        $raw = trim($raw);
        $payload = $this->decodeJson($raw);

        if (! is_array($payload)) {
            $payload = $this->parseFromBrokenJson($raw, $context);
        }

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['response' => ['Unable to parse AI response as valid JSON.']]);
        }

        $payload = $this->normalizeSteps($payload);
        $payload = $this->fillMissingFromContext($payload, $context);

        return $this->validate($payload);
    }

    public function parse(string $raw): array
    {
        return $this->parseLenient($raw);
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

        if (empty($payload['steps']) || ! is_array($payload['steps'])) {
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

    private function normalizeSteps(array $payload): array
    {
        if (! isset($payload['steps']) || ! is_array($payload['steps'])) {
            return $payload;
        }

        $flat = [];
        foreach ($payload['steps'] as $step) {
            if (is_array($step)) {
                foreach ($step as $nested) {
                    $flat[] = is_string($nested) ? $nested : json_encode($nested);
                }
            } else {
                $flat[] = (string) $step;
            }
        }

        $payload['steps'] = $flat !== [] ? $flat : ['See final answer.'];

        return $payload;
    }

    /**
     * @param  array{problem?: string, subject?: string, grade_level?: string}  $context
     */
    private function fillMissingFromContext(array $payload, array $context): array
    {
        if (empty($payload['problem']) && ! empty($context['problem'])) {
            $payload['problem'] = $context['problem'];
        }
        if (($payload['subject'] ?? '') === 'Unknown' && ! empty($context['subject'])) {
            $payload['subject'] = $context['subject'];
        }
        if (($payload['grade_level'] ?? '') === 'Unknown' && ! empty($context['grade_level'])) {
            $payload['grade_level'] = $context['grade_level'];
        }

        return $payload;
    }

    /**
     * @param  array{problem?: string, subject?: string, grade_level?: string}  $context
     */
    private function parseFromBrokenJson(string $raw, array $context): ?array
    {
        $json = $this->stripCodeFences($raw);
        $json = $this->repairJson($json);

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $steps = $this->extractStepsFromBrokenJson($json);
        $finalAnswer = $this->extractJsonStringField($json, 'final_answer');
        $problem = $this->extractJsonStringField($json, 'problem');
        $learningTip = $this->extractJsonStringField($json, 'learning_tip');
        $subject = $this->extractJsonStringField($json, 'subject');
        $gradeLevel = $this->extractJsonStringField($json, 'grade_level');

        if ($steps === [] && $finalAnswer === null && $problem === null) {
            return $this->wrapPlainText($raw, $context);
        }

        return [
            'subject' => $subject ?? $context['subject'] ?? 'Unknown',
            'grade_level' => $gradeLevel ?? $context['grade_level'] ?? 'Unknown',
            'problem' => $problem ?? $context['problem'] ?? 'Unable to extract problem.',
            'steps' => $steps !== [] ? $steps : ['See final answer.'],
            'final_answer' => $finalAnswer ?? 'See steps above.',
            'learning_tip' => $learningTip ?? 'Review the steps above.',
        ];
    }

    /**
     * @param  array{problem?: string, subject?: string, grade_level?: string}  $context
     */
    private function wrapPlainText(string $raw, array $context): ?array
    {
        $text = trim($this->stripCodeFences($raw));
        if ($text === '') {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        $steps = [];
        $finalAnswer = 'See explanation above.';

        foreach ($lines as $line) {
            if (preg_match('/^(?:final\s*answer|answer)\s*[:=]\s*(.+)$/i', $line, $m)) {
                $finalAnswer = trim($m[1]);
                continue;
            }
            if (preg_match('/^\d+[\).\]]\s+(.+)$/', $line, $m)) {
                $steps[] = trim($m[1]);
            } elseif (preg_match('/^step\s*\d+\s*[:.]?\s*(.+)$/i', $line, $m)) {
                $steps[] = trim($m[1]);
            }
        }

        if ($steps === [] && count($lines) > 1) {
            $steps = array_slice($lines, 0, -1);
            $finalAnswer = end($lines) ?: $finalAnswer;
        } elseif ($steps === []) {
            $steps = [$text];
        }

        return [
            'subject' => $context['subject'] ?? 'Unknown',
            'grade_level' => $context['grade_level'] ?? 'Unknown',
            'problem' => $context['problem'] ?? 'Homework problem',
            'steps' => $steps,
            'final_answer' => $finalAnswer,
            'learning_tip' => 'Review each step and make sure you understand why it works.',
        ];
    }

    private function extractStepsFromBrokenJson(string $json): array
    {
        if (! preg_match('/"steps"\s*:\s*(.+?)(?="(?:final_answer|learning_tip|subject|grade_level|problem|request_id)"\s*:)/s', $json, $match)) {
            return [];
        }

        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $match[1], $strings);

        $steps = [];
        foreach ($strings[1] ?? [] as $value) {
            $value = stripcslashes($value);
            if ($value === '' || in_array($value, ['steps', 'subject', 'grade_level'], true)) {
                continue;
            }
            $steps[] = $value;
        }

        return $steps;
    }

    private function extractJsonStringField(string $json, string $field): ?string
    {
        if (! preg_match('/"'.preg_quote($field, '/').'"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $json, $match)) {
            return null;
        }

        return stripcslashes($match[1]);
    }

    private function decodeJson(string $raw): mixed
    {
        foreach ($this->jsonCandidates($raw) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            $repaired = $this->repairJson($candidate);
            $decoded = json_decode($repaired, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function jsonCandidates(string $raw): array
    {
        $candidates = [trim($raw)];

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $fenceMatch)) {
            $candidates[] = trim($fenceMatch[1]);
        }

        $stripped = $this->stripCodeFences($raw);
        if ($stripped !== $raw) {
            $candidates[] = $stripped;
        }

        $start = strpos($stripped, '{');
        $end = strrpos($stripped, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($stripped, $start, $end - $start + 1);
        }

        return array_values(array_unique($candidates));
    }

    private function stripCodeFences(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $fenceMatch)) {
            return trim($fenceMatch[1]);
        }

        return $raw;
    }

    private function repairJson(string $json): string
    {
        $json = preg_replace('/,\s*([}\]])/', '$1', $json) ?? $json;
        $json = $this->mergeSplitStepsArrays($json);

        return $json;
    }

    /** Ollama often returns "steps": ["a"], ["b"] instead of one array. */
    private function mergeSplitStepsArrays(string $json): string
    {
        $limit = 15;
        while ($limit-- > 0 && preg_match('/"steps"\s*:\s*\[[^\]]+"\]\s*,\s*\[\s*"/s', $json)) {
            $json = preg_replace(
                '/("steps"\s*:\s*\[[^\]]+"\])\s*,\s*\[\s*"/s',
                '$1, "',
                $json,
                1
            ) ?? $json;
        }

        return $json;
    }

    /**
     * Parse study-recommender LLM output and validate catalogue content_ids.
     *
     * @param  list<array<string, mixed>>  $allowedCatalogue
     * @param  array{focus_subject?: string, grade_level?: string}  $context
     */
    public function parseRecommendLenient(string $raw, array $allowedCatalogue, array $context = []): array
    {
        $raw = trim($raw);
        $payload = $this->decodeJson($raw);

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['response' => ['Unable to parse AI response as valid JSON.']]);
        }

        $allowedIds = [];
        foreach ($allowedCatalogue as $item) {
            if (! empty($item['id'])) {
                $allowedIds[(string) $item['id']] = true;
            }
        }

        $payload['focus_subject'] = (string) ($payload['focus_subject'] ?? $context['focus_subject'] ?? 'General');
        $payload['reason'] = (string) ($payload['reason'] ?? 'Focus on improving in this subject.');
        $payload['study_plan'] = (string) ($payload['study_plan'] ?? '30 minutes daily for 5 days');
        $payload['motivational_tip'] = (string) ($payload['motivational_tip'] ?? 'Keep practicing — steady progress adds up.');

        $recommendations = $payload['recommendations'] ?? [];
        if (! is_array($recommendations)) {
            $recommendations = [];
        }

        $validatedRecommendations = [];
        foreach ($recommendations as $rec) {
            if (! is_array($rec)) {
                continue;
            }
            $contentId = (string) ($rec['content_id'] ?? '');
            if ($contentId === '' || ! isset($allowedIds[$contentId])) {
                continue;
            }
            $validatedRecommendations[] = [
                'type' => (string) ($rec['type'] ?? 'video'),
                'title' => (string) ($rec['title'] ?? ''),
                'content_id' => $contentId,
            ];
        }

        if ($validatedRecommendations === [] && $allowedCatalogue !== []) {
            $first = $allowedCatalogue[0];
            $validatedRecommendations[] = [
                'type' => (string) ($first['type'] ?? 'video'),
                'title' => (string) ($first['title'] ?? 'Recommended content'),
                'content_id' => (string) ($first['id'] ?? ''),
            ];
        }

        $payload['recommendations'] = $validatedRecommendations;

        $dailyBreakdown = $payload['daily_breakdown'] ?? [];
        if (! is_array($dailyBreakdown) || $dailyBreakdown === []) {
            $dailyBreakdown = [
                ['day' => 1, 'task' => 'Review weak subject fundamentals'],
                ['day' => 2, 'task' => 'Watch one recommended video'],
                ['day' => 3, 'task' => 'Practice quiz questions'],
                ['day' => 4, 'task' => 'Re-watch and take notes'],
                ['day' => 5, 'task' => 'Short self-test on the topic'],
            ];
        }

        $payload['daily_breakdown'] = array_values(array_map(function ($day) {
            if (! is_array($day)) {
                return ['day' => 1, 'task' => (string) $day];
            }

            return [
                'day' => (int) ($day['day'] ?? 1),
                'task' => (string) ($day['task'] ?? 'Study session'),
            ];
        }, $dailyBreakdown));

        $rules = [
            'focus_subject' => 'required|string',
            'reason' => 'required|string',
            'recommendations' => 'required|array|min:1',
            'recommendations.*.type' => 'required|string',
            'recommendations.*.title' => 'required|string',
            'recommendations.*.content_id' => 'required|string',
            'study_plan' => 'required|string',
            'daily_breakdown' => 'required|array|min:1',
            'daily_breakdown.*.day' => 'required|integer',
            'daily_breakdown.*.task' => 'required|string',
            'motivational_tip' => 'required|string',
        ];

        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $payload;
    }
}
