<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $vectorConn = config('rag.vector_connection', 'pgsql_vector');

        // Enable pgvector extension (PostgreSQL only)
        DB::connection($vectorConn)->statement('CREATE EXTENSION IF NOT EXISTS vector;');

        $dimensions = config('rag.embedding_dimensions', 1536);

        Schema::connection($vectorConn)->create('document_chunks', function (Blueprint $table) use ($dimensions) {
         $table->id();
                $table->unsignedBigInteger('document_id');
                $table->unsignedInteger('position')->nullable();
                $table->text('content');
                $table->char('content_hash', 64)->nullable();

                // This will now work because the 'vector' type exists
                $table->vector('embedding', $dimensions)->nullable();

                $table->string('embedding_model')->nullable();
                $table->unsignedInteger('embedding_dimensions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('embedded_at')->nullable();
                $table->timestamps();

                $table->unique(['document_id', 'position']);
                $table->index(['document_id', 'content_hash']);
        });

        // Create HNSW vector index for faster similarity search
        DB::connection($vectorConn)->statement(
            'CREATE INDEX IF NOT EXISTS document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)'
        );
    }

    public function down(): void
    {
        Schema::connection(config('rag.vector_connection', 'pgsql_vector'))->dropIfExists('document_chunks');
    }
};
