<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The embedding provider to use for generating vector embeddings.
    | Supported: 'gemini', 'openai', 'cohere', 'azure'
    |
    | `.env` variables mapped: EMBEDDING_PROVIDER
    |
    */

    'embedding_provider' => env('EMBEDDING_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Embedding Batch Size
    |--------------------------------------------------------------------------
    |
    | The number of text chunks to send in a single batch request to the
    | embedding provider API.
    |
    */

    'embedding_batch_size' => (int) env('EMBEDDING_BATCH_SIZE', 64),

    /*
    |--------------------------------------------------------------------------
    | Vector Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for pgvector operations.
    | Typically a PostgreSQL connection with pgvector extension.
    |
    */

    'vector_connection' => env('RAG_VECTOR_CONNECTION', 'pgsql_vector'),

    /*
    |--------------------------------------------------------------------------
    | Documents Connection
    |--------------------------------------------------------------------------
    |
    | The database connection for storing document metadata (MySQL, PostgreSQL, etc).
    |
    */

    'documents_connection' => env('RAG_DOCUMENTS_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Similarity Threshold
    |--------------------------------------------------------------------------
    |
    | Minimum similarity score (0.0 to 1.0) for document chunk retrieval.
    | Lower values return more results, higher values are more selective.
    |
    */

    'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Arabic Text Similarity Threshold
    |--------------------------------------------------------------------------
    |
    | Special minimum similarity threshold for Arabic language queries.
    | Arabic text often produces lower similarity scores, so this can be adjusted.
    |
    */

    'arabic_min_similarity' => (float) env('RAG_ARABIC_MIN_SIMILARITY', 0.30),

    /*
    |--------------------------------------------------------------------------
    | Embedding Dimensions & Model Setup
    |--------------------------------------------------------------------------
    |
    | Vector embedding dimensions and specific model configurations. 
    | Must match your active embedding model output.
    |
    */

    'embedding_dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1536),
    'embedding_model'      => env('EMBEDDING_MODEL', 'text-embedding-3-small'),

    /*
    |--------------------------------------------------------------------------
    | Chunk Management
    |--------------------------------------------------------------------------
    |
    | Default character limit parameters per chunk during document processing.
    |
    */

    'chunk_size'      => (int) env('RAG_MAX_CHUNK_CHARS', 3000),
    'chunk_overlap'   => (int) env('RAG_CHUNK_OVERLAP', 200),
    'max_context_len' => (int) env('RAG_MAX_CONTEXT_CHARS', 12000),

    /*
    |--------------------------------------------------------------------------
    | Search & Retrieval Strategy Tuning
    |--------------------------------------------------------------------------
    |
    | Settings for pinning down maximum vector returns, pre-filtering limits (candidate limit),
    | and final output counts sent to the LLM (top_k).
    |
    */

    'max_search_results' => (int) env('RAG_MAX_SEARCH_RESULTS', 10),
    'top_k'              => (int) env('RAG_TOP_K', 4),
    'candidate_limit'    => (int) env('RAG_CANDIDATE_LIMIT', 8),

    /*
    |--------------------------------------------------------------------------
    | Document Storage Disk
    |--------------------------------------------------------------------------
    |
    | Filesystem disk for storing uploaded documents.
    |
    */

    'storage_disk' => env('RAG_STORAGE_DISK', 'rag_documents'),

    /*
    |--------------------------------------------------------------------------
    | Text Chunker Strategy
    |--------------------------------------------------------------------------
    |
    | Strategy for splitting documents into chunks.
    | Options: 'character', 'token', 'sentence', 'markdown'
    |
    */

    'chunker_strategy' => env('RAG_CHUNKER_STRATEGY', 'character'),

    /*
    |--------------------------------------------------------------------------
    | AI Chat Provider & Model
    |--------------------------------------------------------------------------
    |
    | Provider and model configuration for chat completions.
    |
    */

    'chat' => [
        'provider' => env('RAG_CHAT_PROVIDERS', 'openai'),
        'model'    => env('RAG_CHAT_MODEL', 'gpt-4-mini'),
        'timeout'  => (int) env('RAG_CHAT_TIMEOUT', 40),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third-Party API Engine Credentials & Global Settings
    |--------------------------------------------------------------------------
    |
    | Dedicated configuration blocks for API keys, custom endpoints, 
    | and HTTP client connection timeouts.
    |
    */

    'providers' => [
        'gemini' => [
            'api_key'         => env('GEMINI_API_KEY'),
            'base_url'        => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 30),
        ],
        'openai' => [
            'connect_timeout' => (int) env('OPENAI_CONNECT_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Instructions
    |--------------------------------------------------------------------------
    |
    | Default system instructions for the RAG agent.
    |
    */

    'agent_instructions' => env(
        'RAG_AGENT_INSTRUCTIONS',
        'You are a helpful document assistant. Use the search tool to find context for the user\'s question. ' .
        'Answer ONLY using the provided documents. If the answer isn\'t found, say "I am sorry, but that information is not in my database."'
    ),

];