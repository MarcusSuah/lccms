<?php

namespace App\Jobs;

use App\Models\Reports\ExportJob;
use App\Services\Reports\ReportExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds one requested export in the background and records the outcome.
 */
class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public ExportJob $export) {}

    public function handle(ReportExporter $exporter): void
    {
        $this->export->forceFill(['status' => 'running'])->save();

        try {
            $result = $exporter->write($this->export);
        } catch (Throwable $exception) {
            $this->fail($exception);

            return;
        }

        $this->export->forceFill([
            'status' => 'completed',
            'file_path' => $result['path'],
            'row_count' => $result['rows'],
            'completed_at' => now(),
            'expires_at' => now()->addDays(config('lccms.exports.retention_days')),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Report export failed', ['export' => $this->export->uuid, 'error' => $exception?->getMessage()]);

        $this->export->forceFill([
            'status' => 'failed',
            // The detailed error stays in the log; users see a generic message.
            'error' => 'The export could not be generated. Try again or contact an administrator.',
            'completed_at' => now(),
        ])->save();
    }
}
