<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Files & Folders module.
 *
 * Legacy equivalents:
 *   roles/admin/files.php          -> GET /admin/files      (document_files)
 *   roles/admin/folders.php        -> GET /admin/folders    (folders table)
 *   roles/admin/shared.php         -> GET /admin/shared     (file_shares)
 *   roles/superadmin/files.php     -> GET /superadmin/files
 *   roles/superadmin/folders.php   -> GET /superadmin/folders
 *   roles/user/folders.php         -> GET /user/folders     (department folders)
 *
 * IMPORTANT: DatabaseTransactions (NOT RefreshDatabase) is used deliberately.
 * These tests run against the real legacy `odci_db` schema and must never
 * create, drop, or migrate anything -- every change is rolled back.
 * All fixture usernames are prefixed with `ff_` so they cannot collide with
 * the unique username / email indexes of real accounts.
 *
 * Uploads write a real row into `files` and a physical file under
 * storage/app/private/uploads/departments/... ; the physical files are
 * removed in tearDown() (the DB rows are rolled back automatically).
 */
class FolderFilesModuleTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private ?int $deptId = null;

    /** Physical files created by fixture uploads (cleaned in tearDown). */
    private array $createdUploads = [];

    /** Rotates through document types to stay clear of unique_submission. */
    private const DOC_TYPES = ['Exam', 'Syllabus', 'Module', 'Accomplishment', 'Curriculum', 'Memorandum'];

    private int $docTypeSeq = 0;

    protected function tearDown(): void
    {
        foreach ($this->createdUploads as $absolutePath) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            $dir = dirname($absolutePath);
            while (str_contains(dirname($dir), 'uploads')) {
                @rmdir($dir);
                $dir = dirname($dir);
                if ($dir === dirname($dir)) {
                    break;
                }
            }
        }

        parent::tearDown();
    }

    private function departmentId(): int
    {
        if ($this->deptId === null) {
            $this->deptId = (int) DB::table('departments')->where('is_active', 1)->value('id');
        }

        return $this->deptId;
    }

    private function otherDepartmentId(): int
    {
        return (int) DB::table('departments')
            ->where('is_active', 1)
            ->where('id', '!=', $this->departmentId())
            ->value('id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(string $role, array $overrides = []): User
    {
        $user = (new User())->forceFill(array_merge([
            'username' => 'ff_'.$this->faker->unique()->numerify('####'),
            'email' => 'ff_'.$this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Files',
            'mi' => 'F',
            'surname' => 'Fixture',
            'employee_id' => $this->faker->unique()->numerify('FIL-#####'),
            'position' => 'Instructor',
            'department_id' => $this->departmentId(),
        ], $overrides));

        $user->save();

        return $user;
    }

    private function makeAddress(int $userId): string
    {
        return 'uploads/documents/'.$this->faker->unique()->numerify('####').'/'.bin2hex(random_bytes(6)).'.pdf';
    }

    /**
     * Insert a document_files row (needs a valid faculty_document_submissions id).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeDocumentFile(int $uploadedBy, array $overrides = []): int
    {
        $year = $overrides['academic_year'] ?? 2025;
        $semester = $overrides['semester_period'] ?? '1st Semester';
        $docType = $overrides['document_type'] ?? self::DOC_TYPES[$this->docTypeSeq++ % count(self::DOC_TYPES)];

        $submissionId = DB::table('faculty_document_submissions')->insertGetId([
            'faculty_id' => $uploadedBy,
            'document_type' => $docType,
            'semester' => $semester,
            'academic_year' => $year,
            'submitted_by' => $uploadedBy,
            'submitted_at' => now(),
        ]);

        return (int) DB::table('document_files')->insertGetId(array_merge([
            'submission_id' => $submissionId,
            'file_name' => 'report-'.$this->faker->unique()->numerify('###').'.pdf',
            'file_path' => $this->makeAddress($uploadedBy),
            'file_size' => 2048,
            'file_type' => $docType,
            'academic_year' => $year,
            'semester_period' => $semester,
            'uploaded_by' => $uploadedBy,
            'description' => null,
            'uploaded_at' => now(),
            'mime_type' => 'application/pdf',
            'download_count' => 0,
        ], $overrides));
    }

    /**
     * Insert a workspace file row (the `files` table). file_shares.file_id has a
     * FK to files(id), so shared fixtures must use this helper.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeWorkspaceFile(int $uploadedBy, array $overrides = []): int
    {
        $semester = $overrides['semester'] ?? 'first';

        return (int) DB::table('files')->insertGetId(array_merge([
            'file_name' => 'stored-'.$this->faker->unique()->numerify('####').'.pdf',
            'original_name' => $this->faker->unique()->words(2, true).'.pdf',
            'file_path' => $this->makeAddress($uploadedBy),
            'file_size' => 512,
            'file_type' => 'Report',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'uploaded_by' => $uploadedBy,
            'folder_id' => $this->makeFolder($uploadedBy),
            'uploaded_at' => now(),
            'academic_year' => '2025-2026',
            'semester' => $semester,
            'description' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeFolder(int $createdBy, array $overrides = []): int
    {
        return (int) DB::table('folders')->insertGetId(array_merge([
            'folder_name' => $this->faker->unique()->words(2, true),
            'description' => null,
            'created_by' => $createdBy,
            'department_id' => $this->departmentId(),
            'folder_path' => '/departments/'.$this->departmentId().'/testing',
            'folder_level' => 1,
            'folder_type' => 'custom',
            'folder_status' => 'active',
            'folder_color' => '#14532d',
            'folder_icon' => 'fa-folder',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function assertFilePagesSkeleton($response): void
    {
        $response->assertOk();
        $response->assertSee('css/tailwind.css', false);
    }

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guests_are_redirected_to_login(): void
    {
        foreach ([
            '/admin/files', '/admin/folders', '/admin/shared',
            '/superadmin/files', '/superadmin/folders', '/user/folders',
        ] as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->get('/folders/category-files')->assertRedirect('/login');
        $this->get('/folders/custom/contents')->assertRedirect('/login');
        $this->post('/files/upload')->assertRedirect('/login');
    }

    public function test_pages_are_gated_by_role(): void
    {
        $faculty = $this->makeUser('user');

        $this->actingAs($faculty)->get('/admin/files')->assertRedirect('/user/dashboard');
        $this->actingAs($faculty)->get('/admin/folders')->assertRedirect('/user/dashboard');
        $this->actingAs($faculty)->get('/admin/shared')->assertRedirect('/user/dashboard');
        $this->actingAs($faculty)->get('/superadmin/files')->assertRedirect('/user/dashboard');
        $this->actingAs($faculty)->get('/superadmin/folders')->assertRedirect('/user/dashboard');

        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/superadmin/files')->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->get('/superadmin/folders')->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->get('/user/folders')->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->get('/folders/category-files')->assertRedirect('/admin/dashboard');
    }

    // -----------------------------------------------------------------
    // All Files (admin / superadmin) - document_files
    // -----------------------------------------------------------------

    public function test_admin_files_page_is_scoped_to_own_department_and_renders_tables(): void
    {
        $admin = $this->makeUser('admin');
        $ownFaculty = $this->makeUser('user');
        $otherFaculty = $this->makeUser('user', ['department_id' => $this->otherDepartmentId()]);

        $ownFile = $this->makeDocumentFile($ownFaculty->id, ['file_name' => 'own-dept-syllabus.pdf']);
        $this->makeDocumentFile($otherFaculty->id, ['file_name' => 'other-dept-secret.pdf']);

        $response = $this->actingAs($admin)->get('/admin/files');

        $this->assertFilePagesSkeleton($response);
        $response->assertSee('All Files');
        $response->assertSee('own-dept-syllabus.pdf');
        $response->assertDontSee('other-dept-secret.pdf');
        $this->assertNotEmpty(DB::table('document_files')->where('id', $ownFile)->first());
    }

    public function test_superadmin_files_page_lists_every_department(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $faculty = $this->makeUser('user', ['department_id' => $this->otherDepartmentId()]);

        $this->makeDocumentFile($faculty->id, ['file_name' => 'cross-dept-file.pdf']);

        $response = $this->actingAs($superAdmin)->get('/superadmin/files');

        $this->assertFilePagesSkeleton($response);
        $response->assertSee('cross-dept-file.pdf');
        $response->assertSee('Uploaded Today');
    }

    public function test_files_page_respects_search_filter(): void
    {
        $admin = $this->makeUser('admin');
        $faculty = $this->makeUser('user');

        $this->makeDocumentFile($faculty->id, ['file_name' => 'needle-document.pdf']);
        $this->makeDocumentFile($faculty->id, ['file_name' => 'haystack-file.pdf']);

        $response = $this->actingAs($admin)->get('/admin/files?search=needle');

        $response->assertOk();
        $response->assertSee('needle-document.pdf');
        $response->assertDontSee('haystack-file.pdf');
    }

    // -----------------------------------------------------------------
    // All Folders (admin / superadmin) - folders table
    // -----------------------------------------------------------------

    public function test_admin_folders_page_renders_summary_and_listed_folder(): void
    {
        $admin = $this->makeUser('admin');
        $folderId = $this->makeFolder($admin->id, ['folder_name' => 'Ratings Fixture Folder']);

        $response = $this->actingAs($admin)->get('/admin/folders');

        $this->assertFilePagesSkeleton($response);
        $response->assertSee('All Folders');
        $response->assertSee('Ratings Fixture Folder');
        $response->assertSee('Total Folders');
        $response->assertSee('Public Folders');
        $this->assertNotEmpty(DB::table('folders')->where('id', $folderId)->first());
    }

    public function test_superadmin_folders_page_renders_all_departments(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $this->makeFolder($superAdmin->id, [
            'folder_name' => 'Global Fixture Folder',
            'department_id' => $this->otherDepartmentId(),
        ]);

        $response = $this->actingAs($superAdmin)->get('/superadmin/folders');

        $this->assertFilePagesSkeleton($response);
        $response->assertSee('Global Fixture Folder');
    }

    public function test_folder_action_toggle_public_updates_visibility(): void
    {
        $admin = $this->makeUser('admin');
        $folderId = $this->makeFolder($admin->id, ['is_public' => 0]);

        $this->actingAs($admin)->post('/folders/action', [
            'action' => 'toggle_public',
            'folder_id' => $folderId,
        ])->assertRedirect();

        $this->assertSame(1, (int) DB::table('folders')->where('id', $folderId)->value('is_public'));
    }

    public function test_folder_action_delete_is_blocked_while_files_exist(): void
    {
        $admin = $this->makeUser('admin');
        $folderId = $this->makeFolder($admin->id);
        $faculty = $this->makeUser('user');

        // A file inside the folder => delete must be refused loudly.
        DB::table('files')->insert([
            'file_name' => 'kept.pdf',
            'original_name' => 'kept.pdf',
            'file_path' => 'uploads/departments/'.$this->departmentId().'/testing/kept.pdf',
            'file_size' => 100,
            'folder_id' => $folderId,
            'uploaded_by' => $faculty->id,
            'uploaded_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post('/folders/action', [
            'action' => 'delete',
            'folder_id' => $folderId,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, (int) DB::table('folders')->where('id', $folderId)->value('is_deleted'));
    }

    public function test_folder_action_delete_soft_deletes_empty_folder(): void
    {
        $admin = $this->makeUser('admin');
        $folderId = $this->makeFolder($admin->id);

        $this->actingAs($admin)->post('/folders/action', ['action' => 'delete', 'folder_id' => $folderId])
            ->assertSessionHas('success');

        $this->assertSame(1, (int) DB::table('folders')->where('id', $folderId)->value('is_deleted'));
    }

    // -----------------------------------------------------------------
    // Shared with me (admin)
    // -----------------------------------------------------------------

    public function test_shared_page_lists_user_shares(): void
    {
        $admin = $this->makeUser('admin');
        $sharer = $this->makeUser('user');

        $fileId = $this->makeWorkspaceFile($sharer->id, ['original_name' => 'shared-with-admin.pdf']);

        DB::table('file_shares')->insert([
            'file_id' => $fileId,
            'shared_by' => $sharer->id,
            'shared_with' => $admin->id,
            'share_token' => bin2hex(random_bytes(16)),
            'share_type' => 'user',
            'is_active' => 1,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/shared');

        $this->assertFilePagesSkeleton($response);
        $response->assertSee('Shared With Me');
        $response->assertSee('shared-with-admin.pdf');
    }

    public function test_department_shares_only_appear_for_same_department(): void
    {
        $sharer = $this->makeUser('user');
        $sameDeptAdmin = $this->makeUser('admin');
        $otherDeptAdmin = $this->makeUser('admin', ['department_id' => $this->otherDepartmentId()]);

        $fileId = $this->makeWorkspaceFile($sharer->id, ['original_name' => 'dept-wide-share.pdf']);

        DB::table('file_shares')->insert([
            'file_id' => $fileId,
            'shared_by' => $sharer->id,
            'shared_with' => null,
            'share_token' => bin2hex(random_bytes(16)),
            'share_type' => 'department',
            'is_active' => 1,
            'created_at' => now(),
        ]);

        $this->actingAs($sameDeptAdmin)->get('/admin/shared')->assertSee('dept-wide-share.pdf');
        $this->actingAs($otherDeptAdmin)->get('/admin/shared')->assertDontSee('dept-wide-share.pdf');
    }

    // -----------------------------------------------------------------
    // User department folders (files table flow)
    // -----------------------------------------------------------------

    public function test_user_folders_page_renders_department_categories(): void
    {
        $faculty = $this->makeUser('user');

        $response = $this->actingAs($faculty)->get('/user/folders');

        $this->assertFilePagesSkeleton($response);
        $response->assertSee('Department Folders');
        $response->assertSee('IPCR Accomplishment');
        $response->assertSee('Grading Sheet');
        $response->assertSee('My Custom Folders');
        $response->assertSee('Upload File');
    }

    public function test_user_folders_page_hides_no_department_users(): void
    {
        $faculty = $this->makeUser('user', ['department_id' => null]);

        $this->actingAs($faculty)
            ->get('/user/folders')
            ->assertOk()
            ->assertSee('No Department Assigned');
    }

    public function test_user_uploads_file_to_category_folder_and_lists_it(): void
    {
        $faculty = $this->makeUser('user');

        $response = $this->actingAs($faculty)->post('/files/upload', [
            'category' => 'exam',
            'semester' => 'first',
            'academic_year' => '2025-2026',
            'description' => 'Midterm exam copy',
            'file' => UploadedFile::fake()->create('midterm.pdf', 200),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $row = DB::table('files')
            ->where('uploaded_by', $faculty->id)
            ->where('semester', 'first')
            ->first();

        $this->assertNotNull($row, 'A files row must be created');
        $this->assertSame('exam', DB::table('folders')->where('id', $row->folder_id)->value('category'));
        $this->assertSame('2025-2026', $row->academic_year);
        $this->assertStringStartsWith('uploads/departments/', $row->file_path);

        $absolute = storage_path('app/private/'.$row->file_path);
        $this->assertFileExists($absolute);
        $this->createdUploads[] = $absolute;

        // The category files AJAX must now show it under the first semester.
        $list = $this->actingAs($faculty)->get('/folders/category-files?department_id='.$this->departmentId().'&category=exam');

        $list->assertOk()->assertJson(['success' => true])
            ->assertJsonPath('first_semester.0.original_name', 'midterm.pdf');
    }

    public function test_category_files_are_scoped_to_the_users_department(): void
    {
        $faculty = $this->makeUser('user');
        $otherDept = $this->otherDepartmentId();

        $this->actingAs($faculty)
            ->get('/folders/category-files?department_id='.$otherDept.'&category=exam')
            ->assertStatus(403);
    }

    public function test_custom_folder_lifecycle_create_upload_contents_delete(): void
    {
        $faculty = $this->makeUser('user');

        // Create the custom folder.
        $create = $this->actingAs($faculty)->postJson('/folders/custom', [
            'folder_name' => 'My Research Notes',
            'folder_description' => 'Drafts and references',
            'folder_color' => '#8b5cf6',
        ]);
        $create->assertOk()->assertJson(['success' => true]);

        $folder = DB::table('folders')
            ->where('folder_name', 'My Research Notes')
            ->where('created_by', $faculty->id)
            ->first();

        $this->assertNotNull($folder);
        $this->assertSame('custom', $folder->folder_type);

        // Upload into the custom folder.
        $upload = $this->actingAs($faculty)->post('/files/upload', [
            'custom_folder_id' => $folder->id,
            'semester' => 'second',
            'academic_year' => '2025-2026',
            'file' => UploadedFile::fake()->create('draft.pdf', 120),
        ]);
        $upload->assertOk()->assertJson(['success' => true]);

        $fileRow = DB::table('files')
            ->where('original_name', 'draft.pdf')
            ->where('uploaded_by', $faculty->id)
            ->first();
        $this->assertNotNull($fileRow);
        $absolute = storage_path('app/private/'.$fileRow->file_path);
        $this->createdUploads[] = $absolute;

        // Folder file count includes files in the semester subfolder.
        $subfolderId = DB::table('files')->where('id', $fileRow->id)->value('folder_id');
        $this->assertNotNull(DB::table('folders')->where('id', $subfolderId)->where('parent_id', $folder->id)->first());

        // Contents endpoint lists it (direct + subfolder files).
        $contents = $this->actingAs($faculty)->get('/folders/custom/contents?folder_id='.$folder->id);
        $contents->assertOk()->assertJson(['success' => true])
            ->assertJsonPath('files.0.original_name', 'draft.pdf');

        // Deleting without force first asks for confirmation...
        $this->actingAs($faculty)->postJson('/folders/custom/delete', ['folder_id' => $folder->id])
            ->assertJson(['confirm' => true]);

        // ...then force delete removes folder, subfolder and files.
        $this->actingAs($faculty)->postJson('/folders/custom/delete', [
            'folder_id' => $folder->id,
            'force_delete' => true,
        ])->assertJson(['success' => true]);

        $this->assertSame(1, (int) DB::table('folders')->where('id', $folder->id)->value('is_deleted'));
        $this->assertSame(1, (int) DB::table('folders')->where('id', $subfolderId)->value('is_deleted'));
        $this->assertSame(1, (int) DB::table('files')->where('id', $fileRow->id)->value('is_deleted'));
    }

    public function test_other_users_cannot_open_a_private_custom_folder(): void
    {
        $owner = $this->makeUser('user');
        $intruder = $this->makeUser('user');

        $folderId = DB::table('folders')->insertGetId([
            'folder_name' => 'Private Folder',
            'created_by' => $owner->id,
            'department_id' => $this->departmentId(),
            'folder_path' => '/departments/'.$this->departmentId().'/custom/Private Folder',
            'folder_level' => 1,
            'folder_type' => 'custom',
            'folder_status' => 'active',
            'is_public' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->get('/folders/custom/contents?folder_id='.$folderId)
            ->assertStatus(404);
    }

    // -----------------------------------------------------------------
    // Workspace file downloads (files table)
    // -----------------------------------------------------------------

    public function test_workspace_download_streams_own_file_and_counts_download(): void
    {
        $faculty = $this->makeUser('user');

        $this->actingAs($faculty)->post('/files/upload', [
            'category' => 'lecture',
            'semester' => 'first',
            'academic_year' => '2025-2026',
            'file' => UploadedFile::fake()->create('lesson.pdf', 300),
        ])->assertOk();

        $row = DB::table('files')->where('original_name', 'lesson.pdf')->first();
        $this->createdUploads[] = storage_path('app/private/'.$row->file_path);

        $download = $this->actingAs($faculty)->get('/workspace/files/'.$row->id.'/download');

        $download->assertOk();
        $this->assertSame(1, (int) DB::table('files')->where('id', $row->id)->value('download_count'));
    }

    public function test_workspace_delete_soft_deletes_own_file(): void
    {
        $faculty = $this->makeUser('user');

        $this->actingAs($faculty)->post('/files/upload', [
            'category' => 'activities',
            'semester' => 'first',
            'academic_year' => '2025-2026',
            'file' => UploadedFile::fake()->create('activity.pdf', 250),
        ])->assertOk();

        $row = DB::table('files')->where('original_name', 'activity.pdf')->first();
        $this->createdUploads[] = storage_path('app/private/'.$row->file_path);

        $this->actingAs($faculty)->postJson('/workspace/files/delete', ['file_id' => $row->id])
            ->assertJson(['success' => true]);

        $this->assertSame(1, (int) DB::table('files')->where('id', $row->id)->value('is_deleted'));
    }

    // -----------------------------------------------------------------
    // Admin document file management (document_files)
    // -----------------------------------------------------------------

    public function test_admin_can_update_description_of_files_in_own_department(): void
    {
        $admin = $this->makeUser('admin');
        $faculty = $this->makeUser('user');
        $fileId = $this->makeDocumentFile($faculty->id);

        $this->actingAs($admin)->postJson('/files/update', [
            'file_id' => $fileId,
            'description' => 'Updated by admin',
        ])->assertJson(['success' => true]);

        $this->assertSame('Updated by admin', DB::table('document_files')->where('id', $fileId)->value('description'));
    }

    public function test_admin_cannot_modify_files_of_another_department(): void
    {
        $admin = $this->makeUser('admin');
        $otherFaculty = $this->makeUser('user', ['department_id' => $this->otherDepartmentId()]);
        $fileId = $this->makeDocumentFile($otherFaculty->id);

        $this->actingAs($admin)->postJson('/files/update', ['file_id' => $fileId, 'description' => 'nope'])
            ->assertStatus(403);

        $this->actingAs($admin)->postJson('/files/delete', ['file_id' => $fileId])
            ->assertStatus(403);
    }

    public function test_superadmin_can_share_a_workspace_file(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $faculty = $this->makeUser('user');
        $receiver = $this->makeUser('user');

        $fileId = $this->makeWorkspaceFile($faculty->id, ['original_name' => 'shareable-report.pdf']);

        $this->actingAs($superAdmin)->postJson('/files/share', [
            'file_id' => $fileId,
            'share_type' => 'user',
            'shared_with' => $receiver->id,
        ])->assertJson(['success' => true]);

        $this->assertSame(1, (int) DB::table('file_shares')
            ->where('file_id', $fileId)
            ->where('shared_with', $receiver->id)
            ->where('share_type', 'user')
            ->value('is_active'));
    }

    public function test_users_cannot_share_files_they_do_not_own(): void
    {
        $owner = $this->makeUser('user');
        $intruder = $this->makeUser('user');
        $fileId = $this->makeWorkspaceFile($owner->id);

        $this->actingAs($intruder)->postJson('/files/share', [
            'file_id' => $fileId,
            'share_type' => 'user',
            'shared_with' => $owner->id,
        ])->assertStatus(403);
    }
}