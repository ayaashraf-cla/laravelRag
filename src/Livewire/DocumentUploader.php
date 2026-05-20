<?php

namespace AyaAshraf\LaravelRag\Livewire;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;
use AyaAshraf\LaravelRag\Enums\DocumentStatus;
use AyaAshraf\LaravelRag\Jobs\ProcessDocumentForRag;
use AyaAshraf\LaravelRag\Models\Document;

#[Layout('rag::components.layouts.app')]
class DocumentUploader extends Component
{
    use WithFileUploads;

    public array $documents = [];

    public array $documentIds = [];

    // Pass these from the parent Blade view to scope documents to a specific owner model.
    // Example: <livewire:rag-uploader :documentable-type="App\Models\User::class" :documentable-id="$user->id" />
    public ?string $documentableType = null;

    public ?int $documentableId = null;

    public ?int $uploadedBy = null;
    public ?int $specifiedName = null;
    // When false, the extracted text is NOT displayed in the UI after upload.
    // The document record is still created and processed for RAG; only the display is suppressed.
    public bool $isVisible = true;
    public bool $isRequied = false;
    public function updatedDocuments(): void
    {
        if ($this->documents === []) {
            return;
        }

        $this->reset('documentIds');
    }

    public function save()
    {
        $this->validate([
            'documents'   => 'required|array|min:1|max:10',
            'documents.*' => 'file|mimes:pdf,docx,txt,xls,xlsx,csv|max:102400',
        ]);

        $this->reset('documentIds');

        foreach ($this->documents as $uploadedDocument) {
            $path = $uploadedDocument->store('rag-sources', 'local');
            $disk = Storage::disk('local');

            $document = Document::create([
                'original_name'     => $uploadedDocument->getClientOriginalName(),
                'disk'              => 'local',
                'path'              => $path,
                'mime_type'         => $this->storedMimeType($disk, $path),
                'size'              => $this->storedSize($disk, $path),
                'documentable_type' => $this->documentableType,
                'documentable_id'   => $this->documentableId,
                'uploaded_by'       => $this->uploadedBy,
                'specified_name'       => $this->specifiedName,
                 'is_required'        => $this->isRequied,
                'is_visible'        => $this->isVisible,
                'status'            => DocumentStatus::QUEUED,
            ]);

            $this->documentIds[] = $document->id;

            ProcessDocumentForRag::dispatch($document);
        }

        $this->reset('documents');

        session()->flash('message', count($this->documentIds).' document(s) queued for processing.');
    }

    private function storedMimeType(Filesystem $disk, string $path): ?string
    {
        try {
            return $disk->mimeType($path) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function storedSize(Filesystem $disk, string $path): int
    {
        try {
            return $disk->size($path) ?: 0;
        } catch (Throwable) {
            return 0;
        }
    }

    public function processedDocuments(): array
    {
        if ($this->documentIds === []) {
            return [];
        }

        return Document::query()
            ->whereIn('id', $this->documentIds)
            ->with('chunks')
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): array => [
                'id'          => $document->id,
                'file_name'   => $document->original_name,
                'is_visible'  => $document->is_visible,
                'text'        => $document->is_visible ? ($document->extracted_text ?? '') : null,
                'chunk_count' => $document->chunks->count(),
                'status'      => $document->status->value,
                'error'       => $document->error,
            ])
            ->all();
    }

    public function render()
    {
        return view('rag::upload', [
            'processedDocuments' => $this->processedDocuments(),
        ]);
    }
}
