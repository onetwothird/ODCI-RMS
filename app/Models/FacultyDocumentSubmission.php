<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy `faculty_document_submissions` has NO created_at/updated_at columns.
 * Populated by the InitializeAcademicPeriod stored procedure.
 */
class FacultyDocumentSubmission extends Model
{
    protected $table = 'faculty_document_submissions';

    public $timestamps = false;

    protected $fillable = [
        'faculty_id',
        'document_type',
        'semester',
        'academic_year',
        'submitted_by',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'academic_year' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faculty_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
