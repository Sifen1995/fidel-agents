<?php

declare(strict_types=1);

namespace App\Ai\Services;

use Laravel\Ai\Ai;
use Laravel\Ai\Messages\UserMessage;

/**
 * Thin wrapper around the Laravel AI SDK for provider calls.
 */
class AIFacade
{
    public function prompt(
        string $instructions,
        string $prompt,
        string $provider,
        string $model,
        int $timeout = 90
    ): string {
        $textProvider = Ai::textProvider($provider);

        $response = $textProvider->textGateway()->generateText(
            $textProvider,
            $model,
            $instructions,
            [new UserMessage($prompt)],
            [],
            null,
            null,
            $timeout
        );

        return trim($response->text);
    }
}
