<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps the legacy `folders` table (Department -> Category -> Semester hierarchy).
 * The legacy app filters soft-deletes with an explicit `is_deleted = 0` check rather
 * than Eloquent's SoftDeletes trait, so we mirror that behaviour explicitly here.
 */
class Folder extends Model
{
    protected $table = 'folders';

    protected $fillable = [
        'folder_name',
        'description',
        'parent_id',
        'department_id',
        'folder_path',
        'folder_level',
        'is_public',
        'folder_color',
        'folder_icon',
        'category',
        'folder_type',
        'folder_status',
        'is_favorite',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_deleted' => 'boolean',
            'is_favorite' => 'boolean',
            'folder_level' => 'integer',
            'folder_size' => 'integer',
            'file_count' => 'integer',
            'access_count' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(FolderPermission::class);
    }

    /** Legacy default: WHERE is_deleted = 0 */
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', 0);
    }
}
