<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\ImportLog;
use App\Services\ImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    protected $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    public function showUpload(Group $group)
    {
        return view('imports.upload', compact('group'));
    }

    public function import(Request $request, Group $group)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        try {
            $importLog = $this->importService->importCsv($path, $group);
            return redirect()->route('groups.imports.report', [$group, $importLog])
                ->with('success', 'CSV processed successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['csv_file' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    public function showReport(Group $group, ImportLog $importLog)
    {
        $anomalies = $importLog->anomalies()->orderBy('row_number', 'asc')->get();
        return view('imports.report', compact('group', 'importLog', 'anomalies'));
    }
}
