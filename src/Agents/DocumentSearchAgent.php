<?php

namespace YourVendor\LaravelRag\Agents;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\SimilaritySearch;
use Stringable;
use YourVendor\LaravelRag\Models\Document;
use YourVendor\LaravelRag\Models\DocumentChunk;
use YourVendor\LaravelRag\Services\EmbeddingGenerator;

class DocumentSearchAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected ?Closure $onChunksFoundCallback = null;

    /**
     * Fluent interface setter. Allows Livewire components to catch
     * search event metrics as soon as the database operation finishes.
     */
    public function onChunksFound(Closure $callback): self
    {
        $this->onChunksFoundCallback = $callback;

        return $this;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return config('rag.agent_instructions',
            'You are a helpful document assistant. Use the search tool to find context for the user\'s question. ' .
            'Answer ONLY using the provided documents. If the answer isn\'t found, say "I am sorry, but that information is not in my database."'
        );
    }

    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): array
    {
        $analysisCallback = $this->onChunksFoundCallback;

        return [
            new SimilaritySearch(function (string $queryString) use ($analysisCallback) {
                // Convert user's raw textual string query into a mathematical vector embedding array
                $embedding = app(EmbeddingGenerator::class)->embed([$queryString])[0] ?? [];

                if ($embedding === []) {
                    return [];
                }

                // Bilingual Match Threshold Calculation
                // Arabic text queries usually yield a lower similarity score.
                // We use regex to detect Arabic and apply a lower threshold.
                $minSimilarity = (float) config('rag.min_similarity', 0.45);

                if (preg_match('/\p{Arabic}/u', $queryString)) {
                    $minSimilarity = (float) config('rag.arabic_min_similarity', 0.30);
                }

                $searchStartedAt = microtime(true);

                // Execute Vector Cosine/Distance Search against the PostgreSQL/Vector Database
                $chunksCollection = DocumentChunk::semanticSearch(
                    $embedding,
                    limit: $this->candidateLimit(),
                    minSimilarity: $minSimilarity,
                );

                $vectorSearchMs = (microtime(true) - $searchStartedAt) * 1000;

                $documentNames = Document::query()
                    ->whereIn('id', $chunksCollection->pluck('document_id')->filter()->unique())
                    ->pluck('original_name', 'id');

                // Convert technical cosine vector distance values into a neat percentage score (0.0 to 1.0)
                $chunks = $chunksCollection
                    ->map(function (DocumentChunk $chunk): array {
                        $distance = $chunk->getAttribute('embedding_distance');
                        // Exclude the huge raw float vector array data to keep the memory profile low
                        return Arr::except($chunk->toArray(), ['embedding']) + [
                            'similarity' => is_numeric($distance) ? max(0, min(1, 1 - (float) $distance)) : null,
                        ];
                    })
                    ->map(fn (array $chunk): array => $chunk + [
                        'original_name' => $documentNames->get($chunk['document_id'] ?? null, 'Unknown Document'),
                    ]);

                // Prune extra text characters to prevent overloading the LLM's prompt window boundaries
                $chunks = $this->pruneChunksForPrompt($chunks)->values();

                // Dispatch data metrics immediately back to the UI listener if one was registered
                if ($analysisCallback !== null) {
                    $analysisCallback(
                        $chunks,
                        [
                            'threshold' => $minSimilarity,
                            'vector_search_ms' => $vectorSearchMs,
                        ],
                    );
                }

                return $chunks->toArray();
            }),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $chunks
     * @return Collection<int, array<string, mixed>>
     *
     * Defensive pipeline optimizer. Limits total chunk count and trims down long paragraphs
     * to keep contextual density high while avoiding token bloat/over-spending.
     */
    private function pruneChunksForPrompt(Collection $chunks): Collection
    {
        $maxChunks = max(1, config('rag.max_search_results', 10));
        $maxContextChars = max(500, (int) config('rag.max_context_chars', 12000));
        $maxChunkChars = max(500, (int) config('rag.max_chunk_chars', 3000));
        $usedChars = 0;

        return $chunks
            ->take($maxChunks)
            ->map(function (array $chunk) use ($maxChunkChars): array {
                $content = trim((string) ($chunk['content'] ?? ''));

                // Truncate overly long text snippets at the specific single-chunk boundary limit
                if (mb_strlen($content) > $maxChunkChars) {
                    $content = rtrim(mb_substr($content, 0, $maxChunkChars)) . '...';
                }

                return [
                    'id' => $chunk['id'] ?? null,
                    'document_id' => $chunk['document_id'] ?? null,
                    'position' => $chunk['position'] ?? null,
                    'content' => $content,
                    'metadata' => $chunk['metadata'] ?? [],
                    'original_name' => $chunk['original_name'] ?? 'Unknown Document',
                    'similarity' => $chunk['similarity'] ?? null,
                ];
            })
            // Accumulate character length dynamically and discard remaining records
            // the exact moment our safety context window ceiling is breached.
            ->takeUntil(function (array $chunk) use (&$usedChars, $maxContextChars): bool {
                $nextSize = mb_strlen((string) ($chunk['content'] ?? ''));

                if ($usedChars > 0 && ($usedChars + $nextSize) > $maxContextChars) {
                    return true;
                }

                $usedChars += $nextSize;

                return false;
            });
    }

    /**
     * Resolves the upper calculation safety boundary size for internal vector lookups.
     */
    private function candidateLimit(): int
    {
        return max(
            config('rag.max_search_results', 10),
            (int) config('rag.candidate_limit', 20),
        );
    }
}
