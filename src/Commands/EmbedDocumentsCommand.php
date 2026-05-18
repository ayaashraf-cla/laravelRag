<?php

namespace YourVendor\LaravelRag\Commands;

use Illuminate\Console\Command;
use YourVendor\LaravelRag\Enums\DocumentStatus; // Note: See the architectural tip below regarding these imports
use YourVendor\LaravelRag\Models\Document;
use YourVendor\LaravelRag\Services\DocumentEmbeddingIndexer;

class EmbedDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:embed-documents 
                            {document? : Optional document ID to re-embed} 
                            {--force : Rebuild chunks even when they already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or rebuild embeddings for processed RAG documents';

    /**
     * Execute the console command.
     *
     * @param DocumentEmbeddingIndexer $indexer
     * @return int
     */
    public function handle(DocumentEmbeddingIndexer $indexer): int
    {
        $query = Document::query()
            ->where('status', DocumentStatus::PROCESSED)
            ->whereNotNull('extracted_text')
            ->orderBy('id');

        if ($documentId = $this->argument('document')) {
            $query->whereKey($documentId);
        }

        $processed = 0;

        $query->each(function (Document $document) use ($indexer, &$processed): void {
            $chunkCount = $document->chunks()->count();

            if (! $this->option('force') && $chunkCount > 0) {
                $this->line("Skipping document {$document->id}; it already has {$chunkCount} chunk(s).");
                return;
            }

            $chunkCount = $indexer->index($document, $document->extracted_text ?? '');
            $processed++;

            $this->info("Embedded document {$document->id} with {$chunkCount} chunk(s).");
        });

        $this->info("Done. Embedded {$processed} document(s).");

        return Command::SUCCESS;
    }
}