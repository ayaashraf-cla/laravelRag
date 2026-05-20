<?php

namespace AyaAshraf\LaravelRag\Traits;

use AyaAshraf\LaravelRag\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function visibleDocuments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')
            ->where('is_visible', true);
    }

    public function hiddenDocuments(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')
            ->where('is_visible', false);
    }
}
