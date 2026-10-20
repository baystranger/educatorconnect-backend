<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a single uploaded file by kind. File type is checked by content
 * (mimetypes / mimes), not just the extension.
 */
class MediaUploadRequest extends FormRequest
{
    public const KINDS = ['image', 'video', 'resume', 'document'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kind = $this->route('kind') ?? $this->input('kind', 'image');
        $limits = config('platform.uploads');

        $fileRules = match ($kind) {
            'video' => ['mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.$limits['video_max_kb']],
            'resume' => ['mimes:pdf,doc,docx', 'max:'.$limits['resume_max_kb']],
            'document' => ['mimes:pdf', 'max:'.$limits['document_max_kb']],
            default => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$limits['image_max_kb'], 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'],
        };

        return [
            'kind' => ['sometimes', Rule::in(self::KINDS)],
            'file' => ['required', 'file', ...$fileRules],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        $kind = $this->route('kind') ?? $this->input('kind', 'image');
        $maxMb = (int) round(config('platform.uploads.'.match ($kind) {
            'video' => 'video_max_kb', 'resume' => 'resume_max_kb', 'document' => 'document_max_kb', default => 'image_max_kb',
        }) / 1024);

        return [
            'file.max' => ($kind === 'video' ? 'Videos' : 'Files').' must be '.$maxMb.' MB or smaller.',
            'file.mimetypes' => 'Upload an MP4, MOV or WebM video.',
            'file.mimes' => 'This file type is not supported.',
            'file.dimensions' => 'Images must be at least 200 × 200 pixels.',
        ];
    }
}
