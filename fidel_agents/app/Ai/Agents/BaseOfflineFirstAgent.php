<?php

namespace App\Ai\Agents;

use App\Ai\Services\ConnectivityService;
use Laravel\Ai\Contracts\Agent;

abstract class BaseOfflineFirstAgent implements Agent
{
    protected ConnectivityService $connectivity;

    public function __construct()
    {
        $this->connectivity = new ConnectivityService();
    }

    /**
     * Determine the provider dynamically at runtime.
     */
    protected function resolveProvider(float $currentConfidence): string
    {
        $isOnline = $this->connectivity->isOnline();
        $ollamaReachable = $this->connectivity->isOllamaReachable();

        if (! $isOnline && ! $ollamaReachable) {
            return 'ollama'; // Completely offline and Ollama down, let it fail natively
        }

        if (! $isOnline) {
            return 'ollama';
        }

        if (! $ollamaReachable) {
            return 'gemini'; // Online but Ollama down, avoid timeout
        }

        if ($currentConfidence === 0.0) {
            return 'ollama';
        }

        if ($currentConfidence < $this->connectivity->llmConfidenceThreshold()) {
            return 'gemini';
        }

        return 'ollama';
    }
}
