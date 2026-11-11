<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-semester document requirements that drive the submission tracker.
 * Read by the InitializeAcademicPeriod stored procedure.
 */
class DocumentRequirement extends Model
{
    protected $table = 'document_requirements';

    protected $fillable = [
        'academic_year',
        'semester',
        'department_id',
        'document_type',
        'is_required',
        'deadline_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'academic_year' => 'integer',
            'is_required' => 'boolean',
            'deadline_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', 1);
    }
}
