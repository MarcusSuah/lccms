<?php

namespace App\Services\Reports;

use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Report rows as an Excel sheet. Every value is written with an explicit
 * type, so text that starts with "=" is stored as text and never evaluated
 * as a formula when the file is opened.
 */
class ReportSpreadsheet implements FromGenerator, WithCustomValueBinder, WithHeadings, WithTitle
{
    public int $rowCount = 0;

    /**
     * @param  list<array{key: string, label: string, numeric: bool}>  $columns
     * @param  iterable<array<string, scalar|null>>  $rows
     */
    public function __construct(private string $title, private array $columns, private iterable $rows) {}

    public function generator(): Generator
    {
        foreach ($this->rows as $row) {
            $this->rowCount++;

            yield array_map(fn (array $column) => $row[$column['key']] ?? null, $this->columns);
        }
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $this->title) ?? 'Report', 0, 31);
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
        } elseif ($value === null) {
            $cell->setValueExplicit(null, DataType::TYPE_NULL);
        } else {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }

        return true;
    }
}
