<?php

namespace App\Models;

use App\Services\LegacyFileResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_USER = 'user';

    protected $table = 'users';

    /**
     * Only attributes that are safe to mass-assign from request input.
     * Role / approval / lockout columns are intentionally excluded so they
     * cannot be tampered with via mass assignment.
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'mi',
        'surname',
        'employee_id',
        'position',
        'department_id',
        'phone',
        'address',
        'date_of_birth',
        'hire_date',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'password_reset_token',
        'email_verification_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified' => 'boolean',
            'is_approved' => 'boolean',
            'is_restricted' => 'boolean',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'last_login' => 'datetime',
            'account_locked_until' => 'datetime',
            'password_reset_expires' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Mirrors the legacy session value: name [MI.] surname
     */
    public function getFullNameAttribute(): string
    {
        $middle = $this->mi ? $this->mi.'. ' : '';

        return trim($this->name.' '.$middle.$this->surname);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isFaculty(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_approved;
    }

    public function isLocked(): bool
    {
        return $this->account_locked_until !== null
            && now()->lt($this->account_locked_until);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** Users approved by this account. */
    public function approvedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'approved_by');
    }

    /** The account that approved this user, if any. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /** Files this account uploaded. Reassigned to user 1 when it is deleted. */
    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(File::class, 'uploaded_by');
    }

    /** Folders this account created. Reassigned to user 1 when it is deleted. */
    public function createdFolders(): HasMany
    {
        return $this->hasMany(Folder::class, 'created_by');
    }

    /** Document submissions this faculty member has made. */
    public function submissions(): HasMany
    {
        return $this->hasMany(FacultyDocumentSubmission::class, 'faculty_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    /**
     * URL for the stored profile image, or null when the file cannot be found.
     *
     * The legacy users.php resolved this with a getProfileImagePath() helper
     * that probed relative paths and fell back to a null so the template could
     * render initials instead. LegacyFileResolver reproduces the same candidate
     * order against the real uploads directory.
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        if (blank($this->profile_image)) {
            return null;
        }

        // New uploads live under storage/app/private/uploads/... and are served
        // through the profile.image route.
        $clean = ltrim((string) preg_replace('#^(\.\./)+#', '', $this->profile_image), '/\\');
        $stored = storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $clean));

        if (is_file($stored)) {
            return route('profile.image', ['path' => $clean]);
        }

        return app(LegacyFileResolver::class)->url($this->profile_image, 'profiles');
    }

    /**
     * First letter of the first name + first letter of the surname, used for
     * the fallback avatar. Mirrors the legacy $initials calculation.
     */
    public function getInitialsAttribute(): string
    {
        return strtoupper(
            mb_substr((string) $this->name, 0, 1).mb_substr((string) $this->surname, 0, 1)
        );
    }
}
