@php
    // Simple state mappings from pre-calculated backend property values
    $analysisPayload = $analysis ?? [];
    $analysisText = $analysisStatus ?? '';

    $totalMs = (float) data_get($analysisPayload, 'timings.total_ms', 0);
    $vectorMs = (float) data_get($analysisPayload, 'timings.vector_search_ms', 0);
    $completionMs = (float) data_get($analysisPayload, 'timings.completion_ms', 0);

    $knownLatencyMs = max($totalMs, $vectorMs + $completionMs, 1);
    $vectorPercent = min(100, round(($vectorMs / $knownLatencyMs) * 100, 1));
    $completionPercent = min(100, round(($completionMs / $knownLatencyMs) * 100, 1));
    $otherPercent = max(0, round(100 - $vectorPercent - $completionPercent, 1));

    $chunks = data_get($analysisPayload, 'retrieval.chunks', []);
    $chunkCount = (int) data_get($analysisPayload, 'retrieval.chunk_count', 0);
    $threshold = data_get($analysisPayload, 'retrieval.threshold');
    $averageSimilarity = data_get($analysisPayload, 'retrieval.average_similarity');

    $inputTokens = (int) data_get($analysisPayload, 'tokens.input', 0);
    $outputTokens = (int) data_get($analysisPayload, 'tokens.output', 0);
    $totalTokens = (int) data_get($analysisPayload, 'tokens.total', 0);

    $formatMs = static fn (float $value): string => $value > 0
        ? ($value >= 1000 ? number_format($value / 1000, 2).'s' : number_format($value, 0).'ms')
        : 'N/A';

    $formatScore = static fn (mixed $value): string => is_numeric($value)
        ? number_format(((float) $value) * 100, 1).'%'
        : 'N/A';

    $scoreBadgeClass = static function (mixed $value): string {
        $score = is_numeric($value) ? (float) $value : 0;
        return match (true) {
            $score >= 0.8 => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
            $score >= 0.55 => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
            default => 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300',
        };
    };
@endphp

