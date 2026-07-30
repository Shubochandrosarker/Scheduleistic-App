<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesToWorkspace;
use App\Models\MediaAsset;
use App\Services\MediaLibrary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Upload validation.
 *
 * Three separate defences, because any one of them alone is bypassable:
 *
 *  - `mimetypes:` makes Laravel read the file's *actual* content type rather
 *    than trusting the client-supplied `Content-Type` or the extension.
 *  - The allow-list below is an allow-list, not a deny-list. SVG is absent on
 *    purpose: it is an executable document, and there is no safe way to serve
 *    an untrusted one from the same origin as the dashboard.
 *  - The storage quota is checked against the plan before a byte is written.
 */
class StoreMediaAssetRequest extends FormRequest
{
    use ScopesToWorkspace;

    /** Content types the library accepts. Extend deliberately, never with a wildcard. */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('create', MediaAsset::class);
    }

    public function rules(): array
    {
        return [
            'workspace_id'    => $this->workspaceIdRules(),
            'media_folder_id' => $this->belongsToWorkspaceRules('media_folders'),
            'file'            => [
                'required',
                'file',
                'max:'.(config('media.max_upload_mb', 512) * 1024),
                'mimetypes:'.implode(',', self::ALLOWED_MIMES),
            ],
            'alt_text' => ['nullable', 'string', 'max:1000'],
            'notes'    => ['nullable', 'string', 'max:2000'],
            'tags'     => ['nullable', 'array', 'max:20'],
            'tags.*'   => ['string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'That file type is not supported. Upload a JPEG, PNG, WebP, GIF, MP4, MOV or WebM file.',
        ];
    }

    /**
     * Quota is checked here rather than in the controller so a rejected upload
     * never touches storage and the caller gets a normal validation error.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->hasFile('file')) {
                return;
            }

            $workspace = $this->workspace();
            $library   = app(MediaLibrary::class);
            $bytes     = $this->file('file')->getSize() ?: 0;

            if (! $library->hasRoomFor($workspace->team, $bytes)) {
                $validator->errors()->add(
                    'file',
                    sprintf(
                        'This upload would exceed your %s storage allowance. Archive or delete assets, or upgrade your plan.',
                        $library->quotaLabel($workspace->team),
                    ),
                );
            }
        });
    }
}
