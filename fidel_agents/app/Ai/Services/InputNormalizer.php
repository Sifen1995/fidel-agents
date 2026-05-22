<?php

namespace App\Ai\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InputNormalizer
{
    /**
     * Normalize request input into a consistent payload for the orchestrator.
     *
     * This keeps transport concerns (multipart file vs JSON body) out of
     * controllers and agent logic.
     *
     * @return array<string, mixed>
     */
    public function normalize(Request $request): array
    {
        $payload = $request->all();

        // Normalize image inputs to image_base64 (+ optional mime).
        // We accept several common keys from the frontend: image, file, photo, homework_image.
        $fileKey = null;
        foreach (['image', 'file', 'photo', 'homework_image'] as $candidate) {
            if ($request->hasFile($candidate)) {
                $fileKey = $candidate;
                break;
            }
        }

        if ($fileKey !== null) {
            $file = $request->file($fileKey);
            $contents = file_get_contents($file->getPathname()) ?: '';

            $payload['image_base64'] = base64_encode($contents);
            $payload['image_mime'] = $file->getClientMimeType();
            $payload['stored_image_path'] = $this->storeImageBytes(
                $contents,
                (string) ($payload['image_mime'] ?? 'image/jpeg')
            );
        } elseif (! empty($payload['image_base64'] ?? null)) {
            // Keep provided base64 as-is (may be pure base64 or data URL).
            $payload = $this->normalizeBase64Field($payload, 'image_base64');
        } else {
            // Backward compatibility: if frontend sends other keys as base64 strings.
            foreach (['image', 'file', 'photo', 'homework_image'] as $candidate) {
                if (! empty($payload[$candidate] ?? null) && is_string($payload[$candidate])) {
                    $payload = $this->normalizeBase64Field($payload, $candidate);
                    // Normalization copies into image_base64 / image_mime, so we can stop.
                    break;
                }
            }
        }

        return $payload;
    }

    /**
     * Normalize a base64/data-URL field into image_base64 (+ image_mime).
     */
    private function normalizeBase64Field(array $payload, string $field): array
    {
        $raw = (string) $payload[$field];

        $mimeFromDataUrl = $this->extractMimeFromDataUrl($raw);
        $base64 = $this->stripDataUrlPrefix($raw);

        $payload['image_base64'] = $base64;

        if ($mimeFromDataUrl !== null) {
            $payload['image_mime'] = $payload['image_mime'] ?? $mimeFromDataUrl;
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $base64) ?: $base64, true);
        if ($binary !== false) {
            $payload['stored_image_path'] = $this->storeImageBytes(
                $binary,
                (string) ($payload['image_mime'] ?? 'image/jpeg')
            );
        }

        return $payload;
    }

    private function extractMimeFromDataUrl(string $value): ?string
    {
        if (preg_match('/^data:(?<mime>[-\w.+\/]+);base64,/', $value, $matches) === 1) {
            return $matches['mime'];
        }

        return null;
    }

    private function stripDataUrlPrefix(string $value): string
    {
        return preg_replace('/^data:[-\w.+\/]+;base64,/', '', $value) ?? $value;
    }

    private function storeImageBytes(string $bytes, string $mimeType): string
    {
        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $directory = 'homework-requests/images/'.now()->format('Y/m/d');
        $path = $directory.'/'.Str::uuid().'.'.$extension;

        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}

