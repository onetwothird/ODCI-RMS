<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Port of roles/superadmin/departments.php + assets/script/departments-script.php
 *
 * Access: super_admin only, matching requireSuperAdmin().
 */
class DepartmentController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $perPage = (int) config('odci.per_page.departments', 12);

        $departments = $this->applyFilters(Department::query(), $filters)
            // user_count / folder_count / file_count / total_size reproduce the
            // legacy aggregate query, including its `u.is_approved = 1` join.
            ->leftJoin('users', 'users.department_id', '=', 'departments.id')
            ->leftJoin('folders', function ($join) {
                $join->on('folders.department_id', '=', 'departments.id')
                    ->where('folders.is_deleted', 0);
            })
            ->leftJoin('files', function ($join) {
                $join->on('files.folder_id', '=', 'folders.id')
                    ->where('files.is_deleted', 0);
            })
            ->groupBy(
                'departments.id',
                'departments.department_code',
                'departments.department_name',
                'departments.description',
                'departments.head_of_department',
                'departments.contact_email',
                'departments.contact_phone',
                'departments.is_active',
                'departments.created_at',
                'departments.updated_at',
            )
            ->select('departments.*')
            ->selectRaw('COUNT(DISTINCT users.id) as user_count')
            ->selectRaw('COUNT(DISTINCT folders.id) as folder_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN files.is_deleted = 0 THEN 1 ELSE 0 END), 0) as file_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN files.is_deleted = 0 THEN files.file_size ELSE 0 END), 0) as total_size')
            ->orderByDesc('departments.created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('superadmin.departments.index', [
            'departments' => $departments,
            'filters' => $filters,
            'counts' => [
                'all' => Department::query()->count(),
                'active' => Department::query()->where('is_active', 1)->count(),
                'inactive' => Department::query()->where('is_active', 0)->count(),
            ],
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::query()->create($request->validated());

        $this->activity->logRequest(
            userId: $request->user()->id,
            action: 'create_department',
            resourceType: 'department',
            resourceId: $department->id,
            description: "Created department: {$department->department_name} ({$department->department_code})",
        );

        return back()->with('success', 'Department created successfully!');
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $data = $request->validated();

        $department->fill([
            'department_code' => $data['department_code'],
            'department_name' => $data['department_name'],
            'description' => $data['description'] ?? null,
            'head_of_department' => $data['head_of_department'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
        ]);

        // is_active is intentionally not mass-assignable (see Department model).
        $department->is_active = $data['is_active'] ?? false;
        $department->save();

        $this->activity->logRequest(
            userId: $request->user()->id,
            action: 'update_department',
            resourceType: 'department',
            resourceId: $department->id,
            description: "Updated department: {$department->department_name} ({$department->department_code})",
        );

        return back()->with('success', 'Department updated successfully!');
    }

    /**
     * Legacy guards, in the same order:
     *   1. department must exist
     *   2. must have no users      -> "Cannot delete department. It has N user(s) assigned to it."
     *   3. must have no folders    -> "Cannot delete department. It has N folder(s) assigned to it."
     */
    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $userCount = $department->users()->count();

        if ($userCount > 0) {
            return back()->with(
                'error',
                "Cannot delete department. It has {$userCount} user(s) assigned to it."
            );
        }

        $folderCount = Department::query()
            ->whereKey($department->id)
            ->join('folders', function ($join) {
                $join->on('folders.department_id', '=', 'departments.id')
                    ->where('folders.is_deleted', 0);
            })
            ->count();

        if ($folderCount > 0) {
            return back()->with(
                'error',
                "Cannot delete department. It has {$folderCount} folder(s) assigned to it."
            );
        }

        $name = $department->department_name;
        $code = $department->department_code;

        $department->delete();

        $this->activity->logRequest(
            userId: $request->user()->id,
            action: 'delete_department',
            resourceType: 'department',
            resourceId: $department->id,
            description: "Deleted department: {$name} ({$code})",
        );

        return back()->with('success', 'Department deleted successfully!');
    }

    /**
     * Return department data for the edit modal.
     */
    public function edit(Department $department): JsonResponse
    {
        return response()->json([
            'department' => [
                'id' => $department->id,
                'department_code' => $department->department_code,
                'department_name' => $department->department_name,
                'description' => $department->description,
                'head_of_department' => $department->head_of_department,
                'contact_email' => $department->contact_email,
                'contact_phone' => $department->contact_phone,
                'is_active' => (bool) $department->is_active,
            ],
        ]);
    }

    /**
     * POST ?action=toggle_status
     */
    public function toggleStatus(Request $request, Department $department): RedirectResponse
    {
        $newStatus = $department->is_active ? 0 : 1;

        $department->is_active = $newStatus;
        $department->save();

        $statusText = $newStatus ? 'activated' : 'deactivated';

        $this->activity->logRequest(
            userId: $request->user()->id,
            action: 'toggle_department_status',
            resourceType: 'department',
            resourceId: $department->id,
            description: "Department {$department->department_name} {$statusText}",
        );

        return back()->with('success', "Department {$statusText} successfully!");
    }

    protected function applyFilters($query, array $filters)
    {
        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';

            $query->where(function ($q) use ($term) {
                $q->where('department_name', 'like', $term)
                    ->orWhere('department_code', 'like', $term)
                    ->orWhere('head_of_department', 'like', $term);
            });
        }

        match ($filters['status']) {
            'active' => $query->where('is_active', 1),
            'inactive' => $query->where('is_active', 0),
            default => null,
        };

        return $query;
    }
}