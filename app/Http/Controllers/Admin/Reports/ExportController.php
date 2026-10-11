<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Models\Reports\ExportJob;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Queues report exports and serves the finished files to the person who
 * requested them. Every request is recorded with its filters and, for
 * personal data, a stated purpose.
 */
class ExportController extends Controller
{
    public function store(Request $request, string $report, ReportRegistry $registry): RedirectResponse
    {
        $user = $request->user();
        $definition = $registry->find($report);
        abort_if($definition === null || ! $definition->authorize($user), 404);
        abort_unless($definition->canExport($user), 403);

        $validated = $request->validate([
            'format' => ['required', Rule::in(ReportExporter::FORMATS)],
            'purpose' => [Rule::requiredIf($definition->containsPersonalData()), 'nullable', 'string', 'min:10', 'max:500'],
            'filters' => ['nullable', 'array'],
        ], [
            'purpose.required' => 'State why you need this personal data. The purpose is recorded with the export.',
        ]);

        $pending = ExportJob::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ExportJob::STATUS_QUEUED, ExportJob::STATUS_RUNNING])
            ->count();

        if ($pending >= config('lccms.exports.max_pending_per_user')) {
            throw ValidationException::withMessages(['format' => 'Wait for your running exports to finish before starting another.']);
        }

        $filters = $definition->resolveFilters($user, $validated['filters'] ?? []);

        $export = ExportJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'report' => $definition->key(),
            'format' => $validated['format'],
            'parameters' => $definition->serializeFilters($filters),
            'classification' => $definition->containsPersonalData() ? 'personal' : 'aggregate',
            'purpose' => $validated['purpose'] ?? null,
        ]);

        GenerateReportExport::dispatch($export);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Export started. It will appear below when ready.']);

        return back();
    }

    public function download(Request $request, ExportJob $export): StreamedResponse
    {
        abort_unless($export->user_id === $request->user()->id, 404);
        abort_unless($export->isDownloadable(), 410, 'This export has expired. Generate it again.');
        abort_unless(Storage::disk('local')->exists($export->file_path), 410, 'This export has expired. Generate it again.');

        $export->increment('download_count');

        activity('reports')
            ->causedBy($request->user())
            ->performedOn($export)
            ->event('downloaded')
            ->withProperties(['report' => $export->report, 'format' => $export->format, 'classification' => $export->classification])
            ->log('Downloaded report export');

        $filename = sprintf('%s-%s.%s', $export->report, $export->created_at->format('Ymd-His'), $export->format);

        return Storage::disk('local')->download($export->file_path, $filename, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
