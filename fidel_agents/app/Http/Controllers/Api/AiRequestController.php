<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\Orchestrator\Brain;
use App\Ai\Services\InputNormalizer;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiRequestController extends Controller
{
    public function __construct(
        protected Brain $brain,
        protected InputNormalizer $normalizer
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $this->normalizer->normalize($request);

        // Temporary debug: log normalized payload to help diagnose missing fields.
        Log::info('AI request payload', ['payload' => $payload]);

        try {
            $result = $this->brain->handle($payload);
        } catch (ValidationException $exception) {
            return response()->json([
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json($result, 200);
    }
}
