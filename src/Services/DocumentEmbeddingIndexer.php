<?php

namespace AyaAshraf\LaravelRag\Services;

use RuntimeException;
use AyaAshraf\LaravelRag\Models\Document;

class DocumentEmbeddingIndexer
{
    public function __construct(
        private readonly DocumentChunker $chunker,
        private readonly EmbeddingGenerator $embeddings,
    ) {}

    public function index(Document $document, string $text): int
    {
        //break the raw text into smaller pieces
        $chunks = $this->chunker->chunk($text);
        //deletes any existing chunks for the $document to prevent stale data, then returns 0 to indicate that no new chunks were created

        if ($chunks === []) {
            $document->chunks()->delete();

            return 0;
        }

        $batchSize = max(1, (int) config('services.embeddings.batch_size', 64));
        $position = 0;

        $document->chunks()->delete();
        //Sending one sentence at a time to an AI provider
        foreach (array_chunk($chunks, $batchSize) as $batch) {
            $vectors = $this->embeddings->embed($batch);
            //It ensures that the AI provider returned exactly one numerical vector for every piece of text sent.
            if (count($vectors) !== count($batch)) {
                throw new RuntimeException('The embedding provider returned a different number of embeddings than requested.');
            }

            foreach ($batch as $index => $chunk) {
                $vector = $vectors[$index];

                $document->chunks()->create([
                    //Keeps track of the order so you can reconstruct the document later.
                    'position' => $position++,
                    'content' => $chunk,
                    //A SHA-256 hash of the text, useful for checking if the content has changed without comparing massive strings
                    'content_hash' => hash('sha256', $chunk),
                    'embedding' => $vector,
                    'embedding_model' => $this->embeddings->model(),
                    'embedding_dimensions' => count($vector),
                    'embedded_at' => now(),
                    'metadata' => [
                        'chunk_index' => $index,
                        'batch_index' => (int) floor($index / $batchSize),
                        'batch_size' => $batchSize,
                    ],
                ]);
            }
        }

        return $position;
    }
}
