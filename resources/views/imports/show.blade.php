{{-- resources/views/imports/show.blade.php --}}
<x-app-layout>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Progress bar (shows only while real background work is happening) --}}
    @if(in_array($import->status, ['processing_names','generating']))
        <div class="max-w-7xl mx-auto p-6">
            <div class="border rounded-xl bg-white shadow-sm p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm text-gray-700">
                        <span class="font-medium" id="gen-label">
                            {{ $import->status === 'processing_names' ? 'Processing names…' : 'Generating emails…' }}
                        </span>
                        <span class="text-gray-500">
                            (<span id="gen-done">0</span>/<span id="gen-total">0</span>)
                        </span>
                    </div>
                    <div class="text-sm text-gray-600">
                        <span id="gen-pct">0%</span>
                    </div>
                </div>

                <div class="mt-2 w-full bg-gray-200 rounded h-3 overflow-hidden">
                    <div id="gen-bar" class="h-3 bg-green-600" style="width:0%"></div>
                </div>

                <div class="mt-2 text-xs text-gray-500" id="gen-stage-hint">
                    This updates only when the server reports real progress (rows processed).
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto p-6 space-y-6">

        @if (session('status'))
            <div class="p-3 rounded-lg bg-green-100 text-green-800 border border-green-200">
                {{ session('status') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Import #{{ $import->id }}</h1>
                <div class="text-sm text-gray-600">
                    Status: <span class="font-semibold">{{ $import->status }}</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('imports.index') }}"
                   class="px-4 py-2 rounded-lg border bg-white hover:bg-gray-50">
                    Back to Imports
                </a>

                @if($import->status === 'generated')
                    <a href="{{ route('imports.exportCsv', $import) }}"
                       class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-black">
                        Export CSV (Server)
                    </a>

                    <form method="POST" action="{{ route('imports.queueEmails', $import) }}">
                        @csrf
                        <button class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-700">
                            Send Emails (Queue)
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Step 1: Email format inputs --}}
        <div class="border rounded-xl bg-white shadow-sm">
            <div class="p-4 border-b">
                <h2 class="font-semibold text-lg">Step 1 — Email Generation Settings</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Build any permutation using tokens. Example:
                    <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{first}.{middle}.{department}.{semesterprefix}@{domain}</span>
                </p>
            </div>

            <div class="p-4">
                <form method="POST" action="{{ route('imports.generateEmails', $import) }}" class="space-y-3">
                    @csrf

                    <div>
                        <label class="block font-medium">Email template</label>
                        <input name="template"
                               class="w-full border rounded-lg p-2 font-mono text-sm"
                               value="{first}.{last}.{department}.{semesterprefix}@{domain}">
                        <p class="text-sm text-gray-600 mt-2">
                            Tokens:
                            <span class="font-mono">{first}</span>
                            <span class="font-mono">{middle}</span>
                            <span class="font-mono">{last}</span>
                            <span class="font-mono">{fi}</span>
                            <span class="font-mono">{mi}</span>
                            <span class="font-mono">{li}</span>
                            <span class="font-mono">{department}</span>
                            <span class="font-mono">{semesterprefix}</span>
                            <span class="font-mono">{domain}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium">Semester prefix</label>
                            <input name="semesterprefix" class="w-full border rounded-lg p-2" placeholder="sp24" required>
                        </div>
                        <div>
                            <label class="block font-medium">Domain</label>
                            <input name="domain" class="w-full border rounded-lg p-2" placeholder="education.edu" required>
                        </div>
                    </div>

                    <button class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">
                        Generate Emails
                    </button>
                </form>
            </div>
        </div>

        {{-- Step 2: Report panel (dynamic client-side) --}}
        @if($import->status === 'generated')
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="p-4 border rounded-xl bg-white shadow-sm">
                    <div class="text-sm text-gray-500">Total</div>
                    <div id="r_total" class="text-2xl font-semibold mt-1">-</div>
                </div>
                <div class="p-4 border rounded-xl bg-white shadow-sm">
                    <div class="text-sm text-gray-500">Generated</div>
                    <div id="r_generated" class="text-2xl font-semibold mt-1">-</div>
                </div>
                <div class="p-4 border rounded-xl bg-white shadow-sm">
                    <div class="text-sm text-gray-500">Duplicates</div>
                    <div id="r_duplicates" class="text-2xl font-semibold mt-1">-</div>
                </div>
                <div class="p-4 border rounded-xl bg-white shadow-sm">
                    <div class="text-sm text-gray-500">Pending</div>
                    <div id="r_pending" class="text-2xl font-semibold mt-1">-</div>
                </div>
                <div class="p-4 border rounded-xl bg-white shadow-sm">
                    <div class="text-sm text-gray-500">Queued</div>
                    <div id="r_queued" class="text-2xl font-semibold mt-1">-</div>
                </div>
                <div class="p-4 border rounded-xl bg-white shadow-sm">
                    <div class="text-sm text-gray-500">Sent</div>
                    <div id="r_sent" class="text-2xl font-semibold mt-1">-</div>
                </div>
                <div class="p-4 border rounded-xl bg-white shadow-sm">
                    <div class="text-sm text-gray-500">Failed</div>
                    <div id="r_failed" class="text-2xl font-semibold mt-1">-</div>
                </div>
            </div>
        @endif

        {{-- Step 3: Fancy Excel-like grid --}}
        <div class="border rounded-xl bg-white shadow-sm">
            <div class="p-4 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <div class="font-semibold text-lg">Step 2 — Review & Edit</div>
                    <div class="text-sm text-gray-500">
                        Use column filters or global search. Edit <b>Generated Email</b> inline.
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button id="download-csv" class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">
                        Export CSV (Grid)
                    </button>
                    <button id="clear-filters" class="px-3 py-2 rounded-lg border bg-white hover:bg-gray-50">
                        Clear Filters
                    </button>
                </div>
            </div>

            <div class="p-4">
                <input id="global-search"
                       class="border rounded-lg p-2 w-full mb-3"
                       placeholder="Search anything (name, dept, email...)">

                <div id="students-grid"></div>

                <div class="text-xs text-gray-500 mt-3">
                    Tip: Double-click the <b>Generated Email</b> cell to edit. Duplicate emails are rejected and reverted.
                </div>
            </div>
        </div>

    </div>

    <script>
    document.addEventListener("DOMContentLoaded", async () => {
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function setText(id, val){
            const el = document.getElementById(id);
            if(el) el.textContent = val;
        }

        function setProgress(done, total, labelText){
            const pctEl = document.getElementById('gen-pct');
            const barEl = document.getElementById('gen-bar');
            const doneEl = document.getElementById('gen-done');
            const totalEl = document.getElementById('gen-total');
            const labelEl = document.getElementById('gen-label');

            if (doneEl) doneEl.textContent = String(done ?? 0);
            if (totalEl) totalEl.textContent = String(total ?? 0);
            if (labelEl && labelText) labelEl.textContent = labelText;

            // Only progress when the server reports real counts
            if (!total || total <= 0) return;

            const safeDone = Math.max(0, Math.min(total, Number(done || 0)));
            const pct = Math.floor((safeDone / total) * 100);

            if (pctEl) pctEl.textContent = pct + '%';
            if (barEl) barEl.style.width = pct + '%';
        }

        /* ============================
           CLIENT-SIDE STATS
        ============================ */
        function recomputeStatsFromTable(table){
            const data = table.getData();

            const total = data.length;
            const generated = data.filter(r => (r.generated_email ?? '').trim() !== '').length;

            const freq = {};
            for (const r of data) {
                const e = (r.generated_email ?? '').trim().toLowerCase();
                if(!e) continue;
                freq[e] = (freq[e] || 0) + 1;
            }
            let duplicates = 0;
            for (const k in freq) {
                if (freq[k] > 1) duplicates += freq[k];
            }

            const pending = data.filter(r => r.email_status === 'pending').length;
            const queued  = data.filter(r => r.email_status === 'queued').length;
            const sent    = data.filter(r => r.email_status === 'sent').length;
            const failed  = data.filter(r => r.email_status === 'failed').length;

            setText('r_total', total);
            setText('r_generated', generated);
            setText('r_duplicates', duplicates);
            setText('r_pending', pending);
            setText('r_queued', queued);
            setText('r_sent', sent);
            setText('r_failed', failed);
        }

        /* ============================
           REAL PROGRESS POLLING
           - Only advances when generation_done changes
           - Works for both: processing_names + generating
        ============================ */
        let lastDone = null;
        let stagnantTicks = 0;

        async function pollGenerationProgress(){
            try {
                const resp = await fetch("{{ route('imports.report', $import) }}", {
                    headers: { "Accept": "application/json" }
                });
                const report = await resp.json();

                // Label based on server stage/status
                const stage = report.generation_stage || report.status;
                const label = stage === 'processing_names'
                    ? 'Processing names…'
                    : (stage === 'generating' ? 'Generating emails…' : 'Working…');

                const total = Number(report.generation_total || 0);
                const done = Number(report.generation_done || 0);

                // ✅ Only update the bar from REAL server counts
                setProgress(done, total, label);

                // detect real movement
                if (lastDone === null) lastDone = done;

                if (done === lastDone) stagnantTicks++;
                else stagnantTicks = 0;

                lastDone = done;

                // If still working, keep polling.
                // We consider "working" while status is processing_names or generating
                const working = ['processing_names', 'generating'].includes(report.status);

                if (working) {
                    // If stuck (no progress) for a bit, slow down polling to be gentle
                    const delay = stagnantTicks >= 10 ? 2500 : 900;
                    setTimeout(pollGenerationProgress, delay);
                    return;
                }

                // Work finished (server changed status away from working)
                window.location.reload();

            } catch (e) {
                console.error('Progress polling failed', e);
                setTimeout(pollGenerationProgress, 2000);
            }
        }

        /* ============================
           LOAD ROWS
        ============================ */
        const rowsResp = await fetch("{{ route('imports.rows', $import) }}", {
            headers: { "Accept": "application/json" }
        });
        const rowsJson = await rowsResp.json();

        const table = new Tabulator("#students-grid", {
            data: rowsJson.data,
            layout: "fitDataStretch",
            height: "620px",
            pagination: "local",
            paginationSize: 50,
            movableColumns: true,
            reactiveData: true,

            tooltips: true,
            selectable: true,
            placeholder: "No rows found.",
            headerSortTristate: true,

            columns: [
                {title: "ID", field: "id", width: 70, frozen:true, headerFilter:true},

                {title: "First", field: "first_name", headerFilter:true},
                {title: "Middle", field: "middle_name", headerFilter:true},
                {title: "Last", field: "last_name", headerFilter:true},

                {title: "Dept", field: "DepartmentName", headerFilter:true},
                {title: "Personal Email", field: "personalEmail", headerFilter:true},

                {
                    title: "Generated Email",
                    field: "generated_email",
                    editor: "input",
                    headerFilter:true,
                    formatter: cell => {
                        const v = (cell.getValue() ?? '').toString();
                        return v || '<span class="text-gray-400 italic">not generated</span>';
                    }
                },

                {title: "Status", field: "email_status", headerFilter:true},
                {title: "Error", field: "email_error", headerFilter:true},
            ],

            cellEdited: async function(cell){
                if(cell.getField() !== "generated_email") return;

                recomputeStatsFromTable(table);

                const rowData = cell.getRow().getData();
                const newValue = cell.getValue();

                try {
                    const resp = await fetch(
                        `{{ url('/imports/'.$import->id) }}/rows/${rowData.id}/email`,
                        {
                            method: "PATCH",
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": csrf,
                                "Accept": "application/json"
                            },
                            body: JSON.stringify({ generated_email: newValue })
                        }
                    );

                    if(!resp.ok){
                        const err = await resp.json().catch(()=>null);
                        alert(err?.message ?? "Failed to update email");
                        cell.restoreOldValue();
                        recomputeStatsFromTable(table);
                    }

                } catch (e) {
                    alert("Network error while saving. Reverting.");
                    cell.restoreOldValue();
                    recomputeStatsFromTable(table);
                }
            }
        });

        /* ============================
           UI ACTIONS
        ============================ */
        document.getElementById('download-csv')?.addEventListener('click', () => {
            table.download("csv", "grid_export.csv");
        });

        document.getElementById('global-search')?.addEventListener('input', e => {
            const val = (e.target.value ?? '').trim().toLowerCase();
            if(!val){
                table.clearFilter(true);
                recomputeStatsFromTable(table);
                return;
            }
            table.setFilter(row =>
                Object.values(row).some(v =>
                    String(v ?? '').toLowerCase().includes(val)
                )
            );
            recomputeStatsFromTable(table);
        });

        document.getElementById('clear-filters')?.addEventListener('click', () => {
            document.getElementById('global-search').value = '';
            table.clearFilter(true);
            table.clearHeaderFilter();
            recomputeStatsFromTable(table);
        });

        /* ============================
           INITIALIZATION
        ============================ */
        @if($import->status === 'generated')
            recomputeStatsFromTable(table);

            setInterval(async () => {
                const r = await fetch("{{ route('imports.rows', $import) }}", {
                    headers: { "Accept": "application/json" }
                });
                const j = await r.json();
                table.replaceData(j.data);
                recomputeStatsFromTable(table);
            }, 5000);
        @endif

        // ✅ Poll progress for BOTH stages (processing_names + generating)
        @if(in_array($import->status, ['processing_names','generating']))
            // initialize bar with current DB values (real values, no fake motion)
            setProgress(
                {{ (int)($import->generation_done ?? 0) }},
                {{ (int)($import->generation_total ?? 0) }},
                "{{ $import->status === 'processing_names' ? 'Processing names…' : 'Generating emails…' }}"
            );
            pollGenerationProgress();
        @endif
    });
    </script>

</x-app-layout>
