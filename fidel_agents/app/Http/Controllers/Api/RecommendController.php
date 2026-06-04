<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\Services\RecommendRequestPersistor;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class RecommendController extends Controller
{
    public function __construct(protected RecommendRequestPersistor $persistor) {}

    public function store(array $result, array $payload): array
    {
        if (empty($payload['student_id'])) {
            throw ValidationException::withMessages([
                'student_id' => ['The student_id field is required.'],
            ]);
        }

        $this->persistor->storeRecommendRequest($result, $payload);

        return $result;
    }
}
