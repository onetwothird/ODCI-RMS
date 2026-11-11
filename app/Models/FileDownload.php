<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Download audit trail. Legacy table has no updated_at column.
 */
class FileDownload extends Model
{
    protected $table = 'file_downloads';

    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'user_id',
        'downloaded_at',
        'user_ip',
    ];

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
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
}
