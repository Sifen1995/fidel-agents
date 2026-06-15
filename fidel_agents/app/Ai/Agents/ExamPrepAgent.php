<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts\ExamPrompt;
use App\Ai\Services\AIFacade;
use App\Ai\Services\ConnectivityService;
use App\Ai\Services\ResponseParserService;
use App\Ai\Services\RuleEngineService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExamPrepAgent
{
    private const OLLAMA_MODEL = 'qwen2.5:1.5b';

    protected ConnectivityService $connectivity;

    public function __construct(
        protected RuleEngineService $ruleEngine = new RuleEngineService(),
        protected ResponseParserService $parser = new ResponseParserService(),
        protected AIFacade $ai = new AIFacade(),
        ?ConnectivityService $connectivity = null,
    ) {
        $this->connectivity = $connectivity ?? new ConnectivityService();
    }

    public function handle(array $input): array
    {
        $examSubject = (string) ($input['exam_subject'] ?? '');
        $examDate = (string) ($input['exam_date'] ?? '');
        $studentId = (string) ($input['student_id'] ?? '');
        $gradeLevel = (string) ($input['grade_level'] ?? 'unspecified');
        $daysRemaining = (int) ($input['days_remaining'] ?? 0);
        $syllabusTopics = is_array($input['syllabus_topics'] ?? null) ? $input['syllabus_topics'] : [];
        $studentScores = is_array($input['student_scores'] ?? null) ? $input['student_scores'] : [];
        $completedVideos = is_array($input['completed_videos'] ?? null) ? $input['completed_videos'] : [];

        if ($examSubject === '') {
            throw new \RuntimeException('exam_subject is required for exam preparation.');
        }

        $rankedTopics = $this->ruleEngine->analyseExamTopics($syllabusTopics, $studentScores);
        $topTopics = array_slice($rankedTopics, 0, 3);
        $priorityTopics = array_map(fn (array $topic): array => [
            'topic' => $topic['topic'],
            'weakness' => $topic['weakness'],
            'priority_score' => $topic['priority_score'],
        ], $topTopics);

        $planIntensity = ExamPrompt::planIntensity($daysRemaining);
        $prompt = ExamPrompt::build(
            $gradeLevel,
            $examSubject,
            $examDate,
            $daysRemaining,
            $priorityTopics,
            $completedVideos,
            $planIntensity
        );
        $instructions = ExamPrompt::instructions();

        $provider = $this->resolveProvider();
        $model = $this->resolveModel($provider);
        $timeout = $provider === 'ollama' ? 90 : 25;

        if ($provider === 'ollama' && ! $this->connectivity->isOllamaReachable()) {
            throw new \RuntimeException('Ollama is not reachable on the host. Cannot process the exam prep request offline.');
        }

        try {
            $rawText = $this->ai->prompt($instructions, $prompt, $provider, $model, $timeout);
        } catch (\Throwable $exception) {
            report($exception);

            if ($provider === 'gemini' && $this->connectivity->isOllamaReachable()) {
                $provider = 'ollama';
                $model = self::OLLAMA_MODEL;
                $timeout = 90;
                try {
                    $rawText = $this->ai->prompt($instructions, $prompt, $provider, $model, $timeout);
                } catch (\Throwable $fallbackException) {
                    report($fallbackException);
                    throw new \RuntimeException('Unable to complete the exam prep using Gemini or local Ollama fallback.', 0, $fallbackException);
                }
            } elseif ($provider === 'ollama' && $this->connectivity->isOnline()) {
                $provider = 'gemini';
                $model = $this->resolveModel($provider);
                try {
                    $rawText = $this->ai->prompt($instructions, $prompt, $provider, $model, 25);
                } catch (\Throwable $fallbackException) {
                    report($fallbackException);
                    throw new \RuntimeException('Unable to complete the exam prep using Ollama or cloud fallback.', 0, $fallbackException);
                }
            } elseif ($provider === 'gemini') {
                throw new \RuntimeException('Unable to complete the exam prep using the cloud provider.', 0, $exception);
            } else {
                throw new \RuntimeException('Unable to complete the exam prep request locally. Check that Ollama is reachable.', 0, $exception);
            }
        }

        $parseContext = [
            'exam' => trim($gradeLevel.' '.$examSubject),
            'exam_date' => $examDate,
            'days_remaining' => $daysRemaining,
        ];

        try {
            $parsed = $this->parser->parseExamLenient($rawText, $priorityTopics, $parseContext);
        } catch (ValidationException $parseException) {
            if ($provider !== 'ollama') {
                throw $parseException;
            }

            $repairPrompt = ExamPrompt::buildJsonRepair($rawText);
            try {
                $rawText = $this->ai->prompt($instructions, $repairPrompt, $provider, $model, $timeout);
                $parsed = $this->parser->parseExamLenient($rawText, $priorityTopics, $parseContext);
            } catch (\Throwable) {
                throw $parseException;
            }
        }

        $parsed['processed_offline'] = $provider === 'ollama';
        $parsed['llm_provider'] = $provider;
        $parsed['llm_model'] = $model;

        $this->logUsage($studentId, $provider, $model, $examSubject);

        return $parsed;
    }

    /**
     * Prefer Gemini when online; use Ollama when offline or connectivity is unavailable.
     */
    protected function resolveProvider(): string
    {
        if ($this->connectivity->isOnline()) {
            return 'gemini';
        }

        return 'ollama';
    }

    protected function resolveModel(string $provider): string
    {
        return match ($provider) {
            'ollama' => self::OLLAMA_MODEL,
            'gemini' => config('ai.models.text.providers.gemini') ?? 'gemini-3.1-flash-lite-preview',
            default => config('ai.models.text.providers.gemini') ?? 'gemini-3.1-flash-lite-preview',
        };
    }

    private function logUsage(string $studentId, string $provider, string $model, string $examSubject): void
    {
        Log::info('ai_usage_log', [
            'agent' => 'exam_prep',
            'student_id' => $studentId,
            'llm_provider' => $provider,
            'llm_model' => $model,
            'exam_subject' => $examSubject,
        ]);
    }
}
