<?php

namespace App\Jobs;

use App\Actions\ProcessNamesForImport;
use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessNamesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $importId) {}

    public function handle(ProcessNamesForImport $action): void
    {
        $import = Import::findOrFail($this->importId);

        // run your existing action (it updates rows)
        $count = $action->handle($import);

        // mark progress complete
        $import->update([
            'status' => 'names_processed',
            'generation_stage' => null,
            'generation_done' => $import->generation_total ?: $count,
        ]);
    }
}
