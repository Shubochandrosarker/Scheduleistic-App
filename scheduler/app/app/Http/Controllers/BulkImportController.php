<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\Workspace;
use App\Services\BulkImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BulkImportController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function create(Request $request, Workspace $workspace): Response
    {
        $this->guardWorkspace($request, $workspace);

        return Inertia::render('BulkImport/Create', [
            'workspace' => $workspace,
            'channels'  => $workspace->channels()->get(['id', 'provider', 'name']),
        ]);
    }

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
