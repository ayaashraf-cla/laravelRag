<?php

namespace AyaAshraf\LaravelRag\Livewire;

use Illuminate\Support\Collection;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;
use AyaAshraf\LaravelRag\Agents\DocumentSearchAgent;

#[Layout('rag::components.layouts.app')]
class ChatComponent extends Component
{
    public string $query = '';

    public string $response = '';

    public string $error = '';

    /**
     * @var array<string, mixed>
     * * Holds detailed performance telemetry (timings, tokens, retrieval stats)
     */
    public array $analysis = [];
    //// Keys used to force Alpine.js/Blade re-renders and show real-time status updates
    public string $analysisKey = 'initial';
    public string $analysisStatus = 'Ready to analyze documents...';

    public function ask(): void
    {
        $this->validate([
            'query' => ['required', 'string', 'max:1000'],
        ]);

        $query = trim($this->query);
        $startedAt = microtime(true);

        $this->response = '';
        $this->error = '';
        $this->analysis = $this->emptyAnalysis();
        $this->refreshAnalysisKey();
        $this->analysisStatus = 'Searching documents...';

        $this->stream(content: $this->analysisStatus, replace: true, to: 'chunk-analysis');
        $this->stream(content: 'Thinking...', replace: true, to: 'ai-response');

        try {
            // Initialize the RAG Agent and attach an inline closure callback.
            // This callback intercepts the retrieved document chunks *before* the LLM answers,
            // allowing us to populate the UI analytics dashboard in real-time.
            $stream = DocumentSearchAgent::make()
                ->onChunksFound(function (Collection $chunks, array $metadata = []): void {
                    $this->captureRetrievalAnalysis($chunks, $metadata);
                })
                ->stream($query, provider: $this->chatProviders(), timeout: $this->chatTimeout());

            $rawResponse = '';
            $this->stream(content: '', replace: true, to: 'ai-response');
            // Loop through the AI response stream chunks (tokens) as they arrive
            foreach ($stream as $event) {
                if (! $event instanceof TextDelta) {
                    // Skip non-text events (like metadata or formatting changes)
                    continue;
                }
                // Append and stream the tiny text fragment directly to the front-end view
                $rawResponse .= $event->delta;
                $this->stream(content: $event->delta, to: 'ai-response');
            }
            //Timings: Tracks how long the vector search took vs. how long the AI model took to generate the answer (in milliseconds)
            $totalMs = (microtime(true) - $startedAt) * 1000;

            $vectorMs = (float) data_get($this->analysis, 'timings.vector_search_ms', 0);
            // Time spent waiting on LLM text generation
            $completionMs = max(0, $totalMs - $vectorMs);
            $promptTokens = (int) ($stream->usage->promptTokens ?? 0);
            $completionTokens = (int) ($stream->usage->completionTokens ?? 0);
            // Merge final token tallies and exact timings into our analytics state array
            $this->analysis = array_replace_recursive($this->analysis, [
                'timings' => [
                    'total_ms' => round($totalMs, 2),
                    'vector_search_ms' => round($vectorMs, 2),
                    'completion_ms' => round($completionMs, 2),
                ],
                'tokens' => [
                    'input' => $promptTokens,
                    'output' => $completionTokens,
                    'total' => $promptTokens + $completionTokens,
                    'cache_write_input_tokens' => (int) ($stream->usage->cacheWriteInputTokens ?? 0),
                    'cache_read_input_tokens' => (int) ($stream->usage->cacheReadInputTokens ?? 0),
                    'reasoning_tokens' => (int) ($stream->usage->reasoningTokens ?? 0),
                ],
            ]);
            // Force Livewire view to refresh with new data
            $this->refreshAnalysisKey();

            $rawResponse = trim($rawResponse);

            if ($rawResponse === '') {
                $rawResponse = 'The documents are indexed, but the AI provider returned an empty answer.';
            }
            // Cleanup the text. Strips unwanted markdown headers/bold text for specific layout requirements
            $this->response = $this->stripMarkdown($rawResponse);
            $this->stream(content: $this->response, replace: true, to: 'ai-response');
        } catch (Throwable $exception) {
            report($exception);

            $this->error = $this->friendlyError($exception);
            $this->stream(content: $this->error, replace: true, to: 'ai-response');

            $this->analysisStatus = 'Search failed.';
            $this->refreshAnalysisKey();
            $this->stream(content: $this->analysisStatus, replace: true, to: 'chunk-analysis');
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $chunks
     * @param  array<string, mixed>  $metadata
     * Processes raw Vector DB chunks and converts them to human-readable UI metrics.     */
    private function captureRetrievalAnalysis(Collection $chunks, array $metadata): void
    {
        $threshold = (float) ($metadata['threshold'] ?? config('services.rag.min_similarity', 0.45));
        $vectorSearchMs = round((float) ($metadata['vector_search_ms'] ?? 0), 2);
        // Fallback state if the database returned zero matching items above our threshold
        if ($chunks->isEmpty()) {
            $this->analysis = array_replace_recursive($this->analysis, [
                'timings' => [
                    'vector_search_ms' => $vectorSearchMs,
                ],
                'retrieval' => [
                    'chunk_count' => 0,
                    'threshold' => $threshold,
                    'average_similarity' => null,
                    'chunks' => [],
                ],
            ]);

            $this->refreshAnalysisKey();

            $this->analysisStatus = "No relevant document contexts matched your query.\nTry adjusting RAG_MIN_SIMILARITY in your .env file.";
            $this->stream(content: $this->analysisStatus, replace: true, to: 'chunk-analysis');

            return;
        }
        // Calculate analytical averages and spreads across our matching chunks
        $count = $chunks->count();
        $highestScore = $chunks->max(fn($chunk) => data_get($chunk, 'similarity') ?? data_get($chunk, 'score') ?? 0);
        $lowestScore = $chunks->min(fn($chunk) => data_get($chunk, 'similarity') ?? data_get($chunk, 'score') ?? 0);
        $averageScore = $chunks->avg(fn($chunk) => (float) (data_get($chunk, 'similarity') ?? data_get($chunk, 'score') ?? 0));
        $totalWords = $chunks->sum(fn($chunk) => str_word_count(data_get($chunk, 'content') ?? data_get($chunk, 'text') ?? ''));

        $sources = $chunks
            ->map(fn($chunk) => data_get($chunk, 'original_name') ?? data_get($chunk, 'document_name') ?? data_get($chunk, 'file_name'))
            ->filter()
            ->unique()
            ->values();

        // Extract a lightweight payload array containing only minimal key data (source name & match score).
        // This limits unnecessary data overhead when pushing values down to Livewire's Javascript layer.
        $optimizedChunks = $chunks
            ->take(5)
            ->values()
            ->map(fn($chunk, $index) => [
                'original_name' => data_get($chunk, 'original_name')
                    ?? data_get($chunk, 'document.original_name')
                    ?? data_get($chunk, 'document_name')
                    ?? data_get($chunk, 'file_name')
                    ?? 'Unknown source',
                'similarity' => data_get($chunk, 'similarity') ?? data_get($chunk, 'score'),
                'chunk_index' => data_get($chunk, 'metadata.chunk_index')
                    ?? data_get($chunk, 'chunk_index')
                    ?? ($index + 1),
            ])
            ->all();
        // Save the built analytical context back to the primary view array
        $this->analysis = array_replace_recursive($this->analysis, [
            'timings' => [
                'vector_search_ms' => $vectorSearchMs,
            ],
            'retrieval' => [
                'chunk_count' => $count,
                'threshold' => $threshold,
                'average_similarity' => round((float) $averageScore, 4),
                'highest_similarity' => $highestScore,
                'lowest_similarity' => $lowestScore,
                'source_count' => $sources->count(),
                'sources' => $sources->all(),
                'context_words' => $totalWords,
                'chunks' => $optimizedChunks,
            ],
        ]);
        $this->refreshAnalysisKey();

        // $lines = [
        //     "Search Results: retained {$count} relevant segment(s).",
        // ];

        // if ($sources->isNotEmpty()) {
        //     $lines[] = 'Sources Referenced: ' . $sources->implode(', ');
        // }

        // if ($highestScore !== null) {
        //     $lines[] = 'Confidence Range: ' . round(((float) $highestScore) * 100, 1) . '% down to ' . round(((float) $lowestScore) * 100, 1) . '%.';
        // }

        // if ($totalWords > 0) {
        //     $lines[] = "Context payload: fed ~{$totalWords} words into AI prompt context.";
        // }

        // $this->analysisStatus = implode("\n", $lines);
        $this->stream(content: $this->analysisStatus, replace: true, to: 'chunk-analysis');
    }

    /**
     * Provides an empty structural canvas for analytics resets.
     * @return array<string, mixed>
     */
    private function emptyAnalysis(): array
    {
        return [
            'timings' => [
                'total_ms' => 0,
                'vector_search_ms' => 0,
                'completion_ms' => 0,
            ],
            'retrieval' => [
                'chunk_count' => 0,
                'threshold' => config('services.rag.min_similarity', 0.45),
                'average_similarity' => null,
                'chunks' => [],
            ],
            'tokens' => [
                'input' => 0,
                'output' => 0,
                'total' => 0,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function chatProviders(): array
    {
        $providers = config('services.rag.chat_providers', ['openai', 'gemini']);

        if (! is_array($providers) || $providers === []) {
            return ['openai', 'gemini'];
        }

        return array_values(array_filter($providers, static fn(mixed $provider): bool => is_string($provider) && $provider !== ''));
    }

    private function chatTimeout(): int
    {
        return max(5, (int) config('services.rag.chat_timeout', 20));
    }

    private function friendlyError(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());
        $providers = implode(', ', $this->chatProviders());

        if (str_contains($message, 'rate') || str_contains($message, '429')) {
            return 'The configured chat provider is rate-limiting requests right now. Current RAG_CHAT_PROVIDERS: ' . $providers . '.';
        }

        if (str_contains($message, 'api key') || str_contains($message, 'unauthorized') || str_contains($message, '401')) {
            return 'The chat provider API key is not configured correctly. Please check your OpenAI or Gemini key in .env.';
        }

        return 'I could not generate an answer. Please check the Laravel log for the provider error and try again.';
    }

    protected function stripMarkdown(string $text): string
    {
        $text = preg_replace('/[*_]+/', '', $text);
        $text = preg_replace('/^#+\s+/m', '', $text);
        $text = preg_replace('/^\s*[\*-]\s+/m', '', $text);

        return trim($text);
    }
    /**
     * Generates a unique state key by hashing the analysis array.
     * Used to force Livewire/Alpine components to re-render when properties deep inside change.
     */
    private function refreshAnalysisKey(): void
    {
        $this->analysisKey = 'analysis-' . md5(json_encode($this->analysis, JSON_THROW_ON_ERROR));
    }

    public function render()
    {
        return view('rag::chat');
    }
}

