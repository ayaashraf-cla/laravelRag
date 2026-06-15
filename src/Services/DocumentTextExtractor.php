<?php

namespace CLA\LaravelRag\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser as PdfParser;
use Spatie\PdfToText\Exceptions\BinaryNotFoundException as PdfBinaryNotFoundException;
use Spatie\PdfToText\Exceptions\CouldNotExtractText as CouldNotExtractTextException;
use Spatie\PdfToText\Pdf as SpatiePdfToText;
use Throwable;

class DocumentTextExtractor
{
    private int $maxSyncPdfBytes;

    public function __construct(?int $maxSyncPdfKilobytes = null)
    {
        $this->maxSyncPdfBytes = ($maxSyncPdfKilobytes ?? (int) env('DOCUMENT_TEXT_EXTRACTOR_MAX_SYNC_PDF_KB', 20480)) * 1024;
    }

    public function extract(string $path, bool $enforceSyncPdfLimit = true): string
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("Document not found at [{$path}].");
        }

        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'txt' => $this->normalize($this->extractTextFile($path)),
            'pdf' => $this->normalize($this->extractPdf($path, $enforceSyncPdfLimit), repairPdfArabic: true),
            'docx' => $this->normalize($this->extractDocx($path)),
            'xls', 'xlsx','csv' => $this->normalize($this->extractSpreadsheet($path)),
            default => throw new InvalidArgumentException("Unsupported document type [{$extension}]."),
        };
    }

    private function extractTextFile(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Unable to read uploaded text file.');
        }

        return $contents;
    }

    private function extractPdf(string $path, bool $enforceSyncPdfLimit): string
    {
        if ($enforceSyncPdfLimit && filesize($path) > $this->maxSyncPdfBytes) {
            throw new RuntimeException('This PDF is too large for in-browser extraction. Store it and process it with a queued job.');
        }

        $config = new PdfParserConfig;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(32 * 1024 * 1024);

        $text = (new PdfParser(config: $config))->parseFile($path)->getText();

        if ($this->shouldFallbackToPdfToText($text)) {
            return $this->extractPdfWithPdfToText($path) ?: $text;
        }

        return $text;
    }

    private function extractPdfWithPdfToText(string $path): string
    {
        try {
            $text = SpatiePdfToText::getText($path, null, ['enc UTF-8'], 600);

            if ($this->looksLikeBrokenPdfText($text)) {
                $text = SpatiePdfToText::getText($path, null, ['enc UTF-8', 'raw'], 600);
            }

            return $text;
        } catch (PdfBinaryNotFoundException|CouldNotExtractTextException|Throwable) {
            return '';
        }
    }

    private function looksLikeBrokenPdfText(string $text): bool
    {
        return ! mb_check_encoding($text, 'UTF-8')
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)
            || preg_match('/[\xED][\xA0-\xBF][\x80-\xBF]/', $text);
    }

    private function shouldFallbackToPdfToText(string $text): bool
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            return true;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            return true;
        }

        if (preg_match('/[\xED][\xA0-\xBF][\x80-\xBF]/', $text)) {
            return true;
        }

        // If we have Arabic content, but a lot of junk bytes, fallback.
        $invalidByteCount = preg_match_all('/[\x80-\xFF]/', $text);

        return $invalidByteCount > 50 && preg_match('/\p{Arabic}/u', $text);
    }

    private function extractDocx(string $path): string
    {
        $document = IOFactory::load($path);
        $text = [];

        foreach ($document->getSections() as $section) {
            $text[] = $this->extractFromElements($section->getElements());
        }

        return implode("\n", array_filter($text));
    }

    private function extractSpreadsheet(string $path): string
    {
        $reader = SpreadsheetIOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($path);
        $text = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(true);

                foreach ($cellIterator as $cell) {
                    $cells[] = trim((string) $cell->getFormattedValue());
                }

                if ($cells !== []) {
                    $text[] = implode("\t", array_filter($cells, static fn ($value) => $value !== ''));
                }
            }
        }

        return implode("\n", array_filter($text));
    }

    /**
     * @param  array<int, mixed>  $elements
     */
    private function extractFromElements(array $elements): string
    {
        $text = [];

        foreach ($elements as $element) {
            if ($element instanceof Text || $element instanceof Title) {
                $text[] = $element->getText();
            } elseif ($element instanceof TextRun || $element instanceof AbstractContainer) {
                $text[] = $this->extractFromElements($element->getElements());
            } elseif (method_exists($element, 'getText')) {
                $text[] = (string) $element->getText();
            }
        }

        return implode("\n", array_filter($text));
    }

    private function normalize(string $text, bool $repairPdfArabic = false): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove invalid UTF-8 sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        if ($repairPdfArabic && $this->looksLikeVisualOrderArabic($text)) {
            $text = $this->repairArabicVisualOrder($text);
        }

        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/(\r\n|\r|\n){3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function looksLikeVisualOrderArabic(string $text): bool
    {
        preg_match_all('/[\p{Arabic}\x{064B}-\x{065F}\x{0670}]+/u', $text, $matches);

        $words = array_values(array_filter($matches[0], static fn (string $word): bool => mb_strlen($word) > 1));

        if (count($words) < 3) {
            return false;
        }

        $visualOrderSignals = 0;

        foreach ($words as $word) {
            if (preg_match('/^ة/u', $word)) {
                $visualOrderSignals++;
            }

            if (preg_match('/(لا|لاو|لاب|لاف|لاك)$/u', $word)) {
                $visualOrderSignals++;
            }

            if (in_array($word, ['يف', 'نم', 'ىلع', 'ىلإ'], true)) {
                $visualOrderSignals++;
            }
        }

        return ($visualOrderSignals / count($words)) >= 0.2;
    }

    private function repairArabicVisualOrder(string $text): string
    {
        return preg_replace_callback(
            '/[\p{Arabic}\x{064B}-\x{065F}\x{0670}]+/u',
            fn (array $matches): string => $this->fixArabicLamAlefArtifacts($this->reverseUtf8Codepoints($matches[0])),
            $text,
        ) ?? $text;
    }

    private function fixArabicLamAlefArtifacts(string $text): string
    {
        return str_replace(
            ['اإل', 'األ', 'اآل', 'السالم'],
            ['الإ', 'الأ', 'الآ', 'السلام'],
            $text,
        );
    }

    private function reverseUtf8Codepoints(string $text): string
    {
        preg_match_all('/./us', $text, $characters);

        return implode('', array_reverse($characters[0]));
    }
}