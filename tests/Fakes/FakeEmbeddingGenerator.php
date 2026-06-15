<?php

namespace CLA\LaravelRag\Tests\Fakes;

use CLA\LaravelRag\Services\EmbeddingGenerator;

/**
 * Returns deterministic unit-length vectors so tests never hit the Gemini API.
 * Each text gets a stable 3-element vector derived from its hash, making
 * similarity assertions predictable and repeatable.
 */
class FakeEmbeddingGenerator implements EmbeddingGenerator
{
    private int $dimensions;

    public function __construct(int $dimensions = 3)
    {
        $this->dimensions = $dimensions;
    }

    /**
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embed(array $texts): array
    {
        return array_map(fn (string $text) => $this->vectorFor($text), $texts);
    }

    public function model(): string
    {
        return 'fake';
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Produce a stable, normalised float vector from a text string.
     * Identical text → identical vector; similar text → slightly different vector.
     *
     * @return array<float>
     */
    private function vectorFor(string $text): array
    {
        $seed = crc32($text);
        $raw  = [];

        for ($i = 0; $i < $this->dimensions; $i++) {
            // Cheap deterministic pseudo-random sequence from the seed.
            $seed  = ($seed * 1664525 + 1013904223) & 0xFFFFFFFF;
            $raw[] = (($seed & 0x7FFFFFFF) / 0x7FFFFFFF) * 2.0 - 1.0;
        }

        // Normalise to unit length so cosine distance is meaningful.
        $norm = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $raw)));

        if ($norm < 1e-9) {
            return array_fill(0, $this->dimensions, 0.0);
        }

        return array_map(fn (float $v) => $v / $norm, $raw);
    }
}
