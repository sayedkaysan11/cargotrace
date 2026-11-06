<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExternalNewsArticle extends Model
{
    protected $fillable = [
        'title',
        'summary',
        'source',
        'category',
        'article_url',
        'image_url',
        'url_hash',
        'title_hash',
        'published_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function image(): ?string
    {
        return $this->image_url;
    }
}
