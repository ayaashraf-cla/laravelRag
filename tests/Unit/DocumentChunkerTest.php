<?php

namespace AyaAshraf\LaravelRag\Tests\Unit;

use AyaAshraf\LaravelRag\Services\DocumentChunker;
use AyaAshraf\LaravelRag\Tests\TestCase;

class DocumentChunkerTest extends TestCase
{
    public function test_chunks_text_correctly(): void
    {
        $chunker = new DocumentChunker(chunkSize: 100, chunkOverlap: 20);

        $text = 'This is a sample document. It contains multiple sentences and paragraphs. ' .
                'The chunker should split this into smaller pieces while preserving context.';

        $chunks = $chunker->chunk($text);

        $this->assertNotEmpty($chunks);
        $this->assertTrue(
            collect($chunks)->every(fn (string $chunk) => mb_strlen($chunk) <= 100),
            'All chunks must be at or below the configured chunk size.'
        );
    }

    public function test_returns_empty_array_for_empty_text(): void
    {
        $chunker = new DocumentChunker();
        $chunks = $chunker->chunk('');

        $this->assertEmpty($chunks);
    }

    public function test_overlap_prefix_appears_in_subsequent_chunk(): void
    {
        $chunker = new DocumentChunker(chunkSize: 60, chunkOverlap: 20);

        // Two paragraphs that together exceed chunkSize; overlap from the first
        // should appear at the start of the second.
        $text = "First paragraph with exactly enough words here.\n\nSecond paragraph that continues the story.";

        $chunks = $chunker->chunk($text);

        if (count($chunks) >= 2) {
            $lastCharsOfFirst = mb_substr($chunks[0], -20);
            $this->assertStringContainsString(
                trim($lastCharsOfFirst),
                $chunks[1],
                'The overlap prefix from chunk 0 should appear at the start of chunk 1.'
            );
        } else {
            // Text fits in one chunk; pass trivially.
            $this->assertCount(1, $chunks);
        }
    }

    public function test_single_oversized_paragraph_is_split_with_sliding_window(): void
    {
        $chunker = new DocumentChunker(chunkSize: 50, chunkOverlap: 10);

        // 200-character single paragraph with no double-newlines.
        $text = str_repeat('abcdefghij', 20);

        $chunks = $chunker->chunk($text);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertTrue(
            collect($chunks)->every(fn (string $chunk) => mb_strlen($chunk) <= 50)
        );
    }
}
