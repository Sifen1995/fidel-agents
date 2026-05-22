<?php

namespace App\Http\Controllers\Api;

use App\Ai\Services\HomeworkRequestPersistor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class HomeworkController extends Controller
{
    public function __construct(protected HomeworkRequestPersistor $persistor)
    {
    }

    public function store(array $result, array $payload): array
    {
        if (empty($payload['user_id'])) {
            throw ValidationException::withMessages(['user_id' => ['The user_id field is required.']]);
        }

        $this->persistor->storeHomeworkRequest($result, $payload);

        return $result;
    }
}
