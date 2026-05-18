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
            'documents' => 'required|array|min:1|max:10',
            'documents.*' => 'file|mimes:pdf,docx,txt,xls,xlsx,csv|max:102400',
        ]);

        $this->reset('documentIds');

        foreach ($this->documents as $uploadedDocument) {
            $path = $uploadedDocument->store('rag-sources', 'local');
            $disk = Storage::disk('local');

            $document = Document::create([
                'original_name' => $uploadedDocument->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'mime_type' => $this->storedMimeType($disk, $path),
                'size' => $this->storedSize($disk, $path),
                'status' => DocumentStatus::QUEUED,
            ]);

            $this->documentIds[] = $document->id;

            ProcessDocumentForRag::dispatch($document);
        }

        $this->reset('documents');

        // Next RAG step: chunk each result, create embeddings, and store the vectors.
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
                'id' => $document->id,
                'file_name' => $document->original_name,
                'text' => $document->extracted_text ?? '',
                'chunk_count' => $document->chunks->count(),
                'status' => $document->status->value,
                'error' => $document->error,
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
