<?php

namespace App\Http\Controllers;
use League\Csv\Reader;

use Illuminate\Http\Request;
use App\Actions\ProcessNamesForImport;
use App\Services\StudentEmailGenerator;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\AuditLog;


class ImportController extends Controller
{
    public function index()
    {

        $imports = auth()->user()
            ->imports()
            ->latest()
            ->get();

        return view('imports.index', compact('imports'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv' => ['required','file','mimes:csv,txt','max:5120'],
        ]);

        $file = $request->file('csv');
        $path = $file->store('imports');

        $import = Import::create([
            'user_id' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'status' => 'uploaded',
        ]);

        // Read CSV
        $csv = Reader::createFromPath(storage_path('app/'.$path), 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();

        // ✅ Bulk insert rows (fast)
        $batch = [];
        $now = now();
        $inserted = 0;

        foreach ($records as $record) {
            $batch[] = [
                'import_id' => $import->id,

                'FirstName' => $record['FirstName'] ?? null,
                'LastName' => $record['LastName'] ?? null,
                'email_address' => $record['email_address'] ?? null,
                'Password' => $record['Password'] ?? null,
                'UnitPath' => $record['UnitPath'] ?? null,
                'personalEmail' => $record['personalEmail'] ?? null,
                'studentPhone' => $record['studentPhone'] ?? null,
                'Title' => $record['Title'] ?? null,
                'studentDepartment' => $record['studentDepartment'] ?? null,
                'DepartmentName' => $record['DepartmentName'] ?? null, // ✅ this is your Dept display
                'ChangePassNext' => $record['ChangePassNext'] ?? null,

                // defaults
                'email_status' => 'pending',

                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                ImportRow::insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            ImportRow::insert($batch);
            $inserted += count($batch);
        }

        // Update import stage after parsing
        $import->update([
            'status' => 'parsed',
        ]);

        // ✅ Initialize progress fields for name processing (background job)
        $import->update([
            'status' => 'processing_names',
            'generation_stage' => 'processing_names',
            'generation_total' => $inserted,
            'generation_done' => 0,
        ]);

        // Audit log (no heavy queries)
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'import_uploaded',
            'entity_type' => 'Import',
            'entity_id' => $import->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 500),
            'metadata' => [
                'rows_count' => $inserted,
                'stored_path' => $path,
            ],
        ]);

        // ✅ Process names in background to avoid timeout
        \App\Jobs\ProcessNamesJob::dispatch($import->id)->onQueue('imports');

