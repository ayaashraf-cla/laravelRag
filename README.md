# Laravel RAG Package

A production-ready **Retrieval-Augmented Generation (RAG)** package for Laravel using pgvector and Livewire.

## Features

- 🔍 **Vector Semantic Search** using pgvector with PostgreSQL
- 📄 **Multi-format Document Processing** (PDF, DOCX, TXT, XLS, XLSX, CSV)
- 🧠 **AI-powered Document Chunking** with configurable strategies
- 💬 **Real-time Streaming Chat** using Laravel AI SDK
- ⚡ **Full-text to Vector Pipeline** with embeddings caching
- 🎯 **Bilingual Support** with Arabic language optimization
- 📊 **Analytics Dashboard** with token counting and timing metrics
- 🔧 **Swappable Embedding Providers** (Gemini, OpenAI, Cohere, Azure, etc.)
- 🎨 **Beautiful Livewire UI** with Tailwind CSS & Alpine.js

## Installation

```bash
composer require AyaAshraf/laravel-rag
```

## Publishing Assets

Publish configuration, migrations, and views:

```bash
php artisan vendor:publish --tag=rag-config
php artisan vendor:publish --tag=rag-migrations
```

> Note: The package suppresses the redundant `pgvector` vendor migration path so your own `document_chunks` migration remains the only migration path for pgvector extension creation. This avoids running the pgvector migration on the wrong default database connection.

Run migrations:

```bash
php artisan migrate
```

## Configuration

Create a PostgreSQL connection for vectors in `config/database.php`:

```php
   'pgsql_vector' => [
            'driver' => 'pgsql',
            'host' => env('VECTOR_DB_HOST', 'localhost'),
            'port' => env('VECTOR_DB_PORT', 5432),
            'database' => env('VECTOR_DB_DATABASE', 'rag_vectors'),
            'username' => env('VECTOR_DB_USERNAME', 'postgres'),
            'password' => env('VECTOR_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
        ],
```

Configure environment variables in `.env`:

```env
EMBEDDING_PROVIDER=gemini
GEMINI_API_KEY=your-key-here
GEMINI_EMBEDDING_MODEL=gemini-embedding-001
GEMINI_EMBEDDING_DIMENSIONS=1536

RAG_VECTOR_CONNECTION=pgsql_vector
RAG_DOCUMENTS_CONNECTION=mysql
RAG_MIN_SIMILARITY=0.45
RAG_ARABIC_MIN_SIMILARITY=0.30
RAG_MAX_CHUNK_CHARS=3000
RAG_CHUNK_OVERLAP=200
RAG_MAX_SEARCH_RESULTS=10
RAG_STORAGE_DISK=rag_documents
RAG_CHUNKER_STRATEGY=character
RAG_CHAT_PROVIDER=gemini
RAG_CHAT_MODEL=gemini-2.0-flash
RAG_CHAT_TIMEOUT=120
```

## Usage

### In Blade Templates

```blade
<!-- Chat Interface -->
<livewire:rag-chat />

<!-- Document Upload -->
<livewire:rag-uploader />
```

### Programmatic Usage

```php
use AyaAshraf\LaravelRag\Agents\DocumentSearchAgent;
use AyaAshraf\LaravelRag\Models\Document;

// Query documents with streaming
$stream = DocumentSearchAgent::make()
    ->stream('What is this about?', provider: 'gemini');

foreach ($stream as $chunk) {
    echo $chunk->text;
}

// Access documents directly
$docs = Document::where('status', 'processed')->get();
$chunks = $docs->first()->chunks;
```

### With Callback Hooks

```php
use AyaAshraf\LaravelRag\Agents\DocumentSearchAgent;

$stream = DocumentSearchAgent::make()
    ->onChunksFound(function ($chunks, $metadata) {
        dump($metadata); // Vector search metrics
        dump($chunks);   // Retrieved document chunks
    })
    ->stream('Your question here', provider: 'gemini');

foreach ($stream as $event) {
    // Handle streaming events
}
```

## Database Schema

### Documents Table

Stores document metadata on MySQL/Primary DB:

```
- id: Primary key
- original_name: File name
- disk: Storage disk (local, s3, etc)
- path: File path
- mime_type: MIME type
- size: File size in bytes
- status: queued|processing|processed|empty|failed
- extracted_text: Full extracted text
- error: Error message if failed
- processed_at: Completion timestamp
```

### Document Chunks Table

Stores vector embeddings on PostgreSQL with pgvector:

