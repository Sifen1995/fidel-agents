<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HomeworkSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer',
            'role_name' => 'required|string',
            'text' => 'nullable|string|required_without_all:image,image_base64,file,photo,homework_image,stored_image_path',
            'image' => 'nullable|file|mimes:jpeg,png,gif,webp|required_without_all:text,image_base64,stored_image_path',
            'file' => 'nullable|file|mimes:jpeg,png,gif,webp|required_without_all:text,image_base64,stored_image_path',
            'photo' => 'nullable|file|mimes:jpeg,png,gif,webp|required_without_all:text,image_base64,stored_image_path',
            'homework_image' => 'nullable|file|mimes:jpeg,png,gif,webp|required_without_all:text,image_base64,stored_image_path',
            'image_base64' => 'nullable|string|required_without_all:text,image,stored_image_path',
            'stored_image_path' => 'nullable|string|required_without_all:text,image_base64',
            'image_mime' => 'nullable|string',
            'subject_hint' => 'nullable|string',
            'grade_hint' => 'nullable|string',
            'ocr_text' => 'nullable|string',
            'ocr_confidence' => 'nullable|numeric',
            'ocr_mode' => 'nullable|string',
        ];
    }
}
