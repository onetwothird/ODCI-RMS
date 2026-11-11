<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Social feed posts. `visibility` drives department-scoped visibility in the legacy
 * PostManager / get_posts.php endpoints.
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'user_id',
        'content',
        'content_type',
        'visibility',
        'target_departments',
        'target_users',
        'priority',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_edited' => 'boolean',
            'is_deleted' => 'boolean',
            'target_departments' => 'array',
            'target_users' => 'array',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'view_count' => 'integer',
            'share_count' => 'integer',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('sort_order');
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * Legacy visibility rules: a user sees their own posts, public posts,
     * department-scoped posts for their own department, and targeted posts.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('visibility', 'public');

            if ($user->department_id !== null) {
                $q->orWhere(function (Builder $sub) use ($user) {
                    $sub->where('visibility', 'department')
                        ->whereRaw('JSON_CONTAINS(target_departments, ?, \'$\')', [json_encode($user->department_id)]);
                });
            }
        });
    }
}
