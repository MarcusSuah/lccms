<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Enums\Access\Permission;
use App\Enums\Imports\ImportStatus;
use App\Http\Controllers\Controller;
use App\Models\Imports\ImportJob;
use App\Models\Imports\ImportRowError;
use App\Services\Imports\GeographyImportProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload → validate with preview → commit, for bulk geography registration.
 */
class GeographyImportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeImport($request);

        $imports = ImportJob::query()
            ->where('type', 'geography')
            ->when(! $request->user()->can(Permission::GeoNational->value), fn ($query) => $query->where('uploaded_by', $request->user()->id))
            ->with('uploader:id,name')
            ->latest('id')
            ->paginate(20)
            ->through(fn (ImportJob $import): array => $this->summary($import));

        return Inertia::render('admin/geography/imports/index', [
            'imports' => $imports,
            'columns' => GeographyImportProcessor::COLUMNS,
            'maxRows' => GeographyImportProcessor::MAX_ROWS,
        ]);
    }

    public function store(Request $request, GeographyImportProcessor $processor): RedirectResponse
    {
        $this->authorizeImport($request);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
        ]);

        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';

        $import = ImportJob::query()->create([
            'uuid' => $uuid,
            'type' => 'geography',
            'original_filename' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'stored_path' => $file->storeAs('imports', "{$uuid}.{$extension}", 'local'),
            'uploaded_by' => $request->user()->id,
        ]);

        $processor->validate($import, $request->user());

        return to_route('admin.geography.imports.show', $import);
    }

    public function show(Request $request, ImportJob $import): Response
    {
        $this->authorizeImport($request, $import);

        return Inertia::render('admin/geography/imports/show', [
            'import' => [
                ...$this->summary($import->load('uploader:id,name')),
                'preview' => $import->preview ?? [],
            ],
            'issues' => $import->rowErrors()
                ->orderBy('row_number')
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(fn (ImportRowError $error): array => $error->only(['row_number', 'column', 'message', 'severity'])),
        ]);
    }

    public function update(Request $request, ImportJob $import, GeographyImportProcessor $processor): RedirectResponse
    {
        $this->authorizeImport($request, $import);

        $validated = $request->validate(['action' => ['required', 'in:commit,discard']]);

        if ($validated['action'] === 'discard') {
            abort_if($import->status === ImportStatus::Committed, 422, 'A committed import cannot be discarded.');
            $import->forceFill(['status' => ImportStatus::Discarded])->save();
            Storage::disk('local')->delete($import->stored_path);
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Import discarded.')]);

            return to_route('admin.geography.imports.index');
        }

        $processor->commit($import, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __(':count records imported.', ['count' => $import->imported_rows])]);

        return to_route('admin.geography.imports.show', $import);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorizeImport($request);

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, GeographyImportProcessor::COLUMNS);
            fputcsv($handle, ['county', 'DEV-EXAMPLE', 'Example County (delete this row)', '', '', '', '', '', 'unverified']);
            fputcsv($handle, ['district', '', 'District #1', 'DEV-EXAMPLE', '1', '', '', '', 'unverified']);
            fclose($handle);
        }, 'geography-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeImport(Request $request, ?ImportJob $import = null): void
    {
        abort_unless($request->user()->can(Permission::GeographyImport->value), 403);

        if ($import !== null) {
            abort_unless($import->type === 'geography', 404);
            abort_unless(
                $import->uploaded_by === $request->user()->id || $request->user()->can(Permission::GeoNational->value),
                404,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ImportJob $import): array
    {
        return [
            'uuid' => $import->uuid,
            'original_filename' => $import->original_filename,
            'status' => $import->status->value,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'invalid_rows' => $import->invalid_rows,
            'imported_rows' => $import->imported_rows,
            'uploaded_by' => $import->uploader?->name,
            'created_at' => $import->created_at?->toIso8601String(),
            'committed_at' => $import->committed_at?->toIso8601String(),
        ];
    }
}
