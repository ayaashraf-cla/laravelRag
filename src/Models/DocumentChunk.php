<?php

namespace AyaAshraf\LaravelRag\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Vector;
use Illuminate\Database\Eloquent\Attributes\Fillable;


class DocumentChunk extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $fillable = [
        'document_id',
        'position',
        'content',
        'content_hash',
        'embedding',
        'embedding_model',
        'embedding_dimensions',
        'embedded_at',
        'metadata',
    ];
    public function getConnectionName(): string
    {
        return config('rag.vector_connection', 'pgsql_vector');
    }

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'embedded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Perform semantic vector search against the stored embeddings.
     *
     * @param  string|array<int, float>  $query
     * @return Collection<int, self>
     */
    public static function semanticSearch(string|array $query, int $limit = 10, float $minSimilarity = 0.4)
    {
        $builder = static::query();

        if (is_array($query)) {
            $builder->where('embedding_dimensions', count($query));
        }

        return $builder
            ->select(['id', 'document_id', 'position', 'content', 'metadata'])
            ->selectVectorDistance('embedding', $query, 'embedding_distance')
            ->whereVectorSimilarTo('embedding', $query, minSimilarity: $minSimilarity)
            ->limit($limit)
            ->get();
    }
}
