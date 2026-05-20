@assets
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/filepond@4.27.2/dist/filepond.min.css">
<script src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.8/dist/filepond-plugin-file-validate-type.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/filepond@4.27.2/dist/filepond.min.js"></script>
<style>
    .filepond--root { font-family: inherit; }
    .filepond--panel-root {
        background-color: #f8fafc;
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        transition: border-color 0.2s;
    }
    .filepond--root:hover .filepond--panel-root { border-color: #818cf8; }
    .filepond--drop-label { color: #64748b; }
    .filepond--label-action { color: #4f46e5; font-weight: 600; text-decoration: underline; text-decoration-color: #a5b4fc; }
    .filepond--drip-blob { background-color: #e0e7ff; }
    .filepond--item-panel { background-color: #eef2ff; border-radius: 8px; }
    .filepond--file-action-button { background: rgba(79, 70, 229, 0.9); }
    .filepond--file-action-button:hover { background: rgba(67, 56, 202, 1); }
    .filepond--file-info-main { color: #1e293b; font-weight: 500; }
    .filepond--file-info-sub { color: #64748b; }
    [data-filepond-item-state*="error"] .filepond--item-panel,
    [data-filepond-item-state*="invalid"] .filepond--item-panel { background-color: #fee2e2; }
    [data-filepond-item-state="processing-complete"] .filepond--item-panel { background-color: #dcfce7; }
</style>
@endassets

<div class="space-y-6">

    {{-- Upload Card --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- Card Header --}}
        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50 via-white to-violet-50 px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 shadow-sm">
                    <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Upload Documents for RAG</h2>
                    <p class="mt-0.5 text-xs text-slate-500">PDF, DOCX, TXT, XLS, XLSX, CSV &mdash; up to 100 MB each, max 10 files</p>
                </div>
            </div>
        </div>

        {{-- Form Body --}}
        <form data-document-upload-form class="space-y-4 p-5">
            <div wire:ignore>
                <input data-filepond-input
                    type="file"
                    accept=".pdf,.docx,.doc,.txt,.xls,.xlsx,.csv,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                    multiple>
            </div>

            @error('documents')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('documents.*')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div wire:loading.flex wire:target="save" class="items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                <span>Saving document(s) and dispatching background jobs...</span>
            </div>

            <button
                type="submit"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                data-document-submit
                disabled
                wire:loading.attr="disabled"
                wire:target="save">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <span data-document-submit-label>Upload for RAG</span>
            </button>
        </form>
    </div>

    {{-- Success Flash --}}
    @if (session()->has('message'))
        <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <svg class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Processed Documents List --}}
    @if ($processedDocuments)
        <div class="space-y-3">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Processed Documents</h2>

            @foreach ($processedDocuments as $index => $processedDocument)
                <div
                    wire:poll.2s
                    wire:key="processed-document-{{ $processedDocument['id'] }}"
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs"
                >
                    {{-- Document Row Header --}}
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100">
                                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <h3 class="truncate text-sm font-medium text-slate-900">{{ $processedDocument['file_name'] }}</h3>
                        </div>
                        <span @class([
                            'shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1',
                            'bg-sky-50 text-sky-700 ring-sky-200'     => $processedDocument['status'] === 'queued',
                            'bg-amber-50 text-amber-700 ring-amber-200' => $processedDocument['status'] === 'processing',
                            'bg-emerald-50 text-emerald-700 ring-emerald-200' => $processedDocument['status'] === 'processed',
                            'bg-slate-100 text-slate-600 ring-slate-200' => $processedDocument['status'] === 'empty',
                            'bg-red-50 text-red-700 ring-red-200'     => $processedDocument['status'] === 'failed',
                        ])>
                            {{ $processedDocument['status'] }}
                        </span>
                    </div>

                    {{-- Status Detail --}}
                    @if ($processedDocument['status'] === 'queued')
                        <div class="flex items-center gap-2 border-t border-slate-100 bg-sky-50 px-4 py-3 text-sm text-sky-700">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Waiting for the queue worker...
                        </div>

                    @elseif ($processedDocument['status'] === 'processing')
                        <div class="flex items-center gap-2 border-t border-slate-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            <span class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                            Extracting text and creating embeddings...
                        </div>

                    @elseif ($processedDocument['status'] === 'failed')
                        <div class="flex items-start gap-2 border-t border-slate-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            {{ $processedDocument['error'] }}
                        </div>

                    @else
                        <div class="flex items-center gap-2 border-t border-slate-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Stored {{ $processedDocument['chunk_count'] }} embedded chunk(s) for RAG.
                        </div>
                        @if ($processedDocument['is_visible'])
                            <div class="border-t border-slate-100 p-4">
                                <textarea
                                    class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-3 font-mono text-xs text-slate-700 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                    rows="10"
                                    readonly>{{ $processedDocument['text'] }}</textarea>
                            </div>
                        @else
                            <div class="flex items-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                    <line x1="1" y1="1" x2="23" y2="23"/>
                                </svg>
                                Extracted content is hidden for this document.
                            </div>
                        @endif
                    @endif

                </div>
            @endforeach
        </div>
    @endif

    @script
    <script>
        (function() {
            FilePond.registerPlugin(FilePondPluginFileValidateType);

            const input = $wire.$el.querySelector('[data-filepond-input]');
            const form = $wire.$el.querySelector('[data-document-upload-form]');
            const submitBtn = $wire.$el.querySelector('[data-document-submit]');
            const submitLabel = $wire.$el.querySelector('[data-document-submit-label]');

            if (!input || !form || !submitBtn || !submitLabel || !window.FilePond || input.dataset.filepondReady) {
                return;
            }

            input.dataset.filepondReady = 'true';

            const pond = FilePond.create(input, {
                allowMultiple: true,
                maxFiles: 10,
                maxFileSize: '100MB',
                instantUpload: false,
                labelIdle: 'Drag & drop your documents or <span class="filepond--label-action">browse</span>',
                credits: false,
                acceptedFileTypes: [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/msword',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/csv'
                ],
                // FALLBACK: If the browser fails to detect the type (common for CSV/DOCX),
                // we manually map the extension to the correct MIME type.
                fileValidateTypeDetectType: (source, type) => new Promise((resolve, reject) => {
                    const extension = source.name.split('.').pop().toLowerCase();
                    const map = {
                        'pdf': 'application/pdf',
                        'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'doc': 'application/msword',
                        'txt': 'text/plain',
                        'xls': 'application/vnd.ms-excel',
                        'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'csv': 'text/csv'
                    };

                    if (map[extension]) {
                        resolve(map[extension]);
                    } else {
                        resolve(type);
                    }
                }),
                labelFileTypeNotAllowed: 'File type not allowed',
                fileValidateTypeLabelExpectedTypes: 'Expects: PDF, DOCX, TXT, XLS, XLSX, CSV',
                styleLoadIndicatorPosition: 'center bottom',
                styleProgressIndicatorPosition: 'right bottom',
                styleButtonRemoveItemPosition: 'left bottom',
                styleButtonProcessItemPosition: 'right bottom',
            });

            let isUploading = false;
            let isSubmitting = false;
            let isUploadReady = false;
            let isClearingAfterSave = false;
            let shouldSyncAfterUpload = false;
            let uploadVersion = 0;

            function updateButtonState() {
                submitBtn.disabled = isUploading || isSubmitting || !isUploadReady;
                submitLabel.textContent = isUploading ?
                    'Uploading...' :
                    (isSubmitting ? 'Queueing...' : 'Upload for RAG');
            }

            function currentFiles() {
                return pond.getFiles().map((fileItem) => fileItem.file).filter(Boolean);
            }

            function syncFiles() {
                const files = currentFiles();
                uploadVersion++;
                isUploadReady = false;

                if (files.length === 0) {
                    $wire.$set('documents', []);
                    isUploading = false;
                    shouldSyncAfterUpload = false;
                    updateButtonState();
                    return;
                }

                if (isUploading) {
                    shouldSyncAfterUpload = true;
                    updateButtonState();
                    return;
                }

                isUploading = true;
                shouldSyncAfterUpload = false;
                updateButtonState();

                const startedVersion = uploadVersion;

                $wire.$uploadMultiple(
                    'documents',
                    files,
                    function() {
                        isUploading = false;
                        isUploadReady = startedVersion === uploadVersion && !shouldSyncAfterUpload;
                        if (shouldSyncAfterUpload) {
                            syncFiles();
                            return;
                        }
                        updateButtonState();
                    },
                    function() {
                        isUploading = false;
                        isUploadReady = false;
                        shouldSyncAfterUpload = false;
                        updateButtonState();
                    },
                    function() {
                        updateButtonState();
                    },
                    function() {
                        isUploading = false;
                        isUploadReady = false;
                        shouldSyncAfterUpload = false;
                        updateButtonState();
                    },
                    false,
                );
            }

            updateButtonState();

            pond.on('addfile', (error, file) => {
                if (error) return; // Ignore files that failed validation
                syncFiles();
            });
            pond.on('removefile', function() {
                if (isClearingAfterSave) return;
                syncFiles();
            });
            pond.on('reorderfiles', syncFiles);

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                if (isUploading || isSubmitting || !isUploadReady) return;

                isSubmitting = true;
                updateButtonState();

                $wire.save()
                    .then(function() {
                        isClearingAfterSave = true;
                        pond.removeFiles();
                        setTimeout(() => { isClearingAfterSave = false; });
                    })
                    .finally(function() {
                        isSubmitting = false;
                        isUploadReady = false;
                        updateButtonState();
                    });
            });
        })();
    </script>
    @endscript

</div>