        // Return JSON redirect for your AJAX uploader, else normal redirect
        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('imports.show', $import),
            ]);
        }

        return redirect()->route('imports.show', $import);
    }


    public function show(Import $import) {
        abort_unless($import->user_id === auth()->id(), 403);
        return view('imports.show', compact('import'));
    }

    public function rowsJson(Import $import)
    {
        abort_unless($import->user_id === auth()->id(), 403);

        return response()->json([
            'data' => $import->rows()->orderBy('id')->get(),
        ]);
    }

    public function processNames(\App\Models\Import $import, ProcessNamesForImport $action)
    {
        abort_unless($import->user_id === auth()->id(), 403);

        $count = $action->handle($import);

        return back()->with('status', "Processed names for {$count} rows.");
    }

    public function generateEmails(Request $request, \App\Models\Import $import)
    {
        abort_unless($import->user_id === auth()->id(), 403);

        $data = $request->validate([
            'template' => ['required', 'string', 'max:255'],
            'semesterprefix' => ['required', 'string', 'max:50'],
            'domain' => ['required', 'string', 'max:100'],
        ]);

        // Basic safety: must contain @ and {domain}
        if (!str_contains($data['template'], '@') || !str_contains($data['template'], '{domain}')) {
            return back()->with('status', 'Template must include @ and {domain} token.');
        }

        // (Optional but recommended) normalize inputs
        $template = trim($data['template']);
        $semesterPrefix = trim($data['semesterprefix']);
        $domain = strtolower(trim($data['domain']));

        // Initialize progress tracking
        $total = $import->rows()->count();

        $import->update([
            'status' => 'generating',
            'generation_stage' => 'generating',
            'generation_total' => $total,
            'generation_done' => 0,
        ]);

        // Audit
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'email_generation_started',
            'entity_type' => 'Import',
            'entity_id' => $import->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 500),
            'metadata' => [
                'template' => $template,
                'semesterprefix' => $semesterPrefix,
                'domain' => $domain,
                'rows_total' => $total,
            ],
        ]);

        // Dispatch background job (progress bar can poll /report.json)
        \App\Jobs\GenerateEmailsJob::dispatch(
            $import->id,
            $template,
            $semesterPrefix,
            $domain
        )->onQueue('emails');

        return back()->with('status', 'Email generation started. Progress will update automatically.');
    }


    public function reportJson(Import $import)
    {
        abort_unless($import->user_id === auth()->id(), 403);

        $total = $import->rows()->count();
        $generated = $import->rows()->whereNotNull('generated_email')->count();

        $pending = $import->rows()->where('email_status', 'pending')->count();
        $queued  = $import->rows()->where('email_status', 'queued')->count();
        $sent    = $import->rows()->where('email_status', 'sent')->count();
        $failed  = $import->rows()->where('email_status', 'failed')->count();

        // duplicates count (within import) for generated_email
        $dupCount = $import->rows()
            ->selectRaw('LOWER(generated_email) as e, COUNT(*) as c')
            ->whereNotNull('generated_email')
            ->groupBy('e')
            ->having('c', '>', 1)
            ->get()
            ->sum('c');

        return response()->json([
            'total' => $total,
            'generated' => $generated,
            'duplicates' => $dupCount,
            'pending' => $pending,
            'queued' => $queued,
            'sent' => $sent,
            'failed' => $failed,
            'status' => $import->status,
            'generation_total' => $import->generation_total,
            'generation_done' => $import->generation_done,
            'generation_stage' => $import->generation_stage,
        ]);
    }

    public function updateRowEmail(Request $request, Import $import, \App\Models\ImportRow $row)
    {
        abort_unless($import->user_id === auth()->id(), 403);
        abort_unless($row->import_id === $import->id, 404);

        $data = $request->validate([
            'generated_email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['generated_email']));

        // prevent duplicates within same import
        $exists = $import->rows()
            ->where('id', '!=', $row->id)
            ->whereRaw('LOWER(generated_email) = ?', [$email])
            ->exists();

        if ($exists) {
            return response()->json([
                'ok' => false,
                'message' => 'Duplicate email exists in this import.',
            ], 422);
        }

        $row->generated_email = $email;
        $row->save();

        // optional audit log
        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'generated_email_edited',
            'entity_type' => 'ImportRow',
            'entity_id' => $row->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 500),
            'metadata' => ['import_id' => $import->id, 'generated_email' => $email],
        ]);

        return response()->json(['ok' => true, 'generated_email' => $email]);
    }

    public function queueEmails(Request $request, Import $import)
    {
        abort_unless($import->user_id === auth()->id(), 403);

        // Queue all rows that have generated_email and are still pending/failed
        $rows = $import->rows()
            ->whereNotNull('generated_email')
            ->whereIn('email_status', ['pending', 'failed'])
            ->get();

        $queued = 0;

        foreach ($rows as $row) {
            // dispatch your job here (you’ll implement the job in the next step)
            // \App\Jobs\SendStudentPasswordEmailJob::dispatch($row->id)->onQueue('emails');

            $row->email_status = 'queued';
            $row->email_error = null;
            $row->save();

            $queued++;
        }

        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'emails_queued',
            'entity_type' => 'Import',
            'entity_id' => $import->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string)$request->userAgent(), 0, 500),
            'metadata' => ['queued_count' => $queued],
        ]);

        return back()->with('status', "Queued {$queued} emails.");
    }

    public function exportCsv(Import $import)
    {
        abort_unless($import->user_id === auth()->id(), 403);

        $filename = "import_{$import->id}_export.csv";

        $headers = [
            "Content-Type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$filename}",
        ];

        $columns = [
            'FirstName','LastName','email_address','Password','UnitPath','personalEmail','studentPhone',
            'Title','studentDepartment','DepartmentName','ChangePassNext',
            'first_name','middle_name','last_name','generated_email','email_status','email_error'
        ];

        $callback = function () use ($import, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $import->rows()->orderBy('id')->chunk(500, function ($rows) use ($file, $columns) {
                foreach ($rows as $row) {
                    $line = [];
                    foreach ($columns as $c) {
                        $line[] = $row->{$c};
                    }
                    fputcsv($file, $line);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }


}
