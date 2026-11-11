<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Port of roles/superadmin/users.php + assets/script/users-script.php
 *
 * Access: super_admin only, matching requireSuperAdmin().
 */
class UserController extends Controller
{
    private const SYSTEM_USER_ID_KEY = 'odci.system_user_id';

    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'filter' => (string) $request->query('filter', 'all'),
            'department' => (string) $request->query('department', ''),
            'role' => (string) $request->query('role', ''),
        ];

        $perPage = (int) config('odci.per_page.users', 20);

        $users = $this->applyFilters(User::query()->with(['department', 'approver']), $filters)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('superadmin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'departments' => $this->activeDepartments(),
            'counts' => $this->counts(),
        ]);
    }

    // -----------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $user = new User();
        $user->forceFill([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],           // 'hashed' cast handles bcrypt
            'name' => $data['name'],
            'mi' => $data['mi'] ?? null,
            'surname' => $data['surname'],
            'employee_id' => $data['employee_id'] ?? null,
            'position' => $data['position'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,

            // Legacy INSERT set these explicitly:
            //   is_approved = 1, email_verified = 1,
            //   created_by = actor, approved_by = actor, approved_at = NOW()
            'is_approved' => 1,
            'email_verified' => 1,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        $this->activity->logRequest(
            userId: $actor->id,
            action: 'create_user',
            resourceType: 'user',
            resourceId: $user->id,
            description: "Created user: {$user->name} {$user->surname}",
            metadata: ['role' => $user->role, 'department_id' => $user->department_id],
        );

        return back()->with('success', 'User created successfully!');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        // Note: `role`, `is_approved` and `is_restricted` are deliberately
        // applied with forceFill() because they are not mass-assignable.
        $user->forceFill([
            'name' => $data['name'],
            'mi' => $data['mi'] ?? null,
            'surname' => $data['surname'],
            'employee_id' => $data['employee_id'] ?? null,
            'position' => $data['position'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'is_approved' => $data['is_approved'] ?? false,
            'is_restricted' => $data['is_restricted'] ?? false,
        ])->save();

        $this->activity->logRequest(
            userId: $actor->id,
            action: 'update_user',
            resourceType: 'user',
            resourceId: $user->id,
            description: "Updated user: {$user->name} {$user->surname}",
        );

        return back()->with('success', 'User updated successfully!');
    }

    /**
     * Return user data for the edit modal.
     */
    public function edit(User $user): JsonResponse
    {
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mi' => $user->mi,
                'surname' => $user->surname,
                'employee_id' => $user->employee_id,
                'position' => $user->position,
                'department_id' => $user->department_id,
                'role' => $user->role,
                'phone' => $user->phone,
                'address' => $user->address,
                'is_approved' => (bool) $user->is_approved,
                'is_restricted' => (bool) $user->is_restricted,
            ],
        ]);
    }

    /**
     * Legacy guard: "Cannot delete super admin account."
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Cannot delete super admin account.');
        }

        $actor = $request->user();
        $fullName = $user->full_name;

        // Legacy ran this in an explicit transaction:
        //   UPDATE files  SET uploaded_by = 1 WHERE uploaded_by = ?
        //   UPDATE folders SET created_by  = 1 WHERE created_by  = ?
        //   DELETE FROM users WHERE id = ?
        DB::transaction(function () use ($user, $actor, $fullName) {
            $systemUserId = (int) config(self::SYSTEM_USER_ID_KEY);

            $user->uploadedFiles()->update(['uploaded_by' => $systemUserId]);
            $user->createdFolders()->update(['created_by' => $systemUserId]);

            $user->delete();
        });

        $this->activity->logRequest(
            userId: $actor->id,
            action: 'delete_user',
            resourceType: 'user',
            resourceId: $user->id,
            description: "Deleted user: {$fullName}",
        );

        return back()->with('success', 'User deleted successfully!');
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        $user->forceFill([
            'is_approved' => 1,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        $this->activity->logRequest(
            userId: $actor->id,
            action: 'approve_user',
            resourceType: 'user',
            resourceId: $user->id,
            description: "Approved user: {$user->full_name}",
        );

        return back()->with('success', 'User approved successfully!');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $this->activity->logRequest(
            userId: $request->user()->id,
            action: 'reset_password',
            resourceType: 'user',
            resourceId: $user->id,
            description: "Reset password for user: {$user->full_name}",
        );

        return back()->with('success', 'Password reset successfully!');
    }

    // -----------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------

    /**
     * Same WHERE clauses as the legacy query builder.
     */
    protected function applyFilters($query, array $filters)
    {
        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('surname', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('employee_id', 'like', $term);
            });
        }

        match ($filters['filter']) {
            'pending' => $query->where('is_approved', 0),
            'approved' => $query->where('is_approved', 1),
            'restricted' => $query->where('is_restricted', 1),
            default => null,
        };

        if ($filters['department'] !== '') {
            $query->where('department_id', (int) $filters['department']);
        }

        if ($filters['role'] !== '') {
            $query->where('role', $filters['role']);
        }

        return $query;
    }

    protected function counts(): array
    {
        return [
            'all' => User::query()->count(),
            'pending' => User::query()->where('is_approved', 0)->count(),
            'approved' => User::query()->where('is_approved', 1)->count(),
            'restricted' => User::query()->where('is_restricted', 1)->count(),
        ];
    }

    protected function activeDepartments()
    {
        return Department::query()
            ->where('is_active', 1)
            ->orderBy('department_name')
            ->get();
    }
}