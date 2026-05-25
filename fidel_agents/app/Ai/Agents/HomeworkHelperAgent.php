<?php


declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts\HomeworkPrompt;
use App\Ai\Services\ResponseParserService;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Stringable;

class HomeworkHelperAgent extends BaseOfflineFirstAgent implements Agent
{
    use Promptable;

    public function __construct(
        public ?User $user = null,
        protected ResponseParserService $parser = new ResponseParserService()
    ) {
        parent::__construct();
    }

    public function handle(array $input): array
    {
        $text = trim((string) ($input['ocr_text'] ?? $input['text'] ?? ''));
        $hasImage = ! empty($input['stored_image_path'] ?? null) || ! empty($input['image_base64'] ?? null);
        $subject = $input['subject_hint'] ?? 'general education';
        $grade = $input['grade_hint'] ?? 'unknown';

        $provider = $this->resolveProvider((float) ($input['ocr_confidence'] ?? 0.0), $hasImage);
        $model = $this->resolveModel($provider);
        $prompt = $provider === 'ollama'
            ? HomeworkPrompt::buildForOllama($text, $hasImage, $subject, $grade)
            : HomeworkPrompt::build($text, $hasImage, $subject, $grade);

        if ($provider === 'ollama' && !$this->connectivity->isOllamaReachable()) {
            throw new \RuntimeException('Ollama is not reachable on the host. Cannot process the request offline.');
        }

        $timeout = $provider === 'ollama' ? 90 : 25;

        try {
            $response = $this->prompt($prompt, [], $provider, $model, $timeout);
        } catch (\Throwable $exception) {
            report($exception);

            if ($provider === 'ollama' && $this->connectivity->isOnline()) {
                $provider = 'gemini';
                $model = $this->resolveModel($provider);
                $prompt = HomeworkPrompt::build($text, $hasImage, $subject, $grade);
                try {
                    $response = $this->prompt($prompt, [], $provider, $model, 25);
                } catch (\Throwable $fallbackException) {
                    report($fallbackException);
                    throw new \RuntimeException('Unable to complete the request using Ollama or cloud fallback.', 0, $fallbackException);
                }
            } elseif ($provider === 'gemini') {
                throw new \RuntimeException('Unable to complete the request using the cloud provider.', 0, $exception);
            } else {
                throw new \RuntimeException('Unable to complete the homework request locally. Check that Ollama is reachable.', 0, $exception);
            }
        }

        $rawText = (string) $response;
        $parsed = $this->parser->parse($rawText);
        $parsed = $this->parser->validate($parsed);

        $parsed['request_id'] = $parsed['request_id'] ?? (string) Str::uuid();
        $parsed['ocr_confidence'] = isset($parsed['ocr_confidence']) ? (float) $parsed['ocr_confidence'] : (isset($input['ocr_confidence']) ? (float) $input['ocr_confidence'] : 0.0);
        $parsed['llm_confidence'] = isset($parsed['llm_confidence']) ? (float) $parsed['llm_confidence'] : (isset($input['llm_confidence']) ? (float) $input['llm_confidence'] : 0.0);
        $parsed['ocr_provider'] = $input['ocr_provider'] ?? ($parsed['ocr_provider'] ?? 'unknown');
        $parsed['ocr_model'] = $input['ocr_model'] ?? ($parsed['ocr_model'] ?? ($parsed['ocr_provider'] === 'gemini' ? config('ai.models.text.providers.gemini_ocr') ?? config('ai.models.text.providers.gemini') : 'tesseract'));
        $parsed['llm_provider'] = $provider;
        $parsed['llm_model'] = $model;
        $parsed['processed_offline'] = $provider === 'ollama'
            && ($input['ocr_provider'] ?? 'tesseract') !== 'gemini'
            && ($input['ocr_mode'] ?? 'offline') === 'offline';

        return $parsed;
    }


    

    public function instructions(): Stringable|string
    {
        return 'You are an educational AI assistant for Fidel Academy. ' .
               'Provide step-by-step reasoning for homework problems. ' .
               'Never just provide the final answer. ' .
               'Reinforce the concept with a learning tip.';
    }

    protected function resolveModel(string $provider): string
    {
        return match ($provider) {
            'ollama' => config('ai.models.text.providers.ollama') ?? 'qwen2.5:1.5b',
            'gemini' => config('ai.models.text.providers.gemini') ?? 'gemini-3.1-flash-lite-preview',
            default => config('ai.models.text.providers.gemini') ?? 'gemini-3.1-flash-lite-preview',
        };
    }

    
}
