<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Services\ConnectivityService;
use Illuminate\View\View;

class HomeworkDemoController extends Controller
{
    public function index(): View
    {
        return view('homework.demo');
    }

    public function status(ConnectivityService $connectivity): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'online' => $connectivity->isOnline(),
            'ollama_reachable' => $connectivity->isOllamaReachable(),
        ]);
    }
}