```
- id: Primary key
- document_id: Foreign key to documents
- position: Chunk order (0-based)
- content: Chunk text
- content_hash: SHA-256 of content
- embedding: pgvector type (e.g., 768-dimensional)
- tokens_count: Token count (optional)
```

## Architecture

The package uses a **multi-database approach**:

- **MySQL/Primary**: Document metadata (fast lookups)
- **PostgreSQL + pgvector**: Vector embeddings (semantic search)

This separation allows:
- Optimal query performance
- Easy backup of metadata
- Scalable vector operations

## Configuration Options

All options can be set via `config/rag.php` or `.env` variables:

| Option | Env Var | Default | Description |
|--------|---------|---------|-------------|
| embedding_provider | RAG_EMBEDDING_PROVIDER | gemini | AI provider for embeddings |
| vector_connection | RAG_VECTOR_CONNECTION | pgsql_vector | PostgreSQL connection name |
| documents_connection | RAG_DOCUMENTS_CONNECTION | mysql | Metadata DB connection |
| min_similarity | RAG_MIN_SIMILARITY | 0.45 | Threshold for English queries |
| arabic_min_similarity | RAG_ARABIC_MIN_SIMILARITY | 0.30 | Threshold for Arabic queries |
| embedding_dimensions | RAG_EMBEDDING_DIMENSIONS | 768 | Vector dimensions |
| chunk_size | RAG_CHUNK_SIZE | 1000 | Characters per chunk |
| chunk_overlap | RAG_CHUNK_OVERLAP | 200 | Overlap between chunks |
| max_search_results | RAG_MAX_SEARCH_RESULTS | 10 | Max chunks to retrieve |
| storage_disk | RAG_STORAGE_DISK | rag_documents | Storage disk for files |
| chunker_strategy | RAG_CHUNKER_STRATEGY | character | Chunking strategy |
| chat.provider | RAG_CHAT_PROVIDER | gemini | Chat completion provider |
| chat.model | RAG_CHAT_MODEL | gemini-2.0-flash | Chat model name |
| chat.timeout | RAG_CHAT_TIMEOUT | 120 | Request timeout in seconds |

## Advanced Usage

### Custom Embedding Providers

Create your own embedding implementation:

```php
use AyaAshraf\LaravelRag\Services\EmbeddingGenerator;

class CustomEmbeddingGenerator implements EmbeddingGenerator
{
    public function embed(array $texts): array
    {
        // Your embedding logic
        return $vectors;
    }

    public function dimensions(): int
    {
        return 768;
    }
}

// Register in AppServiceProvider
$this->app->bind(EmbeddingGenerator::class, CustomEmbeddingGenerator::class);
```

### Manual Document Processing

```php
use AyaAshraf\LaravelRag\Models\Document;
use AyaAshraf\LaravelRag\Services\DocumentTextExtractor;
use AyaAshraf\LaravelRag\Services\DocumentEmbeddingIndexer;

$extractor = app(DocumentTextExtractor::class);
$indexer = app(DocumentEmbeddingIndexer::class);

$text = $extractor->extract('/path/to/file.pdf');
$chunksCreated = $indexer->index($document, $text);
```

## Performance Tips

1. **Use Queue Workers** for document processing:
   ```bash
   php artisan queue:listen
   ```

2. **Enable Embedding Caching** in `config/ai.php`:
   ```php
   'caching' => [
       'embeddings' => [
           'cache' => true,
           'store' => 'redis',
       ],
   ]
   ```

3. **Batch Imports** for large document sets:
   ```bash
   php artisan rag:embed-documents
   ```

4. **Tune Similarity Threshold** based on your documents:
   - Lower threshold (0.30) = More results, lower precision
   - Higher threshold (0.70) = Fewer results, higher precision

## Troubleshooting

### Vector Search Returns No Results

Check the similarity threshold:
```env
RAG_MIN_SIMILARITY=0.30  # Lower to get more results
```

### Out of Memory During PDF Processing

Limit PDF size or process asynchronously:
```env
DOCUMENT_TEXT_EXTRACTOR_MAX_SYNC_PDF_KB=20480
```

### Embedding API Rate Limiting

Reduce batch size in `config/rag.php`:
```php
'chunk_batch_size' => 8  // Process fewer chunks at once
```

## Testing

Run the package tests:

```bash
composer test
```

## License

MIT License - see LICENSE file for details.

## Support

For issues, questions, or contributions, please visit the GitHub repository.

---

**Made with ❤️ for Laravel developers**
