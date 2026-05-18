@assets
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/filepond@4.27.2/dist/filepond.min.css">
<script src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.8/dist/filepond-plugin-file-validate-type.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/filepond@4.27.2/dist/filepond.min.js"></script>
@endassets

<div class="space-y-4">
    <form data-document-upload-form class="space-y-4">
        <div class="space-y-2">
            <label class="block text-sm font-medium text-zinc-900 dark:text-zinc-100">Upload Documents for RAG</label>
            <div wire:ignore>
                <input data-filepond-input
                    type="file"
                    accept=".pdf,.docx,.doc,.txt,.xls,.xlsx,.csv,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                    multiple>
            </div>
            @error('documents')
            <div class="text-sm text-red-500">{{ $message }}</div>
            @enderror
            @error('documents.*')
            <div class="text-sm text-red-500">{{ $message }}</div>
            @enderror
        </div>

        <div wire:loading.flex wire:target="save" class="items-center gap-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
            <span>Saving document(s) and dispatching background jobs...</span>
        </div>

        <button type="submit"
            class="rounded bg-blue-500 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-zinc-900"
            data-document-submit
            disabled
            wire:loading.attr="disabled"
            wire:target="save">
            <span data-document-submit-label>Upload for RAG</span>
        </button>
    </form>

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

    @if (session()->has('message'))
    <div class="mt-4 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300">
        {{ session('message') }}
    </div>
    @endif

    @if ($processedDocuments)
    <div class="mt-6 space-y-4">
        <h2 class="text-lg font-semibold">Documents</h2>
        @foreach ($processedDocuments as $index => $processedDocument)
        <div wire:poll.2s wire:key="processed-document-{{ $processedDocument['id'] }}" class="rounded border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ $processedDocument['file_name'] }}</h3>
                <span @class([ 'rounded px-2 py-1 text-xs font-medium' , 
                    'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'=> $processedDocument['status'] === 'queued',
                    'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' => $processedDocument['status'] === 'processing',
                    'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' => $processedDocument['status'] === 'processed',
                    'bg-zinc-100 text-zinc-700 dark:bg-zinc-900 dark:text-zinc-300' => $processedDocument['status'] === 'empty',
                    'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $processedDocument['status'] === 'failed',
                    ])>
                    {{ $processedDocument['status'] }} 
                </span>
            </div>
            
            @if ($processedDocument['status'] === 'queued')
                <div class="mt-3 rounded border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300">Waiting for the queue worker...</div>
            @elseif ($processedDocument['status'] === 'processing')
                <div class="mt-3 flex items-center gap-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                    <span>Extracting text and creating embeddings...</span>
                </div>
            @elseif ($processedDocument['status'] === 'failed')
                <div class="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">{{ $processedDocument['error'] }}</div>
            @else
                <div class="mt-3 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950/40 dark:text-green-300">Stored {{ $processedDocument['chunk_count'] }} embedded chunk(s) for RAG.</div>
                <textarea class="mt-3 w-full rounded border border-zinc-300 bg-white p-3 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 text-black" rows="10" readonly>{{ $processedDocument['text'] }}</textarea>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>