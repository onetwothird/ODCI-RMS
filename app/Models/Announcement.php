<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'content',
        'summary',
        'image_path',
        'priority',
        'announcement_type',
        'target_departments',
        'target_roles',
        'published_at',
        'expires_at',
        'created_by',
        'is_pinned',
        'allow_comments',
        'send_email',
    ];

    protected function casts(): array
    {
        return [
            'target_departments' => 'array',
            'target_roles' => 'array',
            'is_published' => 'boolean',
            'is_deleted' => 'boolean',
            'is_pinned' => 'boolean',
            'allow_comments' => 'boolean',
            'send_email' => 'boolean',
            'email_sent' => 'boolean',
            'view_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', 1)
            ->where('is_deleted', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * Legacy canAccess('announcement', ...) targeting rules.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->published()->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id);

            if ($user->department_id !== null) {
                $q->orWhereRaw('JSON_CONTAINS(target_departments, ?, \'$\')', [json_encode($user->department_id)]);
            }

            $q->orWhereRaw('JSON_CONTAINS(target_roles, ?, \'$\')', [json_encode($user->role)])
                ->orWhere(function (Builder $sub) {
                    $sub->where(fn ($a) => $a->whereNull('target_departments')->orWhere('target_departments', ''))
                        ->where(fn ($a) => $a->whereNull('target_roles')->orWhere('target_roles', ''));
                });
        });
    }
}
