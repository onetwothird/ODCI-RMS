<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\LegacyFileResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Port of roles/user/colleagues.php.
 *
 * Lists every approved account except the signed-in user, with the same
 * department / search / role filters and the same derived fields (full_name,
 * profile_image_url, initials) the legacy page computed inline.
 */
class ColleaguesController extends Controller
{
    public function __construct(private readonly LegacyFileResolver $files) {}

    /**
     * GET /user/colleagues
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Get filters
        $departmentFilter = $request->query('department', '');
        $search = $request->query('search', '');
        $roleFilter = $request->query('role', '');

        // Get all departments for filter
        $departments = DB::select('SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name');

        // Get colleagues with filters
        $query = "
            SELECT u.*, d.department_name, d.department_code
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.is_approved = 1 AND u.id != ?
        ";

        $params = [$user->id];

        if ($departmentFilter) {
            $query .= ' AND u.department_id = ?';
            $params[] = $departmentFilter;
        }

        if ($search) {
            $query .= ' AND (u.name LIKE ? OR u.surname LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.position LIKE ?)';
            for ($i = 0; $i < 5; $i++) {
                $params[] = "%$search%";
            }
        }

        if ($roleFilter) {
            $query .= ' AND u.role = ?';
            $params[] = $roleFilter;
        }

        $query .= ' ORDER BY u.surname, u.name';

        $colleagues = array_map(
            fn ($row) => (array) $row,
            DB::select($query, $params)
        );

        foreach ($colleagues as &$colleague) {
            $colleague['full_name'] = trim(
                $colleague['name'].' '.
                (! empty($colleague['mi']) ? $colleague['mi'].'. ' : '').
                $colleague['surname']
            );

            $colleague['profile_image_url'] = $this->profileImageUrl($colleague['profile_image'] ?? '');

            $nameParts = preg_split('/\s+/', $colleague['full_name'], -1, PREG_SPLIT_NO_EMPTY);
            $colleague['initials'] = strtoupper(
                mb_substr($nameParts[0] ?? '?', 0, 1).
                (count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : '')
            );
        }
        unset($colleague);

        // Get user's department colleagues count
        $deptColleaguesCount = (int) DB::selectOne("
            SELECT COUNT(*) as count
            FROM users
            WHERE department_id = (SELECT department_id FROM users WHERE id = ?)
            AND is_approved = 1 AND id != ?
        ", [$user->id, $user->id])->count;

        // Get total colleagues count
        $totalColleagues = (int) DB::selectOne(
            'SELECT COUNT(*) as count FROM users WHERE is_approved = 1 AND id != ?',
            [$user->id]
        )->count;

        // Legacy: "Active This Week" card counts the currently filtered list.
        $activeThisWeek = count(array_filter(
            $colleagues,
            fn ($c) => $c['last_login'] && strtotime($c['last_login']) > strtotime('-7 days')
        ));

        return view('user.colleagues', [
            'departments' => $departments,
            'colleagues' => $colleagues,
            'departmentFilter' => $departmentFilter,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'deptColleaguesCount' => $deptColleaguesCount,
            'totalColleagues' => $totalColleagues,
            'activeThisWeek' => $activeThisWeek,
        ]);
    }

    /**
     * Resolve a stored users.profile_image to a URL, or '' when the file is
     * missing (the legacy page rendered the initials avatar in that case).
     */
    private function profileImageUrl(?string $stored): string
    {
        $absolute = $this->files->resolve((string) $stored, 'profiles');

        if ($absolute === null) {
            return '';
        }

        return route('legacy.uploads', ['path' => $this->files->relativePath($absolute)])
            .'?v='.filemtime($absolute);
    }
}