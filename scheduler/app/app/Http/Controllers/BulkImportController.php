<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Workspace;
use App\Services\BulkImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BulkImportController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function store(Request $request, Workspace $workspace, BulkImporter $importer): RedirectResponse
    {
        $this->guardWorkspace($request, $workspace);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $rows   = $importer->parseCsv($request->file('file')->get());
        $result = $importer->import($workspace, $rows, $request->user()->id);

        return back()->with('status', "imported-{$result['created']}-skipped-{$result['skipped']}");
    }
}
