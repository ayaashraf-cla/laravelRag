<?php

namespace AyaAshraf\LaravelRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use AyaAshraf\LaravelRag\Enums\DocumentStatus;

class Document extends Model
{
     protected $fillable = [
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'status',
        'extracted_text',
        'error',
        'processed_at',
    ];
    public function getConnectionName(): string
    {
        return config('rag.documents_connection', 'mysql');
    }

    // public const STATUS_QUEUED = 'queued';

    // public const STATUS_PROCESSING = 'processing';

    // public const STATUS_PROCESSED = 'processed';

    // public const STATUS_EMPTY = 'empty';

    // public const STATUS_FAILED = 'failed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}
