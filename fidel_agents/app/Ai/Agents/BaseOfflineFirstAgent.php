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
     * Prefers Ollama when reachable (offline-first); falls back to Gemini when online.
     * Image requests prefer Gemini when online because the small Ollama model lacks vision.
     */
    protected function resolveProvider(float $currentConfidence, bool $hasImage = false): string
    {
        $isOnline = $this->connectivity->isOnline();
        $ollamaReachable = $this->connectivity->isOllamaReachable();

        if ($hasImage && $isOnline) {
            return 'gemini';
        }

        if ($ollamaReachable) {
            return 'ollama';
        }

        if ($isOnline) {
            return 'gemini';
        }

        return 'ollama';
    }
}
