<?php

namespace AyaAshraf\LaravelRag\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use AyaAshraf\LaravelRag\Enums\DocumentStatus;

class Document extends Model
{
    protected $fillable = [
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'documentable_id',
        'documentable_type',
        'uploaded_by',
        'is_visible',
        'status',
        'extracted_text',
        'error',
        'processed_at',
    ];

    public function getConnectionName(): string
    {
        return config('rag.documents_connection', 'mysql');
    }

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'status'       => DocumentStatus::class,
            'is_visible'   => 'boolean',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeHidden(Builder $query): Builder
    {
        return $query->where('is_visible', false);
    }
}
