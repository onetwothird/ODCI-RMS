<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy `post_media` has created_at only (no updated_at).
 * Media files live under social_feed/uploads/ and are referenced by relative path.
 */
class PostMedia extends Model
{
    protected $table = 'post_media';

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'media_type',
        'file_path',
        'file_name',
        'original_name',
        'file_size',
        'mime_type',
        'url',
        'url_title',
        'url_description',
        'url_image',
        'thumbnail_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
