<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class IntentClassifierAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are an API intent router. Choose exactly one intent: homework, recommender, exam_prep, or helpdesk.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()->required(),
        ];
    }

    /**
     * For Conversational agents, return the conversation messages.
     * Intent classification is a stateless single-prompt operation, so
     * return an empty list here.
     *
     * @return \Laravel\Ai\Messages\Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    public function classify(array $input): string
    {
        $response = $this->prompt(
            prompt: "Payload JSON:\n".json_encode($input, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n\nReturn only the best intent in the structured output schema.",
            provider: config('ai.default', 'ollama')
        );

        $intent = strtolower(trim((string) ($response['intent'] ?? '')));

        return match ($intent) {
            'homework' => 'homework',
            'recommender' => 'recommender',
            'exam_prep', 'exam-prep', 'exam' => 'exam_prep',
            'helpdesk', 'help' => 'helpdesk',
            default => 'helpdesk',
        };
    }
}
