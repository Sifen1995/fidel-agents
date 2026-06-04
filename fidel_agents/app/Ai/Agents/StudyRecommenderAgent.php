<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts\RecommendPrompt;
use App\Ai\Services\AIFacade;
use App\Ai\Services\ConnectivityService;
use App\Ai\Services\ResponseParserService;
use App\Ai\Services\RuleEngineService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StudyRecommenderAgent
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
        $studentId = (string) ($input['student_id'] ?? '');
        $gradeLevel = (string) ($input['grade_level'] ?? 'unspecified');
        $quizResults = is_array($input['quiz_results'] ?? null) ? $input['quiz_results'] : [];
        $completedVideos = is_array($input['completed_videos'] ?? null) ? $input['completed_videos'] : [];
        $contentCatalogue = is_array($input['content_catalogue'] ?? null) ? $input['content_catalogue'] : [];

        if ($studentId === '') {
            throw new \RuntimeException('student_id is required for study recommendations.');
        }

        $weakness = $this->ruleEngine->analyse($quizResults, $completedVideos);
        $weakSubject = (string) ($weakness['subject'] ?? 'General');
        $filteredCatalogue = $this->filterCatalogueBySubject($contentCatalogue, $weakSubject);

        $prompt = RecommendPrompt::build($gradeLevel, $weakness, $filteredCatalogue);
        $instructions = RecommendPrompt::instructions();

        $provider = $this->resolveProvider();
        $model = $this->resolveModel($provider);
        $timeout = $provider === 'ollama' ? 90 : 25;

        if ($provider === 'ollama' && ! $this->connectivity->isOllamaReachable()) {
            throw new \RuntimeException('Ollama is not reachable on the host. Cannot process the recommendation request offline.');
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
                    throw new \RuntimeException('Unable to complete the recommendation using Gemini or local Ollama fallback.', 0, $fallbackException);
                }
            } elseif ($provider === 'ollama' && $this->connectivity->isOnline()) {
                $provider = 'gemini';
                $model = $this->resolveModel($provider);
                try {
                    $rawText = $this->ai->prompt($instructions, $prompt, $provider, $model, 25);
                } catch (\Throwable $fallbackException) {
                    report($fallbackException);
                    throw new \RuntimeException('Unable to complete the recommendation using Ollama or cloud fallback.', 0, $fallbackException);
                }
            } elseif ($provider === 'gemini') {
                throw new \RuntimeException('Unable to complete the recommendation using the cloud provider.', 0, $exception);
            } else {
                throw new \RuntimeException('Unable to complete the recommendation request locally. Check that Ollama is reachable.', 0, $exception);
            }
        }

        $parseContext = [
            'focus_subject' => $weakSubject,
            'grade_level' => $gradeLevel,
        ];

        try {
            $parsed = $this->parser->parseRecommendLenient($rawText, $filteredCatalogue, $parseContext);
        } catch (ValidationException $parseException) {
            if ($provider !== 'ollama') {
                throw $parseException;
            }

            $repairPrompt = RecommendPrompt::buildJsonRepair($rawText);
            try {
                $rawText = $this->ai->prompt($instructions, $repairPrompt, $provider, $model, $timeout);
                $parsed = $this->parser->parseRecommendLenient($rawText, $filteredCatalogue, $parseContext);
            } catch (\Throwable) {
                throw $parseException;
            }
        }

        $parsed['processed_offline'] = $provider === 'ollama';
        $parsed['llm_provider'] = $provider;
        $parsed['llm_model'] = $model;

        $this->logUsage($studentId, $provider, $model, $weakSubject);

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

    /**
     * @param  list<array<string, mixed>>  $catalogue
     * @return list<array<string, mixed>>
     */
    private function filterCatalogueBySubject(array $catalogue, string $weakSubject): array
    {
        $weakSubjectLower = strtolower(trim($weakSubject));

        $filtered = array_values(array_filter($catalogue, function ($item) use ($weakSubjectLower) {
            if (! is_array($item)) {
                return false;
            }

            $itemSubject = strtolower(trim((string) ($item['subject'] ?? '')));

            return $itemSubject === $weakSubjectLower
                || ($weakSubjectLower !== '' && str_contains($itemSubject, $weakSubjectLower))
                || ($itemSubject !== '' && str_contains($weakSubjectLower, $itemSubject));
        }));

        return $filtered !== [] ? $filtered : $catalogue;
    }

    private function logUsage(string $studentId, string $provider, string $model, string $focusSubject): void
    {
        Log::info('ai_usage_log', [
            'agent' => 'study_recommender',
            'student_id' => $studentId,
            'llm_provider' => $provider,
            'llm_model' => $model,
            'focus_subject' => $focusSubject,
        ]);
    }
}
