<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the first sheet of an uploaded CSV/XLSX into associative rows keyed by
 * the slugged heading row. Validation happens in the domain validators.
 */
class SpreadsheetRows implements ToArray, WithHeadingRow
{
    /**
     * @param  array<array-key, mixed>  $array
     */
    public function array(array $array): void
    {
        // Rows are read with Excel::toArray(); nothing to persist here.
    }
}
