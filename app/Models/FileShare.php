<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileShare extends Model
{
    protected $table = 'file_shares';

    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'shared_by',
        'shared_with',
        'share_token',
        'share_type',
        'permissions',
        'expires_at',
        'password_protected',
        'share_password',
        'download_limit',
        'is_active',
    ];

    protected $hidden = [
        'share_password',
    ];

    protected function casts(): array
    {
        return [
            'password_protected' => 'boolean',
            'is_active' => 'boolean',
            'download_count' => 'integer',
            'download_limit' => 'integer',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_accessed' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with');
    }
}