<div class="p-6">
    <!-- Streamed AI Text Output Area -->
    <div class="mb-4 min-h-[100px] rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <div wire:stream="ai-response" class="whitespace-pre-wrap text-sm leading-6 dark:text-zinc-200" style="color: #27272a !important;">
            @if (($error ?? '') !== '')
                {{ $error }}
            @else
                {{ $response ?? '' }}
            @endif
        </div>
    </div>

    <!-- Pipeline Metric Dashboard Drawer Section -->
    <section
        id="pipeline-analysis-container"
        wire:key="{{ $analysisKey }}"
        x-data="{
            open: false,
            loaded: false,
            copied: false,
            analysis: @js($analysis),
            copyRaw() {
                navigator.clipboard.writeText(JSON.stringify(this.analysis, null, 2));
                this.copied = true;
                setTimeout(() => this.copied = false, 1400);
            }
        }"
        x-init="$watch('open', value => { if (value) loaded = true })"
        class="mb-4 rounded-lg border border-zinc-200 bg-zinc-50/80 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/70"
    >
        <div class="flex flex-col gap-3 border-b border-zinc-200 p-3 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-on:click="open = !open"
                    class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-white px-3 py-2 text-xs font-semibold shadow-xs transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800"
                    style="color: #3f3f46 !important;"
                    :aria-expanded="open.toString()"
                >
                    <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-90': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                    </svg>
                    View Pipeline Analysis
                </button>

                <div class="hidden h-5 w-px bg-zinc-200 dark:bg-zinc-800 sm:block"></div>

                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="rounded-full bg-white px-2 py-1 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" style="color: #71717a !important;">{{ $formatMs($totalMs) }}</span>
                    <span class="rounded-full bg-white px-2 py-1 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" style="color: #71717a !important;">{{ $chunkCount }} chunks</span>
                    <span class="rounded-full bg-white px-2 py-1 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800" style="color: #71717a !important;">{{ number_format($totalTokens) }} tokens</span>
                </div>
            </div>

            <button
                type="button"
                x-on:click="copyRaw()"
                class="inline-flex items-center justify-center gap-2 rounded-md px-2.5 py-2 text-xs font-medium transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-900 dark:hover:text-zinc-100"
                style="color: #71717a !important;"
                aria-label="Copy raw JSON"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                </svg>
                <span x-text="copied ? 'Copied' : 'Copy Raw JSON'"></span>
            </button>
        </div>

        <div x-show="open" x-collapse x-cloak>
            <div class="grid grid-cols-1 gap-3 p-3 lg:grid-cols-3">
                <div x-show="!loaded" class="lg:col-span-3 rounded-lg border border-dashed border-zinc-300 bg-white p-6 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-400">
                    <div class="animate-pulse">
                        <div class="h-4 w-40 rounded bg-zinc-200 dark:bg-zinc-800"></div>
                        <div class="mt-3 h-3 w-3/4 rounded bg-zinc-200 dark:bg-zinc-800"></div>
                        <div class="mt-6 grid gap-3 sm:grid-cols-3">
                            <div class="h-20 rounded bg-zinc-200 dark:bg-zinc-800"></div>
                            <div class="h-20 rounded bg-zinc-200 dark:bg-zinc-800"></div>
                            <div class="h-20 rounded bg-zinc-200 dark:bg-zinc-800"></div>
                        </div>
                    </div>
                </div>
                <div x-show="loaded" class="lg:col-span-3 grid grid-cols-3 gap-3">
                    <!-- Column 1: Performance Timeline -->
                    <article class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold dark:text-zinc-100" style="color: #09090b !important;">Performance & Latency</h3>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Retrieval and generation timing.</p>
                        </div>
                        <span class="rounded-md bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">{{ $formatMs($totalMs) }}</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <div class="mb-2 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                                <span>Latency split</span>
                                <span>{{ $formatMs($vectorMs + $completionMs) }} measured</span>
                            </div>
                            <div class="flex h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div class="bg-cyan-500" style="width: {{ $vectorPercent }}%"></div>
                                <div class="bg-indigo-500" style="width: {{ $completionPercent }}%"></div>
                                <div class="bg-zinc-300 dark:bg-zinc-700" style="width: {{ $otherPercent }}%"></div>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                <div class="rounded-md bg-cyan-50 p-2 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-300">
                                    <div class="font-semibold">{{ $formatMs($vectorMs) }}</div>
                                    <div class="text-cyan-600/80 dark:text-cyan-300/70">Vector</div>
                                </div>
                                <div class="rounded-md bg-indigo-50 p-2 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    <div class="font-semibold">{{ $formatMs($completionMs) }}</div>
                                    <div class="text-indigo-600/80 dark:text-indigo-300/70">LLM</div>
                                </div>
                                <div class="rounded-md bg-zinc-100 p-2 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    <div class="font-semibold">{{ $otherPercent }}%</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">Other</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Column 2: Context Chunk Details -->
                <article class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold dark:text-zinc-100" style="color: #09090b !important;">Context & Retrieval</h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Chunks, threshold, and confidence.</p>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800/80">
                            <div class="text-lg font-semibold dark:text-zinc-100" style="color: #09090b !important;">{{ $chunkCount }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Chunks</div>
                        </div>
                        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800/80">
                            <div class="text-lg font-semibold dark:text-zinc-100" style="color: #09090b !important;">{{ is_numeric($threshold) ? $formatScore($threshold) : 'N/A' }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Threshold</div>
                        </div>
                        <div class="rounded-md bg-zinc-50 p-3 dark:bg-zinc-800/80">
                            <div class="text-lg font-semibold dark:text-zinc-100" style="color: #09090b !important;">{{ $formatScore($averageSimilarity) }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">Average</div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        @if(!empty($chunks))
                            @foreach ($chunks as $chunk)
                                @php
                                    $score = data_get($chunk, 'similarity') ?? data_get($chunk, 'score');
                                    $source = data_get($chunk, 'original_name') ?? data_get($chunk, 'document.original_name') ?? data_get($chunk, 'document_name') ?? data_get($chunk, 'file_name') ?? 'Unknown source';
                                @endphp
                                <div wire:key="chunk-{{ $loop->index }}-{{ data_get($chunk, 'chunk_index') ?? $loop->iteration }}" class="flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-2 dark:border-zinc-800">
                                    <div class="min-w-0">
                                        <div class="truncate text-xs font-medium dark:text-zinc-200" style="color: #27272a !important;">{{ $source }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Chunk {{ data_get($chunk, 'metadata.chunk_index') ?? data_get($chunk, 'chunk_index') ?? $loop->iteration }}</div>
                                    </div>
                                    <span class="shrink-0 rounded-full border px-2 py-1 text-xs font-semibold {{ $scoreBadgeClass($score) }}">{{ $formatScore($score) }}</span>
                                </div>
                            @endforeach
                        @else
                            <div wire:stream="chunk-analysis" class="rounded-md border border-dashed border-zinc-300 p-3 text-sm leading-6 text-zinc-600 dark:border-zinc-700 dark:text-zinc-400">
                                {{ $analysisText ?: 'Run a query to inspect retrieved context.' }}
                            </div>
                        @endif
                    </div>
                </article>

                <!-- Column 3: Token Usage Profiles -->
                <article class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold dark:text-zinc-100" style="color: #09090b !important;">Token Consumption</h3>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Prompt, completion, and total usage.</p>
                        </div>
                        <span class="rounded-md bg-zinc-900 px-2 py-1 text-xs font-semibold text-white dark:bg-zinc-100 dark:text-zinc-900">{{ number_format($totalTokens) }}</span>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-200 dark:bg-slate-900/70 dark:ring-zinc-800">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Input tokens</span>
                                <span class="font-mono text-sm font-semibold dark:text-slate-100" style="color: #09090b !important;">{{ number_format($inputTokens) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                <div class="h-full bg-teal-500" style="width: {{ $totalTokens > 0 ? round(($inputTokens / $totalTokens) * 100, 1) : 0 }}%"></div>
                            </div>
                        </div>

                        <div class="rounded-lg bg-zinc-50 p-3 ring-1 ring-zinc-200 dark:bg-zinc-800/80 dark:ring-zinc-700">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Output tokens</span>
                                <span class="font-mono text-sm font-semibold dark:text-slate-100" style="color: #09090b !important;">{{ number_format($outputTokens) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                <div class="h-full bg-fuchsia-500" style="width: {{ $totalTokens > 0 ? round(($outputTokens / $totalTokens) * 100, 1) : 0 }}%"></div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Total tokens</span>
                                <span class="font-mono text-base font-semibold dark:text-zinc-100" style="color: #09090b !important;">{{ number_format($totalTokens) }}</span>
                            </div>
                        </div>
                    </div>
                </article>
                </div>
            </div>
        </div>
    </section>

    <!-- Submission Control Form Footer -->
    <form wire:submit="ask" class="flex gap-2">
        <input
            wire:model="query"
            type="text"
            placeholder="Ask a question about your docs..."
            class="min-w-0 flex-1 rounded-md border-zinc-300 bg-white px-3 py-2 shadow-sm focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder-zinc-500 dark:focus:border-zinc-400 dark:focus:ring-zinc-400"
            style="color: #09090b !important;"
        >
        <button type="submit" wire:loading.attr="disabled"     
                class="rounded bg-blue-500 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-zinc-900">
            <span wire:loading.remove>Ask AI</span>
            <span wire:loading>Thinking...</span>
        </button>
    </form>

    @error('query')
        <p class="mt-2 text-sm dark:text-red-400" style="color: #dc2626 !important;">{{ $message }}</p>
    @enderror
</div>