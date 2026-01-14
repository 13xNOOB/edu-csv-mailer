<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\StudentEmailGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $importId,
        public string $template,
        public string $semesterPrefix,
        public string $domain
    ) {}

    public function handle(StudentEmailGenerator $gen): void
    {
        $import = Import::findOrFail($this->importId);

        $total = $import->rows()->count();
        $import->update([
            'status' => 'generating',
            'generation_stage' => 'generating',
            'generation_total' => $total,
            'generation_done' => 0,
        ]);

        $done = 0;

        // Process in chunks so we can update progress
        $import->rows()->orderBy('id')->chunk(200, function ($rows) use ($import, $gen, &$done) {
            foreach ($rows as $row) {
                // generate per-row using your generator logic
                // easiest: call generator helper you already wrote by reusing generateForImport in a row-wise method later
                // For now, we can do this by calling generateForImport once elsewhere.
                // Better: add a new method generateForRow(...) to StudentEmailGenerator and call it here.

                $done++;
            }

            $import->update(['generation_done' => $done]);
        });

        // If you don’t add generateForRow yet, keep using your old controller generation for now.
        // But for progress, you should add row-wise generation.

        $import->update([
            'status' => 'generated',
            'generation_stage' => null,
        ]);
    }
}
