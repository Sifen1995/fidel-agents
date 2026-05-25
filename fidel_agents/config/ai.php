<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Fidel Academy defaults to the host Ollama runtime for offline-first
    | operation. Set AI_DEFAULT_PROVIDER=gemini to prefer cloud fallback.
    |
    */

    'default' => env('AI_DEFAULT_PROVIDER', 'ollama'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://host.docker.internal:11434'),
            'timeout' => 120,
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Models
    |--------------------------------------------------------------------------
    */

    'models' => [

        'text' => [
            'default' => env('OLLAMA_MODEL'),
            'providers' => [
                'ollama' => env('OLLAMA_MODEL'),
                'gemini' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite-preview'),
                'gemini_ocr' => env('GEMINI_OCR_MODEL', env('GEMINI_MODEL', 'gemini-3.1-flash-lite-preview')),
            ],
        ],

        'embeddings' => [
            'default' => env('OLLAMA_EMBEDDING_MODEL'),
            'providers' => [
                'ollama' => env('OLLAMA_EMBEDDING_MODEL'),
                'gemini' => env('GEMINI_EMBEDDING_MODEL'),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failover
    |--------------------------------------------------------------------------
    |
    | Outline for optional cloud fallback when connectivity is restored.
    |
    */

    'failover' => [
        'enabled' => env('AI_FAILOVER_ENABLED', false),
        'primary' => 'ollama',
        'fallback' => 'gemini',
    ],

];
