<?php

use YourVendor\LaravelRag\Enums\DocumentStatus;
use YourVendor\LaravelRag\Jobs\ProcessDocumentForRag;
use YourVendor\LaravelRag\Livewire\DocumentUploader;
use YourVendor\LaravelRag\Models\Document;
use YourVendor\LaravelRag\Models\DocumentChunk;
use YourVendor\LaravelRag\Services\DocumentEmbeddingIndexer;
use YourVendor\LaravelRag\Services\DocumentTextExtractor;
use YourVendor\LaravelRag\Services\EmbeddingGenerator;
// use App\Services\OpenAiEmbeddingGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    DocumentChunk::query()->delete();
});

it('stores uploaded documents and queues them for processing', function () {
    Queue::fake();
    Storage::fake('local');

    $firstFile = UploadedFile::fake()->createWithContent('notes.txt', "First   line\n\n\nSecond line");
    $secondFile = UploadedFile::fake()->createWithContent('summary.txt', 'Another document');

    Livewire::test(DocumentUploader::class)
        ->set('documents', [$firstFile, $secondFile])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('documentIds.0', Document::query()->where('original_name', 'notes.txt')->value('id'))
        ->assertSet('documentIds.1', Document::query()->where('original_name', 'summary.txt')->value('id'));

    expect(Document::query()->where('status', DocumentStatus::QUEUED)->count())->toBe(2);

    Queue::assertPushed(ProcessDocumentForRag::class, 2);
});

it('processes a queued document', function () {
    Storage::fake('local');
    Storage::disk('local')->put('rag-sources/notes.txt', "First   line\n\n\nSecond line");

    $document = Document::create([
        'original_name' => 'notes.txt',
        'disk' => 'local',
        'path' => 'rag-sources/notes.txt',
        'mime_type' => 'text/plain',
        'size' => 25,
        'status' => DocumentStatus::QUEUED,
    ]);

    (new ProcessDocumentForRag($document))->handle(
        app(DocumentTextExtractor::class),
        app(DocumentEmbeddingIndexer::class),
    );

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::PROCESSED)
        ->and($document->extracted_text)->toBe("First line\n\nSecond line")
        ->and($document->processed_at)->not->toBeNull()
        ->and($document->chunks)->toHaveCount(1)
        ->and($document->chunks->first()->content)->toBe("First line\n\nSecond line")
        ->and($document->chunks->first()->embedding)->toHaveCount(3)
        ->and($document->chunks->first()->embedding_model)->toBe('fake')
        ->and($document->chunks->first()->embedding_dimensions)->toBe(3);
});

it('backfills embeddings for processed documents without chunks', function () {
    $vector = array_fill(0, 1536, 0.1);

    // 1. Mock the EmbeddingGenerator interface
    $mockGenerator = Mockery::mock(EmbeddingGenerator::class);

    // 2. Specify expectation for the embed() method
    $mockGenerator->shouldReceive('embed')
        ->once()
        ->with(Mockery::type('array'))
        ->andReturnUsing(function (array $inputs) use ($vector) {
            return array_fill(0, count($inputs), $vector);
        });

    // 3. FIX: Specify expectation for the model() method so Mockery knows how to handle it
    $mockGenerator->shouldReceive('model')
        ->zeroOrMoreTimes() // Allows it to be called safely as many times as needed
        ->andReturn('gemini-embedding');

    // 4. Bind the mock to Laravel's Service Container
    $this->app->instance(EmbeddingGenerator::class, $mockGenerator);

    // 5. Configure runtime settings
    config([
        'services.embeddings.provider' => 'gemini',
        'services.gemini.key' => 'test-key',
        'services.gemini.embedding_dimensions' => 1536,
    ]);

    // 6. Create your test document record
    $document = Document::create([
        'original_name' => 'notes.txt',
        'disk' => 'local',
        'path' => 'rag-sources/notes.txt',
        'mime_type' => 'text/plain',
        'size' => 25,
        'status' => DocumentStatus::PROCESSED,
        'extracted_text' => "First line\n\nSecond line",
        'processed_at' => now(),
    ]);

    // 7. Run your Artisan command block
    $expectedChunksCount = 1; // Keep as 2 if your chunker splits on double newlines

    $this->artisan('rag:embed-documents', ['document' => $document->id])
        ->expectsOutput("Embedded document {$document->id} with {$expectedChunksCount} chunk(s).")
        ->expectsOutput('Done. Embedded 1 document(s).')
        ->assertSuccessful();

    // 8. Verify database state
    expect($document->refresh()->chunks)->toHaveCount($expectedChunksCount)
        ->and($document->chunks->first()->embedding)->toHaveCount(1536);
});
