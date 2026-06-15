<?php

use CLA\LaravelRag\Enums\DocumentStatus;
use CLA\LaravelRag\Models\Document;
use CLA\LaravelRag\Models\DocumentChunk;

it('filters chunks precisely using dynamic similarity thresholds and vector distances', function () {
    // 1. Establish our baseline search vector (1536 dimensions)
    $searchVector = array_fill(0, 1536, 0.1);

    // 2. Create a parent document matching your schema logic
    $document = Document::create([
        'title'         => 'Test Humanitarian Guide Document',
        'original_name' => 'humanitarian_code_of_conduct.pdf',
        'disk'          => 'local',
        'path'          => 'documents/humanitarian_code_of_conduct.pdf',
        'mime_type'     => 'application/pdf',
        'size'          => 1024,
        'status'        => DocumentStatus::PROCESSED,
    ]);

    // 3. Seed Database using native PHP arrays linked to our document

    // Chunk A: Highly relevant (Exact match to search anchor)
    $highlyRelevantChunk = DocumentChunk::create([
        'document_id'          => $document->id,
        'content'              => 'Prohibited actions for humanitarian workers include exploitation...',
        'embedding'            => $searchVector,
        'embedding_dimensions' => 1536,
    ]);

    // Chunk B: Borderline relevant (Slightly modified vector values)
    $borderlineVector = array_map(fn($val) => $val + 0.05, $searchVector);
    $borderlineChunk = DocumentChunk::create([
        'document_id'          => $document->id,
        'content'              => 'Humanitarian agencies should coordinate with local authorities.',
        'embedding'            => $borderlineVector,
        'embedding_dimensions' => 1536,
    ]);

    // Chunk C: Completely irrelevant (Altered signature directional space)
    $irrelevantVector = array_map(fn($index) => $index % 2 === 0 ? 0.9 : -0.9, range(0, 1535));
    $irrelevantChunk = DocumentChunk::create([
        'document_id'          => $document->id,
        'content'              => 'To compile an optimized production build of Vue, use npm run build.',
        'embedding'            => $irrelevantVector,
        'embedding_dimensions' => 1536,
    ]);

    // 4. Set up dynamic threshold variables
    $dynamicMinSimilarity = 0.78;

    // 5. Act: Execute search using your target query macros/scopes
    $results = DocumentChunk::query()
        ->whereVectorSimilarTo('embedding', $searchVector, $dynamicMinSimilarity)
        ->orderByVectorDistance('embedding', $searchVector)
        ->take(5)
        ->get();

    // 6. Assertions
    expect($results)->toHaveCount(2)
        ->and($results->pluck('id'))->toContain($highlyRelevantChunk->id)
        ->and($results->pluck('id'))->toContain($borderlineChunk->id);

    expect($results->pluck('id'))->not->toContain($irrelevantChunk->id);
    expect($results->first()->id)->toBe($highlyRelevantChunk->id);
})->skip('Requires PostgreSQL with pgvector extension — cannot run on SQLite.');