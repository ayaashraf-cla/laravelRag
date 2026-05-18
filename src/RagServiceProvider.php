<?php

namespace YourVendor\LaravelRag;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use YourVendor\LaravelRag\Livewire\ChatComponent;
use YourVendor\LaravelRag\Livewire\DocumentUploader;
use YourVendor\LaravelRag\Services\AiSdkEmbeddingGenerator;
use YourVendor\LaravelRag\Services\DocumentChunker;
use YourVendor\LaravelRag\Services\EmbeddingGenerator;

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

        // Register package Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \YourVendor\LaravelRag\Commands\EmbedDocumentsCommand::class,
            ]);
        }

        // publishes() MUST live in boot(), not register().
        $this->publishes([
            $this->packagePath('config/rag.php') => config_path('rag.php'),
        ], 'rag-config');

        $this->publishes([
            $this->packagePath('database/migrations') => database_path('migrations'),
        ], 'rag-migrations');

        $this->publishes([
            $this->packagePath('resources/views') => resource_path('views/vendor/rag'),
        ], 'rag-views');
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
