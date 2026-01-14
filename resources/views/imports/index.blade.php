{{-- resources/views/imports/index.blade.php --}}
<x-app-layout>
    <div class="max-w-4xl mx-auto p-6 space-y-6">

        <h1 class="text-2xl font-bold">Upload Student CSV</h1>

        @if (session('status'))
            <div class="p-3 rounded bg-green-100 text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="p-3 rounded bg-red-100 text-red-800">
                <ul class="list-disc ml-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="upload-form" class="p-4 border rounded bg-white space-y-3">
            @csrf

            <div>
                <label class="block font-medium">CSV File</label>
                <input type="file" id="csv" name="csv" required class="block w-full mt-1 border rounded p-2">
            </div>

            <button id="upload-btn" type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
                Upload CSV
            </button>

            <div id="upload-progress-wrap" class="hidden">
                <div class="text-sm text-gray-600 mb-1">Uploading… <span id="upload-pct">0%</span></div>
                <div class="w-full bg-gray-200 rounded h-3 overflow-hidden">
                    <div id="upload-bar" class="h-3 bg-blue-600" style="width:0%"></div>
                </div>
            </div>
        </form>

        <script>
        document.addEventListener("DOMContentLoaded", () => {
            const form = document.getElementById("upload-form");
            const input = document.getElementById("csv");
            const wrap = document.getElementById("upload-progress-wrap");
            const bar = document.getElementById("upload-bar");
            const pct = document.getElementById("upload-pct");
            const btn = document.getElementById("upload-btn");

            form.addEventListener("submit", (e) => {
                e.preventDefault();

                if (!input.files || !input.files[0]) return;

                const fd = new FormData();
                fd.append("csv", input.files[0]);
                fd.append("_token", "{{ csrf_token() }}");

                const xhr = new XMLHttpRequest();
                xhr.open("POST", "{{ route('imports.store') }}", true);
                xhr.setRequestHeader("Accept", "application/json");

                wrap.classList.remove("hidden");
                btn.disabled = true;
                btn.textContent = "Uploading...";

                xhr.upload.onprogress = function(ev){
                    if(!ev.lengthComputable) return;
                    const percent = Math.round((ev.loaded / ev.total) * 100);
                    pct.textContent = percent + "%";
                    bar.style.width = percent + "%";
                };

                xhr.onload = function(){
                    btn.disabled = false;
                    btn.textContent = "Upload CSV";

                    if(xhr.status >= 200 && xhr.status < 300){
                        const res = JSON.parse(xhr.responseText);
                        window.location.href = res.redirect;
                        return;
                    }
                    alert("Upload failed. Check file type and server logs.");
                };

                xhr.onerror = function(){
                    btn.disabled = false;
                    btn.textContent = "Upload CSV";
                    alert("Upload network error.");
                };

                xhr.send(fd);
            });
        });
        </script>


        <div class="bg-white border rounded p-4">
            <h2 class="font-semibold mb-2">Your Imports</h2>
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">File</th>
                        <th class="p-2 text-left">Status</th>
                        <th class="p-2 text-left">Created</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($imports as $import)
                        <tr class="border-t">
                            <td class="p-2">{{ $import->original_filename }}</td>
                            <td class="p-2">{{ $import->status }}</td>
                            <td class="p-2">{{ $import->created_at }}</td>
                            <td class="p-2 text-right">
                                <a class="text-blue-600 hover:underline"
                                   href="{{ route('imports.show', $import) }}">
                                    Open
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td class="p-2 text-gray-500" colspan="4">No imports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</x-app-layout>
