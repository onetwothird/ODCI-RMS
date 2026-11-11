<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Folder access grants used by the legacy canAccess('folder', ...) logic.
 * permission_type is an enum: read < write < admin.
 */
class FolderPermission extends Model
{
    protected $table = 'folder_permissions';

    public $timestamps = false;

    /** Ordering used by canAccess() to compare permission levels. */
    public const LEVELS = [
        'read' => 1,
        'write' => 2,
        'admin' => 3,
    ];

    protected $fillable = [
        'folder_id',
        'user_id',
        'department_id',
        'role',
        'permission_type',
        'granted_by',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** Active grants that have not expired (legacy WHERE clause). */
    public function scopeCurrentlyValid($query)
    {
        return $query->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function grants(string $requiredPermission): bool
    {
        $granted = self::LEVELS[$this->permission_type] ?? 0;
        $required = self::LEVELS[$requiredPermission] ?? 1;

        return $granted >= $required;
    }
}
