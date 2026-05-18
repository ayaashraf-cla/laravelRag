<?php

namespace YourVendor\LaravelRag\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use YourVendor\LaravelRag\RagServiceProvider;
use YourVendor\LaravelRag\Services\EmbeddingGenerator;
use YourVendor\LaravelRag\Tests\Fakes\FakeEmbeddingGenerator;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Livewire\LivewireServiceProvider::class,
            \Laravel\Ai\AiServiceProvider::class,
            RagServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Both connections point to the same in-memory SQLite instance so
        // cross-connection foreign keys work in tests without a real PostgreSQL.
        $sqlite = [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ];

        $app['config']->set('database.default', 'sqlite_docs');
        $app['config']->set('database.connections.sqlite_docs', $sqlite);
        $app['config']->set('database.connections.sqlite_vector', $sqlite);

        // Point the package at the two SQLite connections above.
        $app['config']->set('rag.documents_connection', 'sqlite_docs');
        $app['config']->set('rag.vector_connection', 'sqlite_vector');

        // Tiny vectors so tests are fast; FakeEmbeddingGenerator honours this.
        $app['config']->set('rag.embedding_provider', 'fake');
        $app['config']->set('rag.embedding_dimensions', 3);
        $app['config']->set('rag.chunk_size', 100);
        $app['config']->set('rag.chunk_overlap', 20);
        $app['config']->set('rag.min_similarity', 0.45);
        $app['config']->set('rag.arabic_min_similarity', 0.30);
        $app['config']->set('rag.max_search_results', 10);
        $app['config']->set('rag.storage_disk', 'local');
    }

    protected function defineEnvironment($app): void
    {
        // Replace the real embedding generator with the fake so no API calls
        // are made during tests.
        $app->bind(EmbeddingGenerator::class, FakeEmbeddingGenerator::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        // --- Documents table (standard SQL – runs fine on SQLite) ---
        Schema::connection('sqlite_docs')->dropIfExists('documents');
        Schema::connection('sqlite_docs')->create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status')->default('queued')->index();
            $table->longText('extracted_text')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // --- Document chunks table (SQLite-compatible stub) ---
        // The production migration uses pgvector VECTOR columns and CREATE EXTENSION,
        // neither of which SQLite supports. We substitute JSON for the embedding
        // column here; semantic search tests must run against a real PostgreSQL instance.
        Schema::connection('sqlite_vector')->dropIfExists('document_chunks');
        Schema::connection('sqlite_vector')->create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedSmallInteger('position');
            $table->text('content');
            $table->string('content_hash', 64)->index();
            $table->json('embedding');
            $table->string('embedding_model')->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('tokens_count')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'position']);
            $table->index(['document_id', 'content_hash']);
        });
    }
}
