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
            $score >= 0.8 => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            $score >= 0.55 => 'border-amber-200 bg-amber-50 text-amber-700',
            default => 'border-red-200 bg-red-50 text-red-700',
        };
    };
@endphp

<div class="flex flex-col gap-4 p-6">

    {{-- AI Response Area --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-4 py-2.5">
            <svg class="h-4 w-4 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/>
                <path d="M12 8v4l3 3"/>
            </svg>
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">AI Response</span>
        </div>
        <div class="min-h-[110px] p-5">
            <div wire:stream="ai-response" class="whitespace-pre-wrap text-sm leading-7 text-slate-800">
                @if (($error ?? '') !== '')
                    <span class="text-red-600">{{ $error }}</span>
                @else
                    {{ $response ?? '' }}
                @endif
            </div>
        </div>
    </div>

    {{-- Pipeline Metric Dashboard Drawer --}}
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
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        {{-- Section Header --}}
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-on:click="open = !open"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    :aria-expanded="open.toString()"
                >
                    <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-90': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                    </svg>
                    Pipeline Analysis
                </button>

                <div class="hidden h-4 w-px bg-slate-200 sm:block"></div>

                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 font-medium text-sky-700 ring-1 ring-sky-200">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        {{ $formatMs($totalMs) }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2.5 py-1 font-medium text-violet-700 ring-1 ring-violet-200">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        {{ $chunkCount }} chunks
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700 ring-1 ring-emerald-200">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                        {{ number_format($totalTokens) }} tokens
                    </span>
                </div>
            </div>

            <button
                type="button"
                x-on:click="copyRaw()"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none"
                aria-label="Copy raw JSON"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                </svg>
                <span x-text="copied ? 'Copied!' : 'Copy JSON'"></span>
            </button>
        </div>

        {{-- Collapsible Body --}}
        <div x-show="open" x-collapse x-cloak>
            <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-3">

                {{-- Loading skeleton --}}
                <div x-show="!loaded" class="lg:col-span-3 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6">
                    <div class="animate-pulse space-y-4">
                        <div class="h-4 w-48 rounded-full bg-slate-200"></div>
                        <div class="h-3 w-3/4 rounded-full bg-slate-200"></div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="h-24 rounded-xl bg-slate-200"></div>
                            <div class="h-24 rounded-xl bg-slate-200"></div>
                            <div class="h-24 rounded-xl bg-slate-200"></div>
                        </div>
                    </div>
                </div>

                <div x-show="loaded" class="lg:col-span-3 grid grid-cols-1 gap-4 lg:grid-cols-3">

                    {{-- Card 1: Performance & Latency --}}
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Performance & Latency</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Retrieval and generation timing.</p>
                            </div>
                            <span class="shrink-0 rounded-lg bg-sky-50 px-2 py-1 text-xs font-bold text-sky-700 ring-1 ring-sky-200">{{ $formatMs($totalMs) }}</span>
                        </div>
                        <div>
                            <div class="mb-2 flex items-center justify-between text-xs text-slate-500">
                                <span>Latency split</span>
                                <span class="font-medium text-slate-700">{{ $formatMs($vectorMs + $completionMs) }} measured</span>
                            </div>
                            <div class="flex h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="bg-cyan-400 transition-all" style="width: {{ $vectorPercent }}%"></div>
                                <div class="bg-indigo-500 transition-all" style="width: {{ $completionPercent }}%"></div>
                                <div class="bg-slate-300" style="width: {{ $otherPercent }}%"></div>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                                <div class="rounded-lg bg-cyan-50 p-2.5 ring-1 ring-cyan-200">
                                    <div class="font-bold text-cyan-700">{{ $formatMs($vectorMs) }}</div>
                                    <div class="mt-0.5 text-cyan-600/80">Vector</div>
                                </div>
                                <div class="rounded-lg bg-indigo-50 p-2.5 ring-1 ring-indigo-200">
                                    <div class="font-bold text-indigo-700">{{ $formatMs($completionMs) }}</div>
                                    <div class="mt-0.5 text-indigo-600/80">LLM</div>
                                </div>
                                <div class="rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-200">
                                    <div class="font-bold text-slate-700">{{ $otherPercent }}%</div>
                                    <div class="mt-0.5 text-slate-500">Other</div>
                                </div>
                            </div>
                        </div>
                    </article>

                    {{-- Card 2: Context & Retrieval --}}
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-slate-900">Context & Retrieval</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Chunks, threshold, and confidence.</p>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-lg bg-slate-50 p-3 text-center ring-1 ring-slate-200">
                                <div class="text-xl font-bold text-slate-900">{{ $chunkCount }}</div>
                                <div class="text-xs text-slate-500">Chunks</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3 text-center ring-1 ring-slate-200">
                                <div class="text-xl font-bold text-slate-900">{{ is_numeric($threshold) ? $formatScore($threshold) : 'N/A' }}</div>
                                <div class="text-xs text-slate-500">Threshold</div>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3 text-center ring-1 ring-slate-200">
                                <div class="text-xl font-bold text-slate-900">{{ $formatScore($averageSimilarity) }}</div>
                                <div class="text-xs text-slate-500">Average</div>
                            </div>
                        </div>
                        <div class="mt-4 space-y-2">
                            @if(!empty($chunks))
                                @foreach ($chunks as $chunk)
                                    @php
                                        $score = data_get($chunk, 'similarity') ?? data_get($chunk, 'score');
                                        $source = data_get($chunk, 'original_name') ?? data_get($chunk, 'document.original_name') ?? data_get($chunk, 'document_name') ?? data_get($chunk, 'file_name') ?? 'Unknown source';
                                    @endphp
                                    <div wire:key="chunk-{{ $loop->index }}-{{ data_get($chunk, 'chunk_index') ?? $loop->iteration }}" class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            <div class="min-w-0">
                                                <div class="truncate text-xs font-medium text-slate-800">{{ $source }}</div>
                                                <div class="text-xs text-slate-400">Chunk {{ data_get($chunk, 'metadata.chunk_index') ?? data_get($chunk, 'chunk_index') ?? $loop->iteration }}</div>
                                            </div>
                                        </div>
                                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-semibold {{ $scoreBadgeClass($score) }}">{{ $formatScore($score) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <div wire:stream="chunk-analysis" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-3 text-xs leading-6 text-slate-500">
                                    {{ $analysisText ?: 'Run a query to inspect retrieved context.' }}
                                </div>
                            @endif
                        </div>
                    </article>

                    {{-- Card 3: Token Consumption --}}
                    <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Token Consumption</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Prompt, completion, and total usage.</p>
                            </div>
                            <span class="shrink-0 rounded-lg bg-slate-900 px-2 py-1 text-xs font-bold text-white">{{ number_format($totalTokens) }}</span>
                        </div>
                        <div class="space-y-3">
                            <div class="rounded-lg bg-teal-50 p-3 ring-1 ring-teal-200">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-medium text-teal-700">Input tokens</span>
                                    <span class="font-mono text-sm font-bold text-teal-800">{{ number_format($inputTokens) }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-teal-100">
                                    <div class="h-full rounded-full bg-teal-500 transition-all" style="width: {{ $totalTokens > 0 ? round(($inputTokens / $totalTokens) * 100, 1) : 0 }}%"></div>
                                </div>
                            </div>
                            <div class="rounded-lg bg-fuchsia-50 p-3 ring-1 ring-fuchsia-200">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-medium text-fuchsia-700">Output tokens</span>
                                    <span class="font-mono text-sm font-bold text-fuchsia-800">{{ number_format($outputTokens) }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-fuchsia-100">
                                    <div class="h-full rounded-full bg-fuchsia-500 transition-all" style="width: {{ $totalTokens > 0 ? round(($outputTokens / $totalTokens) * 100, 1) : 0 }}%"></div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-medium text-slate-600">Total tokens</span>
                                    <span class="font-mono text-base font-bold text-slate-900">{{ number_format($totalTokens) }}</span>
                                </div>
                            </div>
                        </div>
                    </article>

                </div>
            </div>
        </div>
    </section>

    {{-- Submission Form --}}
    <form wire:submit="ask" class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <input
                wire:model="query"
                type="text"
                placeholder="Ask a question about your documents..."
                class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 shadow-xs placeholder:text-slate-400 transition focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-200"
            >
        </div>
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
        >
            <span wire:loading.remove class="flex items-center gap-1.5">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                Ask AI
            </span>
            <span wire:loading class="flex items-center gap-1.5">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                Thinking...
            </span>
        </button>
    </form>

    @error('query')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror

</div>
