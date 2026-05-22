<?php

namespace App\Ai\Services;

use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Ai;
use Laravel\Ai\Files\LocalImage;
use Laravel\Ai\Messages\UserMessage;

class OcrResult {
    public function __construct(
        public string $text,
        public float $confidence,
        public string $mode, // 'offline' or 'cloud-enhanced'
        public string $provider,
        public string $model
    ) {}
}

class OcrService {
    public function extract($imageFile): OcrResult {
        $connectivity = new ConnectivityService();

        $localResult = $this->runTesseract($imageFile);

        if ($localResult->confidence >= $connectivity->ocrConfidenceThreshold()) {
            return new OcrResult($localResult->text, $localResult->confidence, 'offline', 'tesseract', 'tesseract');
        }

        if ($connectivity->isOnline()) {
            try {
                $cloudText = $this->runGeminiVision($imageFile);
                $model = config('ai.models.text.providers.gemini_ocr')
                    ?? config('ai.models.text.providers.gemini')
                    ?? 'gemini-3.1-flash-lite-preview';

                return new OcrResult($cloudText, 0.95, 'cloud-enhanced', 'gemini', $model);
            } catch (RequestException $exception) {
                report($exception);
                return new OcrResult($localResult->text, $localResult->confidence, 'offline', 'tesseract', 'tesseract');
            }
        }

        return new OcrResult($localResult->text, $localResult->confidence, 'offline', 'tesseract', 'tesseract');
    }

    private function runTesseract($imageFile): OcrResult
    {
        $imagePath = $this->normalizeImagePath($imageFile);
        $outputBase = tempnam(sys_get_temp_dir(), 'tess_');

        if ($outputBase === false) {
            return new OcrResult('', 0.0, 'offline');
        }

        $outputTextFile = $outputBase . '.txt';
        $escapedImage = escapeshellarg($imagePath);
        $escapedOutput = escapeshellarg($outputBase);

        exec(sprintf('tesseract %s %s 2>&1', $escapedImage, $escapedOutput), $output, $status);

        $text = '';
        if ($status === 0 && file_exists($outputTextFile)) {
            $text = trim((string) file_get_contents($outputTextFile));
        }

        @unlink($outputTextFile);

        $confidence = $this->estimateConfidence($text, $status);
        return new OcrResult($text, $confidence, 'offline', 'tesseract', 'tesseract');
    }

    private function runGeminiVision($imageFile): string
    {
        $imagePath = $this->normalizeImagePath($imageFile);

        $provider = Ai::textProvider('gemini');
        $model = config('ai.models.text.providers.gemini_ocr')
            ?? config('ai.models.text.providers.gemini')
            ?? $provider->defaultTextModel();

        $instructions = 'You are an OCR assistant. Extract the text from the attached image as accurately as possible. Return only the raw extracted text.';
        $messages = [
            new UserMessage(
                'Please extract the text from the attached image and return only the raw extracted text.',
                [new LocalImage($imagePath)]
            ),
        ];

        $response = $provider->textGateway()->generateText(
            $provider,
            $model,
            $instructions,
            $messages,
            [],
            null,
            null,
            30
        );

        return trim($response->text);
    }

    private function normalizeImagePath($imageFile): string
    {
        if (is_string($imageFile) && file_exists($imageFile)) {
            return $imageFile;
        }

        if (is_object($imageFile) && method_exists($imageFile, 'getPathname')) {
            $path = $imageFile->getPathname();
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new \InvalidArgumentException('Unsupported or missing image file path for OCR extraction.');
    }

    private function estimateConfidence(string $text, int $status): float
    {
        if ($status !== 0 || $text === '') {
            return 0.0;
        }

        $length = strlen(preg_replace('/\s+/', '', $text));
        if ($length === 0) {
            return 0.0;
        }

        return min(0.95, max(0.35, min(1.0, $length / 300.0)));
    }
}
