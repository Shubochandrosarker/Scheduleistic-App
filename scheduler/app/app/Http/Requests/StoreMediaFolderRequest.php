<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ScopesToWorkspace;
use App\Models\MediaFolder;
use Illuminate\Foundation\Http\FormRequest;

class StoreMediaFolderRequest extends FormRequest
{
    use ScopesToWorkspace;

    public function authorize(): bool
    {
        return $this->user()->can('create', MediaFolder::class);
    }

    public function rules(): array
    {
        return [
            'workspace_id' => $this->workspaceIdRules(),
            'parent_id'    => $this->belongsToWorkspaceRules('media_folders'),
            'name'         => ['required', 'string', 'max:80'],
        ];
    }
}
