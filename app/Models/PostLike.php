<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy `post_likes` has created_at only (no updated_at).
 * reaction_type enum: like, love, laugh, angry, sad, wow
 */
class PostLike extends Model
{
    protected $table = 'post_likes';

    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'comment_id',
        'user_id',
        'reaction_type',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
