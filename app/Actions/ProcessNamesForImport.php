<?php

namespace App\Actions;

use App\Models\Import;
use App\Services\NameSanitizer;
use Illuminate\Support\Facades\Log;

class ProcessNamesForImport
{
    public function __construct(
        private NameSanitizer $sanitizer
    ) {}

    public function handle(Import $import): int
    {
        $updated = 0;

        $rows = $import->rows()->orderBy('id')->get();

        foreach ($rows as $row) {
            $clean = $this->sanitizer->sanitizeNameString($row->FirstName);
            $split = $this->sanitizer->smartSplit($clean);

            $row->first_name = $split['first'];
            $row->middle_name = $split['middle'];
            $row->last_name = $split['last'];
            $row->save();

            $updated++;
        }

        Log::info('names_processed_for_import', [
            'import_id' => $import->id,
            'updated_rows' => $updated,
        ]);

        return $updated;
    }
}
