<?php

namespace App\Ai\Services;

use Illuminate\Support\Facades\Http;

class ConnectivityService {
    public function isOnline(): bool {
        try {
            if (! $this->canResolveHost('generativelanguage.googleapis.com')) {
                return false;
            }

            $response = Http::timeout(3)
                ->withOptions(['verify' => false])
                ->get('https://generativelanguage.googleapis.com/v1beta');

            // The root endpoint returns 404, which means we reached it.
            return $response->status() > 0;
        } catch (\Exception) {
            return false;
        }
    }

    public function isOllamaReachable(): bool {
        try {
            // Check the base URL to see if the service is up. Ollama responds to GET / with "Ollama is running"
            $url = config('ai.providers.ollama.url');
            $response = Http::timeout(2)
                ->withOptions(['verify' => false])
                ->get($url);
            
            return $response->status() === 200;
        } catch (\Exception) {
            return false;
        }
    }

    public function ocrConfidenceThreshold(): float { return 0.60; }
    public function llmConfidenceThreshold(): float { return 0.50; }

    private function canResolveHost(string $host): bool
    {
        if (function_exists('dns_get_record')) {
            return (bool) dns_get_record($host, DNS_A | DNS_AAAA);
        }

        $resolved = gethostbyname($host);

        return $resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP) !== false;
    }
}
