<?php

namespace AyaAshraf\LaravelRag\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;
use AyaAshraf\LaravelRag\Enums\DocumentStatus;
use AyaAshraf\LaravelRag\Models\Document;
use AyaAshraf\LaravelRag\Services\DocumentEmbeddingIndexer;
use AyaAshraf\LaravelRag\Services\DocumentTextExtractor;

class ProcessDocumentForRag implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Document $document) {}

    public function handle(
        DocumentTextExtractor $extractor,
        DocumentEmbeddingIndexer $indexer,
    ): void {
        $this->document->update([
            'status' => DocumentStatus::PROCESSING,
            'error' => null,
        ]);

        try {
            $text = $extractor->extract(
                Storage::disk($this->document->disk)->path($this->document->path),
                enforceSyncPdfLimit: false,
            );

            $this->document->update([
                'extracted_text' => $text,
            ]);

            if (blank($text)) {
                $this->document->chunks()->delete();
                $this->document->update([
                    'status' => DocumentStatus::EMPTY,
                    'error' => null,
                    'processed_at' => now(),
                ]);

                return;
            }

            if ($indexer->index($this->document, $text) === 0) {
                $this->document->chunks()->delete();
                $this->document->update([
                    'status' => DocumentStatus::EMPTY,
                    'error' => null,
                    'processed_at' => now(),
                ]);

                return;
            }

            $this->document->update([
                'status' => DocumentStatus::PROCESSED,
                'error' => null,
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $this->document->update([
                'status' => DocumentStatus::FAILED,
                'error' => 'The document could not be processed by the background worker: '.$exception->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }
}

