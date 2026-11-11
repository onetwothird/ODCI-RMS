<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileComment extends Model
{
    protected $table = 'file_comments';

    protected $fillable = [
        'file_id',
        'user_id',
        'comment',
        'parent_comment_id',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', 0);
    }
}
