<?php

namespace CLA\LaravelRag\Services;

/**
 * Base interface for embedding generation.
 * Allows swappable implementations (Gemini, OpenAI, Cohere, etc).
 */
interface EmbeddingGenerator
{
    /**
     * @param  array<int, string>  $inputs
     * @return array<int, array<int, float>>
     */
    public function embed(array $inputs): array;

    public function model(): string;
}
