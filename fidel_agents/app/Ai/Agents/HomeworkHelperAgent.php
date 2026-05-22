<?php


declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts\HomeworkPrompt;
use App\Ai\Services\ResponseParserService;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Stringable;

class HomeworkHelperAgent extends BaseOfflineFirstAgent implements Agent, HasStructuredOutput
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

        $prompt = HomeworkPrompt::build($text, $hasImage, $subject, $grade);
        $provider = $this->resolveProvider((float) ($input['ocr_confidence'] ?? 0.0));
        $model = $this->resolveModel($provider);

        if ($provider === 'ollama' && !$this->connectivity->isOllamaReachable()) {
            throw new \RuntimeException('Ollama is not reachable on the host. Cannot process the request offline.');
        }

        // Temporary debug — remove after fixing
        if ($provider === 'ollama') {
            \Log::info('Ollama URL being used', [
                'url' => config('ai.providers.ollama.url'),
                'model' => $model,
            ]);
        }

        try {
            $response = $this->prompt(
                $prompt,
                [],
                $provider,
                $model,
                25 // Reduced from 30 to prevent 504 Gateway Timeout
            );
        } catch (\Throwable $exception) {
            report($exception);

            if ($this->connectivity->isOnline() && $provider !== 'gemini') {
                $provider = 'gemini';
                $model = $this->resolveModel($provider);
                try {
                    $response = $this->prompt(
                        $prompt,
                        [],
                        $provider,
                        $model,
                        25 // 25 + 25 = 50s, safe from 60s Nginx timeout
                    );
                } catch (\Throwable $fallbackException) {
                    report($fallbackException);
                    throw new \RuntimeException('Unable to complete the request using the cloud provider.', 0, $fallbackException);
                }
            } elseif ($provider === 'ollama' && $model !== 'llama3.1:8b') {
                $fallbackModel = 'llama3.1:8b';
                try {
                    $response = $this->prompt(
                        $prompt,
                        [],
                        'ollama',
                        $fallbackModel,
                        25 // 25 + 25 = 50s, safe from 60s Nginx timeout
                    );
                    $model = $fallbackModel;
                } catch (\Throwable $fallbackException) {
                    report($fallbackException);
                    throw new \RuntimeException('Unable to complete the homework request locally. Check that Ollama is reachable through the host proxy on port 11435.', 0, $fallbackException);
                }
            } else {
                $msg = $provider === 'gemini' 
                    ? 'Unable to complete the request using the cloud provider.'
                    : 'Unable to complete the homework request locally. Check that Ollama is reachable through the host proxy on port 11435.';
                throw new \RuntimeException(
                    $msg,
                    0,
                    $exception
                );
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
        $parsed['llm_provider'] = $parsed['llm_provider'] ?? $provider;
        $parsed['llm_model'] = $parsed['llm_model'] ?? $model;
        $parsed['processed_offline'] = isset($parsed['processed_offline'])
            ? (bool) $parsed['processed_offline']
            : ($provider === 'ollama' && ($parsed['ocr_provider'] ?? 'tesseract') !== 'gemini' && ($input['ocr_mode'] ?? 'offline') === 'offline');

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
            'ollama' => config('ai.models.text.providers.ollama') ?? 'llama3.1:8b',
            'gemini' => config('ai.models.text.providers.gemini') ?? 'gemini-3.1-flash-lite-preview',
            default => config('ai.models.text.providers.gemini') ?? 'gemini-3.1-flash-lite-preview',
        };
    }
      
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()->required(),
            'subject' => $schema->string()->required(),
            'grade_level' => $schema->string()->required(),
            'problem' => $schema->string()->required(),
            'steps' => $schema->array($schema->string())->required(),
            'final_answer' => $schema->string()->required(),
            'learning_tip' => $schema->string()->required(),
            'ocr_confidence' => $schema->number()->required(),
            'llm_confidence' => $schema->number()->required(),
            'processed_offline' => $schema->boolean()->required(),
            'ocr_provider' => $schema->string(),
            'ocr_model' => $schema->string(),
            'llm_provider' => $schema->string(),
            'llm_model' => $schema->string(),
        ];
    }

    
}
