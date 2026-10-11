<?php

namespace App\Services\Reports;

use App\Models\Reports\ExportJob;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Writes a report to a private file for one export request. Rows are
 * produced by the same definition the screen uses, as the requesting user,
 * so an export can never contain more than that user may see.
 */
class ReportExporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /**
     * PDFs are for printing and sharing summaries, not bulk data.
     */
    public const PDF_MAX_ROWS = 2000;

    public function __construct(private ReportRegistry $registry) {}

    /**
     * @return array{path: string, rows: int}
     */
    public function write(ExportJob $export): array
    {
        $report = $this->registry->find($export->report) ?? throw new RuntimeException("Unknown report [{$export->report}].");
        $user = $export->user;

        if (! $user instanceof User || ! $user->is_active || ! $report->canExport($user)) {
            throw new RuntimeException('The requesting user may no longer export this report.');
        }

        $filters = $report->resolveFilters($user, $export->parameters);
        $columns = $report->columns($filters);
        $rows = $report->rows($user, $filters);
        $path = "exports/{$export->uuid}.{$export->format}";

        $count = match ($export->format) {
            'csv' => $this->writeCsv($path, $columns, $rows),
            'xlsx' => $this->writeSpreadsheet($path, $report, $columns, $rows),
            'pdf' => $this->writePdf($path, $export, $report, $filters, $columns, $rows),
            default => throw new RuntimeException("Unsupported format [{$export->format}]."),
        };

        return ['path' => $path, 'rows' => $count];
    }

    /**
     * @param  list<array{key: string, label: string, numeric: bool}>  $columns
     * @param  iterable<array<string, scalar|null>>  $rows
     */
    private function writeCsv(string $path, array $columns, iterable $rows): int
    {
        $stream = fopen('php://temp', 'w+b');
        // Byte order mark so spreadsheet software reads names with accents correctly.
        fwrite($stream, "\u{FEFF}");
        fputcsv($stream, array_column($columns, 'label'), escape: '');
        $count = 0;

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (array $column) => self::csvSafe($row[$column['key']] ?? null), $columns), escape: '');
            $count++;
        }

        rewind($stream);
        Storage::disk('local')->writeStream($path, $stream);
        fclose($stream);

        return $count;
    }

    /**
     * @param  list<array{key: string, label: string, numeric: bool}>  $columns
     * @param  iterable<array<string, scalar|null>>  $rows
     */
    private function writeSpreadsheet(string $path, ReportDefinition $report, array $columns, iterable $rows): int
    {
        $sheet = new ReportSpreadsheet($report->title(), $columns, $rows);
        Excel::store($sheet, $path, 'local', ExcelFormat::XLSX);

        return $sheet->rowCount;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<array{key: string, label: string, numeric: bool}>  $columns
     * @param  iterable<array<string, scalar|null>>  $rows
     */
    private function writePdf(string $path, ExportJob $export, ReportDefinition $report, array $filters, array $columns, iterable $rows): int
    {
        $collected = [];
        $truncated = false;

        foreach ($rows as $row) {
            if (count($collected) >= self::PDF_MAX_ROWS) {
                $truncated = true;

                break;
            }

            $collected[] = $row;
        }

        $pdf = Pdf::loadView('reports.pdf', [
            'title' => $report->title(),
            'description' => $report->description(),
            'filters' => $report->describeFilters($filters),
            'columns' => $columns,
            'rows' => $collected,
            'truncated' => $truncated,
            'maxRows' => self::PDF_MAX_ROWS,
            'personal' => $report->containsPersonalData(),
            'generatedFor' => $export->user->name,
            'generatedAt' => now()->toDayDateTimeString(),
            'reference' => $export->uuid,
        ])->setPaper('a4', count($columns) > 6 ? 'landscape' : 'portrait');

        Storage::disk('local')->put($path, $pdf->output());

        return count($collected);
    }

    /**
     * Neutralise spreadsheet formula injection: text beginning with a
     * formula trigger is prefixed with an apostrophe.
     */
    public static function csvSafe(string|int|float|bool|null $value): string|int|float|null
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        $value = is_bool($value) ? ($value ? 'Yes' : 'No') : $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
