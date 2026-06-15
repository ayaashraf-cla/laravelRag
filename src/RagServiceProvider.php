<?php

namespace CLA\LaravelRag;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use CLA\LaravelRag\Livewire\ChatComponent;
use CLA\LaravelRag\Livewire\DocumentUploader;
use CLA\LaravelRag\Services\AiSdkEmbeddingGenerator;
use CLA\LaravelRag\Services\DocumentChunker;
use CLA\LaravelRag\Services\EmbeddingGenerator;

class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->packagePath('config/rag.php'),
            'rag'
        );

        // Bind EmbeddingGenerator interface to the default implementation.
        // Swap this binding in your AppServiceProvider to use a custom provider.
        $this->app->bind(EmbeddingGenerator::class, AiSdkEmbeddingGenerator::class);

        // Bind DocumentChunker with values from config so chunk_size / chunk_overlap
        // are respected without requiring callers to pass constructor arguments.
        $this->app->bind(DocumentChunker::class, function () {
            return new DocumentChunker(
                chunkSize: (int) config('rag.chunk_size', 1000),
                chunkOverlap: (int) config('rag.chunk_overlap', 200),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->packagePath('database/migrations'));
        $this->loadViewsFrom($this->packagePath('resources/views'), 'rag');

        if (class_exists(Livewire::class)) {
            $this->registerLivewireComponents();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \CLA\LaravelRag\Commands\EmbedDocumentsCommand::class,
            ]);
        }

        $this->publishes([
            $this->packagePath('config/rag.php') => config_path('rag.php'),
        ], 'rag-config');

        $this->publishes([
            $this->packagePath('database/migrations') => database_path('migrations'),
        ], 'rag-migrations');

        $this->publishes([
            $this->packagePath('resources/views') => resource_path('views/vendor/rag'),
        ], 'rag-views');

        // pgvector/pgvector auto-registers a migration that runs CREATE/DROP EXTENSION
        // on whatever the default connection is — which may be MySQL and will fail.
        // We ship an override file with the same timestamp that guards on the driver.
        // Registering the override path inside booted() ensures it is added LAST to
        // the migrator's paths array. Laravel's getMigrationFiles() uses keyBy(name),
        // so the last entry for a given filename wins — our file beats pgvector's.
        $this->app->booted(function () {
            if ($this->app->runningInConsole() && $this->app->bound('migrator')) {
                $this->app->make('migrator')->path(
                    $this->packagePath('database/migration_overrides')
                );
            }
        });
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('rag-chat', ChatComponent::class);
        Livewire::component('rag-uploader', DocumentUploader::class);
    }

    protected function packagePath(string $path = ''): string
    {
        return __DIR__ . '/..' . ($path ? '/' . $path : '');
    }
}