<?php

namespace App\Ai\Orchestrator;

use App\Ai\Agents\ExamPrepAgent;
use App\Ai\Agents\HelpdeskAgent;
use App\Ai\Agents\HomeworkHelperAgent;
use App\Ai\Agents\StudyRecommenderAgent;
use App\Ai\Services\IntentClassifier;
use App\Ai\Services\OcrService;
use App\Http\Controllers\Api\HomeworkController;
use App\Http\Controllers\Api\ExamPrepController;
use App\Http\Controllers\Api\RecommendController;
use App\Http\Requests\HomeworkSubmitRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Brain
{
    public function __construct(
        protected OcrService $ocrService,
        protected IntentClassifier $intentClassifier
    ) {}

    public function handle(array $input): array
    {
        if (strtolower(trim((string) ($input['is_exam_active'] ?? 'false'))) === 'true') {
            throw new HttpResponseException(response()->json([
                'message' => 'Cannot access exam prep during an active exam session',
            ], 403));
        }

        $input = $this->normalise($input);

        $input = $this->prepareImagePayload($input);

        $explicitIntent = $this->extractExplicitIntent($input);
        if ($explicitIntent !== null) {
            return $this->dispatch($explicitIntent, $input, 'explicit');
        }

        $ruleIntent = $this->detectIntentFromRules($input);
        if ($ruleIntent !== null) {
            return $this->dispatch($ruleIntent, $input, 'rules');
        }

        $aiIntent = $this->intentClassifier->classify($input);

        return $this->dispatch($aiIntent, $input, 'ai');
    }

    private function prepareImagePayload(array $input): array
    {
        $imageSource = null;
        $tempPath = null;

        if (! empty($input['stored_image_path'] ?? null)) {
            $storedPath = trim((string) $input['stored_image_path']);
            $candidate = \Illuminate\Support\Facades\Storage::disk('local')->path($storedPath);
            if (file_exists($candidate)) {
                $imageSource = $candidate;
            } else {
                report(new \RuntimeException("Stored image path not found: {$candidate}"));
                unset($input['stored_image_path']);
            }
        }

        if ($imageSource === null && ! empty($input['image_base64'] ?? null)) {
            $tempPath = tempnam(sys_get_temp_dir(), 'ai_img_');
            if ($tempPath !== false) {
                file_put_contents($tempPath, base64_decode((string) $input['image_base64']));
                $imageSource = $tempPath;
            }
        }

        if ($imageSource === null) {
            return $input;
        }

        $ocrResult = $this->ocrService->extract($imageSource);

        $input['ocr_text'] = $ocrResult->text;
        $input['ocr_confidence'] = $ocrResult->confidence;
        $input['ocr_mode'] = $ocrResult->mode;
        $input['ocr_provider'] = $ocrResult->provider;
        $input['ocr_model'] = $ocrResult->model;
        $input['llm_confidence'] = $ocrResult->mode === 'cloud-enhanced' ? 0.95 : 0.0;
        $input['text'] = trim(((string) ($input['text'] ?? '')) . "\n" . $ocrResult->text);

        if ($tempPath !== null && file_exists($tempPath)) {
            @unlink($tempPath);
        }

        return $input;
    }

    /**
     * Decode multipart JSON fields and shape recommend payloads before routing.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        foreach (['quiz_results', 'completed_videos', 'content_catalogue', 'syllabus_topics', 'student_scores'] as $field) {
            if (! isset($input[$field]) || ! is_string($input[$field])) {
                continue;
            }

            $decoded = json_decode($input[$field], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $input[$field] = $decoded;
            }
        }

        $hasImage = ! empty($input['image_base64'] ?? null)
            || ! empty($input['stored_image_path'] ?? null)
            || ! empty($input['image'] ?? null)
            || ! empty($input['image_url'] ?? null);

        if (! $hasImage && ! empty($input['quiz_results']) && is_array($input['quiz_results'])) {
            $input['student_id'] = $input['student_id'] ?? null;
            $input['grade_level'] = $input['grade_level'] ?? null;
            $input['quiz_results'] = $input['quiz_results'];
            $input['completed_videos'] = is_array($input['completed_videos'] ?? null)
                ? $input['completed_videos']
                : [];
            $input['content_catalogue'] = is_array($input['content_catalogue'] ?? null)
                ? $input['content_catalogue']
                : [];
        }

        if (! $hasImage && empty($input['quiz_results']) && ! empty($input['exam_subject'])) {
            $input['exam_subject'] = (string) $input['exam_subject'];
            $input['exam_date'] = (string) ($input['exam_date'] ?? '');
            $input['student_id'] = (string) ($input['student_id'] ?? '');
            $input['grade_level'] = (string) ($input['grade_level'] ?? '');
            $input['days_remaining'] = (int) ($input['days_remaining'] ?? 0);
            $input['syllabus_topics'] = is_array($input['syllabus_topics'] ?? null)
                ? $input['syllabus_topics']
                : [];
            $input['student_scores'] = is_array($input['student_scores'] ?? null)
                ? $input['student_scores']
                : [];
            $input['completed_videos'] = is_array($input['completed_videos'] ?? null)
                ? $input['completed_videos']
                : [];
        }

        return $input;
    }

    private function dispatch(string $intent, array $input, string $source): array
    {
        if ($intent === 'homework') {
            $input = $this->validateHomeworkPayload($input);
        }

        $agent = $this->resolveAgent($intent);
        $result = $agent->handle($input);

        if ($intent === 'homework') {
            app(HomeworkController::class)->store($result, $input);
        }

        if ($intent === 'recommender') {
            app(RecommendController::class)->store($result, $input);
        }

        if ($intent === 'exam_prep') {
            app(ExamPrepController::class)->store($result, $input);
        }

        $result['intent'] = $intent;
        $result['intent_source'] = $source;

        return $result;
    }

    private function resolveAgent(string $intent): HomeworkHelperAgent|StudyRecommenderAgent|ExamPrepAgent|HelpdeskAgent
    {
        return match ($intent) {
            'homework' => app(HomeworkHelperAgent::class),
            'recommender' => app(StudyRecommenderAgent::class),
            'exam_prep' => app(ExamPrepAgent::class),
            'helpdesk' => new HelpdeskAgent(),
            default => new HelpdeskAgent(),
        };
    }

    private function validateHomeworkPayload(array $input): array
    {
        $request = new HomeworkSubmitRequest();
        $validator = Validator::make($input, $request->rules());

        if ($request->authorize() === false) {
            throw new ValidationException($validator, response()->json(['message' => 'Unauthorized homework payload.'], 403));
        }

        $validator->validate();

        $roleName = strtolower(trim((string) ($input['role_name'] ?? '')));
        if (! in_array($roleName, ['student', 'instructor'], true)) {
            throw ValidationException::withMessages([
                'role_name' => ['Only student or instructor role_name is allowed for homework requests.'],
            ]);
        }

        return $input;
    }

    private function extractExplicitIntent(array $input): ?string
    {
        if (! empty($input['tags'] ?? null)) {
            $tags = $input['tags'];

            if (is_string($tags)) {
                $tags = preg_split('/[,|\s]+/', $tags) ?: [];
            }

            if (is_array($tags)) {
                $normalized = array_map(
                    fn ($t) => strtolower(trim((string) $t)),
                    $tags
                );

                if (in_array('homework', $normalized, true)) {
                    return 'homework';
                }

                if (in_array('recommender', $normalized, true) || in_array('recommendation', $normalized, true)) {
                    return 'recommender';
                }

                if (in_array('exam_prep', $normalized, true) || in_array('exam-prep', $normalized, true) || in_array('exam', $normalized, true)) {
                    return 'exam_prep';
                }

                if (in_array('helpdesk', $normalized, true) || in_array('support', $normalized, true)) {
                    return 'helpdesk';
                }
            }
        }

        $raw = $input['intent'] ?? $input['agent'] ?? $input['type'] ?? null;

        if ($raw === null) {
            return null;
        }

        $intent = strtolower(trim((string) $raw));

        return match ($intent) {
            'homework' => 'homework',
            'recommender' => 'recommender',
            'exam_prep', 'exam-prep', 'exam' => 'exam_prep',
            'helpdesk', 'help' => 'helpdesk',
            default => null,
        };
    }

    private function detectIntentFromRules(array $input): ?string
    {
        $hasImage = !empty($input['image_base64'] ?? null)
            || !empty($input['stored_image_path'] ?? null)
            || !empty($input['image'] ?? null)
            || !empty($input['image_url'] ?? null);

        if ($hasImage) {
            return 'homework';
        }

        if (! empty($input['quiz_results']) && is_array($input['quiz_results'])) {
            return 'recommender';
        }

        if (! empty($input['exam_subject'])) {
            return 'exam_prep';
        }

        $recoKeys = ['weak_subjects', 'completed_videos', 'completed_topics', 'grade_level', 'weak_topics'];
        foreach ($recoKeys as $key) {
            if (!empty($input[$key] ?? null)) {
                return 'recommender';
            }
        }

        if (!empty($input['question'] ?? null) || !empty($input['help'] ?? null)) {
            return 'helpdesk';
        }

        $freeText = strtolower(trim((string) ($input['text'] ?? $input['message'] ?? '')));
        $hasRole = !empty($input['user_role'] ?? null) || !empty($input['role'] ?? null);
        if ($hasRole && $this->looksLikeHelpdeskText($freeText)) {
            return 'helpdesk';
        }

        if (!empty($input['text'] ?? null)) {
            $freeText = strtolower(trim((string) $input['text']));
            if (!$this->looksLikeHelpdeskText($freeText)) {
                return 'homework';
            }
        }

        return null;
    }

    private function looksLikeHelpdeskText(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $keywords = [
            'login', 'password', 'payment', 'billing', 'invoice',
            'upload', 'account', 'subscription', 'support', 'help',
            'request tutor', 'how do i', 'cannot access', 'error on site',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
