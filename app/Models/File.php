<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maps the legacy `files` table. Soft deletes are done via `is_deleted` /
 * `deleted_at` columns which the legacy code filters explicitly.
 */
class File extends Model
{
    protected $table = 'files';

    // The legacy table has `uploaded_at` / `updated_at`, not `created_at`.
    public const CREATED_AT = 'uploaded_at';

    protected $fillable = [
        'file_name',
        'original_name',
        'file_path',
        'file_size',
        'file_type',
        'mime_type',
        'file_extension',
        'uploaded_by',
        'folder_id',
        'file_hash',
        'is_public',
        'public_token',
        'version',
        'parent_file_id',
        'tags',
        'description',
        'academic_year',
        'semester',
        'thumbnail_path',
        'is_favorite',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_deleted' => 'boolean',
            'is_favorite' => 'boolean',
            'file_size' => 'integer',
            'download_count' => 'integer',
            'version' => 'integer',
            'uploaded_at' => 'datetime',
            'deleted_at' => 'datetime',
            'last_downloaded' => 'datetime',
            'expiry_date' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(FileDownload::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FileComment::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(FileShare::class);
    }

    /** Legacy default: WHERE is_deleted = 0 */
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', 0);
    }

    public function scopeVisibleTo($query, User $user)
    {
        // Legacy canAccess('file', ...) rules:
        //   super_admin -> everything
        //   uploader    -> everything
        //   is_public   -> read access
        //   otherwise   -> defer to folder permissions
        return $query->where(function ($q) use ($user) {
            $q->where('uploaded_by', $user->id)
                ->orWhere('is_public', 1);

            if ($user->isSuperAdmin()) {
                $q->orWhereRaw('1 = 1');
            }
        });
    }
}
