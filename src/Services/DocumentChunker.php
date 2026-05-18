<?php

namespace AyaAshraf\LaravelRag\Services;

class DocumentChunker
{
    public function __construct(
        //The maximum length of a single chunk
        private readonly int $chunkSize = 4000,
        //The amount of text from the end of one chunk to repeat at the start of the next
        private readonly int $chunkOverlap = 500,
    ) {}

    /**
     * @return array<int, string>
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }
        //// 1. Split into paragraphs to preserve semantic units
        $paragraphs = preg_split("/\n{2,}/u", $text) ?: [$text];
        $chunks = [];
        $current = '';
        //iterates through these paragraphs and builds a "candidate" chunk
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }
            // 2. If the single paragraph is already bigger than the limit, 
            // handle it with a sliding window.
            if (mb_strlen($paragraph) > $this->chunkSize) {
                $this->flushChunk($chunks, $current);
                $current = '';

                foreach ($this->splitLongText($paragraph) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }
            //// 3. Check if adding this paragraph would push the current chunk over the limit
            $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;
            if (mb_strlen($candidate) > $this->chunkSize) {
                $this->flushChunk($chunks, $current);
                $current = $this->overlapPrefix($current) . $paragraph;
            } else {
                $current = $candidate;
            }
        }

        $this->flushChunk($chunks, $current);

        return array_values(array_filter(array_map(trim(...), $chunks)));
    }

    /**
     * @param  array<int, string>  $chunks
     */
    private function flushChunk(array &$chunks, string $chunk): void
    {
        $chunk = trim($chunk);

        if ($chunk !== '') {
            $chunks[] = $chunk;
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitLongText(string $text): array
    {
        $chunks = [];
        $step = max(1, $this->chunkSize - $this->chunkOverlap);
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset += $step) {
            $chunks[] = trim(mb_substr($text, $offset, $this->chunkSize));

            if ($offset + $this->chunkSize >= $length) {
                break;
            }
        }

        return array_values(array_filter($chunks));
    }

    private function overlapPrefix(string $previousChunk): string
    {
        if ($this->chunkOverlap <= 0 || $previousChunk === '') {
            return '';
        }

        $overlap = trim(mb_substr($previousChunk, -$this->chunkOverlap));

        return $overlap === '' ? '' : $overlap . "\n\n";
    }
}
