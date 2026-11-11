<?php

namespace App\Http\Controllers\SocialFeed;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Port of the 14 legacy Social Feed AJAX endpoints under social_feed/api/*.php.
 *
 * Every query and JSON shape is ported verbatim from the legacy managers
 * (PostManager, CommentManager, ReactionManager, MediaManager,
 * NotificationManager) so the front-end keeps working unchanged apart from
 * the URL/CSRF/media-path adaptations already made in public/js/social_feed/.
 *
 * Login-only like the legacy endpoints (requireAuth(); no role gating).
 */
class SocialFeedApiController extends Controller
{
    // ---------------------------------------------------------------------
    // GET endpoints
    // ---------------------------------------------------------------------

    /**
     * GET /social_feed/get_user_info.php
     */
    public function getUserInfo(Request $request): JsonResponse
    {
        try {
            $row = DB::selectOne("
                SELECT u.id, u.username, u.name, u.mi, u.surname, u.email, u.position,
                       u.profile_image, u.role, u.department_id, d.department_name, d.department_code,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as full_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.id = ?
            ", [$request->user()->id]);

            if ($row) {
                $user = json_decode(json_encode($row), true);
                unset($user['password']);

                return response()->json(['success' => true, 'user' => $user]);
            }

            return response()->json(['success' => false, 'message' => 'User not found']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get user info error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * GET /social_feed/get_notification_count.php
     */
    public function getNotificationCount(Request $request): JsonResponse
    {
        try {
            $result = DB::selectOne("
                SELECT COUNT(*) as count
                FROM post_notifications
                WHERE user_id = ? AND is_read = 0
            ", [$request->user()->id]);

            return response()->json([
                'success' => true,
                'count' => (int) ($result->count ?? 0),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get notification count error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error getting notification count']);
        }
    }

    /**
     * GET /social_feed/get_posts.php?filter=&page=&limit=
     */
    public function getPosts(Request $request): JsonResponse
    {
        try {
            $filter = $request->query('filter', 'all');
            $page = (int) $request->query('page', 0);
            $limit = (int) $request->query('limit', 10);
            $offset = $page * $limit;

            $userId = $request->user()->id;
            $userRole = $request->user()->role ?? 'user';

            // Get user's department
            $userDept = DB::selectOne('SELECT department_id FROM users WHERE id = ?', [$userId]);
            $departmentId = $userDept->department_id ?? 0;

            // Build query based on filter
            $whereConditions = ['p.is_deleted = 0'];
            $params = [];

            switch ($filter) {
                case 'my_posts':
                    $whereConditions[] = 'p.user_id = ?';
                    $params[] = $userId;
                    break;

                case 'department':
                    $whereConditions[] = "(p.visibility = 'department' AND JSON_CONTAINS(p.target_departments, ?))";
                    $params[] = '"'.$departmentId.'"';
                    break;

                case 'pinned':
                    $whereConditions[] = 'p.is_pinned = 1';
                    break;

                default: // all posts
                    $whereConditions[] = "(
                        p.visibility = 'public' OR
                        p.user_id = ? OR
                        (p.visibility = 'department' AND JSON_CONTAINS(p.target_departments, ?)) OR
                        (p.visibility = 'custom' AND JSON_CONTAINS(p.target_users, ?))
                    )";
                    $params = array_merge($params, [$userId, '"'.$departmentId.'"', '"'.$userId.'"']);
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Simple order by - pinned posts first, then by creation date
            $orderBy = 'p.is_pinned DESC, p.created_at DESC';

            $query = "
                SELECT p.*, u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
                       u.profile_image, u.position, u.role, d.department_code, d.department_name,
                       EXISTS(SELECT 1 FROM post_likes pl WHERE pl.post_id = p.id AND pl.user_id = ?) as user_liked,
                       (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id AND pc.is_deleted = 0) as comment_count,
                       (SELECT COUNT(*) FROM post_views pv WHERE pv.post_id = p.id) as view_count
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT ? OFFSET ?
            ";

            $allParams = array_merge([$userId], $params, [$limit, $offset]);
            $posts = $this->rowsToArrays(DB::select($query, $allParams));

            // Process each post
            foreach ($posts as &$post) {
                // Get media for each post
                $post['media'] = $this->getPostMedia((int) $post['id']);

                // Track view if user hasn't viewed this post before
                try {
                    DB::statement("
                        INSERT IGNORE INTO post_views (post_id, user_id, viewed_at, ip_address, user_agent)
                        VALUES (?, ?, NOW(), ?, ?)
                    ", [
                        $post['id'],
                        $userId,
                        $request->ip(),
                        $request->userAgent(),
                    ]);
                } catch (\Throwable $e) {
                    // Ignore duplicate entry errors
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        \Illuminate\Support\Facades\Log::error("View tracking error for post {$post['id']}: ".$e->getMessage());
                    }
                }

                // Ensure counts are integers
                $post['like_count'] = (int) $post['like_count'];
                $post['comment_count'] = (int) $post['comment_count'];
                $post['view_count'] = (int) $post['view_count'];
            }

            return response()->json([
                'success' => true,
                'posts' => $posts,
                'has_more' => count($posts) === $limit,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get posts error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error loading posts: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * GET /social_feed/get_post.php?id=
     */
    public function getPost(Request $request): JsonResponse
    {
        try {
            $postId = (int) $request->query('id', 0);
            $userId = $request->user()->id;

            if (! $postId) {
                return response()->json(['success' => false, 'message' => 'Post ID is required']);
            }

            $row = DB::selectOne("
                SELECT p.*, u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as author_full_name,
                       u.profile_image, u.position, d.department_code
                FROM posts p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE p.id = ? AND p.is_deleted = 0
            ", [$postId]);

            if ($row) {
                $post = json_decode(json_encode($row), true);

                // Check if user has permission to view this post
                $canView = false;

                if ($post['visibility'] === 'public' || $post['user_id'] == $userId) {
                    $canView = true;
                } elseif ($post['visibility'] === 'department') {
                    // Check if user is in target department
                    $userDept = DB::selectOne('SELECT department_id FROM users WHERE id = ?', [$userId]);

                    if ($userDept && $post['target_departments']) {
                        $targetDepts = json_decode($post['target_departments'], true);
                        $canView = is_array($targetDepts) && in_array($userDept->department_id, $targetDepts);
                    }
                } elseif ($post['visibility'] === 'custom') {
                    // Check if user is in target users
                    if ($post['target_users']) {
                        $targetUsers = json_decode($post['target_users'], true);
                        $canView = is_array($targetUsers) && in_array($userId, $targetUsers);
                    }
                }

                if ($canView) {
                    return response()->json(['success' => true, 'post' => $post]);
                }

                return response()->json(['success' => false, 'message' => 'You do not have permission to view this post']);
            }

            return response()->json(['success' => false, 'message' => 'Post not found']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get post error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error retrieving post: '.$e->getMessage()]);
        }
    }

    /**
     * GET /social_feed/get_comments.php?post_id=&limit=&offset=
     */
    public function getComments(Request $request): JsonResponse
    {
        try {
            $postId = (int) $request->query('post_id', 0);
            $limit = (int) $request->query('limit', 20);
            $offset = (int) $request->query('offset', 0);

            if (! $postId) {
                return response()->json(['success' => false, 'message' => 'Post ID is required']);
            }

            // Verify post exists and user can view it
            $post = DB::selectOne('SELECT id FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);

            if (! $post) {
                return response()->json(['success' => false, 'message' => 'Post not found']);
            }

            // Get comments using the CommentManager logic
            $comments = $this->getPostComments($postId, $limit, $offset);

            // Get actual comment count for this post
            $actualCount = $this->getActualCommentCount($postId);

            // Check if user liked each comment and reply
            $userId = $request->user()->id;
            foreach ($comments as &$comment) {
                // Check if user liked this comment
                $comment['user_liked'] = DB::selectOne(
                    'SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?',
                    [$comment['id'], $userId]
                ) ? true : false;

                // Also check replies for likes
                if (isset($comment['replies']) && is_array($comment['replies'])) {
                    foreach ($comment['replies'] as &$reply) {
                        $reply['user_liked'] = DB::selectOne(
                            'SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?',
                            [$reply['id'], $userId]
                        ) ? true : false;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'comments' => $comments,
                'total_comments' => $actualCount,
                'has_more' => count($comments) === $limit,
                'comment_count' => count($comments),
                'offset' => $offset,
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get comments error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error loading comments: '.$e->getMessage(),
                'comments' => [],
                'total_comments' => 0,
            ]);
        }
    }

    /**
     * GET /social_feed/get_replies.php?parent_comment_id=&limit=&offset=
     */
    public function getReplies(Request $request): JsonResponse
    {
        try {
            $parentCommentId = (int) $request->query('parent_comment_id', 0);
            $limit = (int) $request->query('limit', 10);
            $offset = (int) $request->query('offset', 0);

            if (! $parentCommentId) {
                return response()->json(['success' => false, 'message' => 'Parent comment ID is required']);
            }

            // Verify parent comment exists
            $parentComment = DB::selectOne(
                'SELECT id, post_id FROM post_comments WHERE id = ? AND is_deleted = 0',
                [$parentCommentId]
            );

            if (! $parentComment) {
                return response()->json(['success' => false, 'message' => 'Parent comment not found']);
            }

            // Get replies
            $query = "
                SELECT c.*, u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                       u.profile_image, u.position
                FROM post_comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.parent_comment_id = ? AND c.is_deleted = 0
                ORDER BY c.created_at ASC
                LIMIT ? OFFSET ?
            ";

            $replies = $this->rowsToArrays(DB::select($query, [$parentCommentId, $limit, $offset]));

            // Check if user liked each reply
            $userId = $request->user()->id;
            foreach ($replies as &$reply) {
                $reply['user_liked'] = DB::selectOne(
                    'SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?',
                    [$reply['id'], $userId]
                ) ? true : false;
            }

            return response()->json([
                'success' => true,
                'replies' => $replies,
                'has_more' => count($replies) === $limit,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get replies error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error loading replies: '.$e->getMessage(),
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // POST endpoints
    // ---------------------------------------------------------------------

    /**
     * POST /social_feed/create_post.php (multipart)
     */
    public function createPost(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $content = trim((string) $request->input('content', ''));
            $visibility = $request->input('visibility', 'public');

            // Allow empty content if there are images or files
            $hasImages = $request->hasFile('images');
            $hasFiles = $request->hasFile('files');

            if (empty($content) && ! $hasImages && ! $hasFiles) {
                return response()->json(['success' => false, 'message' => 'Content, images, or files are required']);
            }

            // Validate inputs
            $allowedVisibility = ['public', 'department', 'custom'];

            if (! in_array($visibility, $allowedVisibility)) {
                $visibility = 'public';
            }

            // Enhanced department handling
            $targetDepartments = null;
            if ($visibility === 'department') {
                // Check if specific departments were selected
                $selectedDepartments = $request->input('selectedDepartments', '');

                if (! empty($selectedDepartments)) {
                    // Use the selected departments from the enhanced selector
                    try {
                        $selectedDepts = json_decode($selectedDepartments, true);
                        if (is_array($selectedDepts) && count($selectedDepts) > 0) {
                            // Map department codes to department IDs
                            $targetDepartments = $this->getDepartmentIdsByCodes($selectedDepts);

                            if (empty($targetDepartments)) {
                                return response()->json(['success' => false, 'message' => 'Invalid departments selected']);
                            }
                        } else {
                            return response()->json(['success' => false, 'message' => 'No departments selected']);
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Error parsing selected departments: '.$e->getMessage());

                        return response()->json(['success' => false, 'message' => 'Invalid department selection format']);
                    }
                } else {
                    // Fallback to user's own department if no specific selection
                    $userDept = DB::selectOne('SELECT department_id FROM users WHERE id = ?', [$userId]);
                    if ($userDept && $userDept->department_id) {
                        $targetDepartments = [(int) $userDept->department_id];
                    } else {
                        return response()->json(['success' => false, 'message' => 'No department found for user']);
                    }
                }
            }

            // Create post
            $postId = $this->createPostRecord($userId, $content, 'text', $visibility, $targetDepartments, null);

            if (! $postId) {
                return response()->json(['success' => false, 'message' => 'Failed to create post']);
            }

            // UPDATED: Define upload directories with correct paths
            $socialFeedBaseDir = public_path('social_feed'.DIRECTORY_SEPARATOR.'uploads').DIRECTORY_SEPARATOR;
            $imagesUploadDir = $socialFeedBaseDir.'images'.DIRECTORY_SEPARATOR;
            $filesUploadDir = $socialFeedBaseDir.'files'.DIRECTORY_SEPARATOR;

            // Create directories if they don't exist
            foreach ([$socialFeedBaseDir, $imagesUploadDir, $filesUploadDir] as $dir) {
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }

            $uploadedImages = 0;
            $uploadedFiles = 0;
            $uploadErrors = [];

            // Handle multiple image uploads
            $imageFiles = $request->file('images');
            if ($imageFiles) {
                if (! is_array($imageFiles)) {
                    $imageFiles = [$imageFiles];
                }

                $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxImageSize = 20 * 1024 * 1024; // 20MB per image
                $maxImages = 100; // Maximum 100 images per post

                $imageCount = count($imageFiles);

                if ($imageCount > $maxImages) {
                    return response()->json(['success' => false, 'message' => "Maximum {$maxImages} images allowed"]);
                }

                foreach ($imageFiles as $imageUpload) {
                    // Check if this image slot has a file
                    if (! $imageUpload instanceof UploadedFile || $imageUpload->getError() !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $imageName = $imageUpload->getClientOriginalName();
                    $imageType = $imageUpload->getClientMimeType();
                    $imageSize = $imageUpload->getSize();

                    // Validate image
                    if (! in_array($imageType, $allowedImageTypes)) {
                        $uploadErrors[] = "{$imageName}: Invalid image type";
                        continue;
                    }

                    if ($imageSize > $maxImageSize) {
                        $uploadErrors[] = "{$imageName}: Image too large (max 20MB)";
                        continue;
                    }

                    // Generate unique filename
                    $fileExtension = pathinfo($imageName, PATHINFO_EXTENSION);
                    $fileName = uniqid().'_'.time().'.'.$fileExtension;
                    $physicalPath = $imagesUploadDir.$fileName;

                    try {
                        $imageUpload->move($imagesUploadDir, $fileName);

                        // UPDATED: Store path relative to ODCI root for database
                        $webPath = 'social_feed/uploads/images/'.$fileName;

                        $mediaAdded = $this->addPostMedia($postId, 'image', $webPath, $fileName, $imageName, $imageSize, $imageType);

                        if ($mediaAdded) {
                            $uploadedImages++;
                            \Illuminate\Support\Facades\Log::info("Image uploaded successfully: {$webPath}");
                        } else {
                            $uploadErrors[] = "{$imageName}: Failed to save to database";
                            // Clean up the uploaded file
                            if (file_exists($physicalPath)) {
                                unlink($physicalPath);
                            }
                        }
                    } catch (\Throwable $e) {
                        $uploadErrors[] = "{$imageName}: Failed to upload";
                        \Illuminate\Support\Facades\Log::error('Image upload error: '.$e->getMessage());
                    }
                }
            }

            // Handle multiple file uploads
            $files = $request->file('files');
            if ($files) {
                if (! is_array($files)) {
                    $files = [$files];
                }

                $maxFileSize = 100 * 1024 * 1024; // 100MB per file
                $maxFiles = 50; // Maximum 50 files per post

                // Blocked image types (should use image upload instead)
                $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

                $fileCount = count($files);

                if ($fileCount > $maxFiles) {
                    return response()->json(['success' => false, 'message' => "Maximum {$maxFiles} files allowed"]);
                }

                foreach ($files as $fileUpload) {
                    // Check if this file slot has a file
                    if (! $fileUpload instanceof UploadedFile || $fileUpload->getError() !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $fileName = $fileUpload->getClientOriginalName();
                    $fileType = $fileUpload->getClientMimeType();
                    $fileSize = $fileUpload->getSize();

                    // Check if it's an image type (should use image upload)
                    if (in_array($fileType, $imageTypes)) {
                        $uploadErrors[] = "{$fileName}: Image files should use image upload button";
                        continue;
                    }

                    // Validate file size
                    if ($fileSize > $maxFileSize) {
                        $uploadErrors[] = "{$fileName}: File too large (max 100MB)";
                        continue;
                    }

                    // Generate unique filename while preserving extension
                    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $uniqueFileName = uniqid().'_'.time().'.'.$fileExtension;
                    $physicalPath = $filesUploadDir.$uniqueFileName;

                    try {
                        $fileUpload->move($filesUploadDir, $uniqueFileName);

                        // UPDATED: Store path relative to ODCI root for database
                        $webPath = 'social_feed/uploads/files/'.$uniqueFileName;

                        $mediaAdded = $this->addPostMedia($postId, 'file', $webPath, $uniqueFileName, $fileName, $fileSize, $fileType);

                        if ($mediaAdded) {
                            $uploadedFiles++;
                            \Illuminate\Support\Facades\Log::info("File uploaded successfully: {$webPath}");
                        } else {
                            $uploadErrors[] = "{$fileName}: Failed to save to database";
                            // Clean up the uploaded file
                            if (file_exists($physicalPath)) {
                                unlink($physicalPath);
                            }
                        }
                    } catch (\Throwable $e) {
                        $uploadErrors[] = "{$fileName}: Failed to upload";
                        \Illuminate\Support\Facades\Log::error('File upload error: '.$e->getMessage());
                    }
                }
            }

            // Prepare response message
            $messageParts = ['Post created successfully'];

            if ($uploadedImages > 0) {
                $messageParts[] = "{$uploadedImages} image(s) uploaded";
            }

            if ($uploadedFiles > 0) {
                $messageParts[] = "{$uploadedFiles} file(s) uploaded";
            }

            $message = implode(' with ', $messageParts);

            if (! empty($uploadErrors)) {
                $message .= '. Some files had issues: '.implode(', ', $uploadErrors);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'post_id' => $postId,
                'uploaded_images' => $uploadedImages,
                'uploaded_files' => $uploadedFiles,
                'upload_errors' => $uploadErrors,
                'visibility' => $visibility,
                'target_departments' => $targetDepartments,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Create post error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error creating post: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * POST /social_feed/update_post.php (JSON body)
     */
    public function updatePost(Request $request): JsonResponse
    {
        try {
            $input = $request->json()->all();
            $userId = $request->user()->id;
            $userRole = $request->user()->role ?? 'user';
            $postId = (int) ($input['id'] ?? 0);
            $content = trim((string) ($input['content'] ?? ''));

            if (! $postId || empty($content)) {
                return response()->json(['success' => false, 'message' => 'Post ID and content are required']);
            }

            $result = $this->updatePostRecord($postId, $userId, $content, $userRole);

            if ($result) {
                return response()->json(['success' => true, 'message' => 'Post updated successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Failed to update post or insufficient permissions']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Update post error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error updating post: '.$e->getMessage()]);
        }
    }

    /**
     * POST /social_feed/delete_post.php (JSON body)
     */
    public function deletePost(Request $request): JsonResponse
    {
        try {
            $input = $request->json()->all();
            $userId = $request->user()->id;
            $userRole = $request->user()->role ?? 'user';
            $postId = (int) ($input['id'] ?? 0);

            if (! $postId) {
                return response()->json(['success' => false, 'message' => 'Post ID is required']);
            }

            $result = $this->deletePostRecord($postId, $userId, $userRole);

            if ($result) {
                return response()->json(['success' => true, 'message' => 'Post deleted successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Failed to delete post or insufficient permissions']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Delete post error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error deleting post: '.$e->getMessage()]);
        }
    }

    /**
     * POST /social_feed/toggle_like.php (JSON body)
     */
    public function toggleLike(Request $request): JsonResponse
    {
        try {
            $input = $request->json()->all();
            $userId = $request->user()->id;
            $postId = $input['post_id'] ?? null;
            $commentId = $input['comment_id'] ?? null;
            $reactionType = $input['reaction_type'] ?? 'like';

            if (! $postId && ! $commentId) {
                return response()->json(['success' => false, 'message' => 'Post ID or Comment ID required']);
            }

            $result = $this->toggleLikeRecord($userId, $postId, $commentId);

            if ($result) {
                // Get updated like count
                if ($postId) {
                    $post = DB::selectOne('SELECT like_count FROM posts WHERE id = ?', [$postId]);
                    $likeCount = $post->like_count ?? 0;

                    // Update like count
                    DB::statement(
                        'UPDATE posts SET like_count = (SELECT COUNT(*) FROM post_likes WHERE post_id = ?) WHERE id = ?',
                        [$postId, $postId]
                    );

                    // Check if user liked
                    $liked = DB::selectOne(
                        'SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?',
                        [$postId, $userId]
                    ) ? true : false;
                } else {
                    $comment = DB::selectOne('SELECT like_count FROM post_comments WHERE id = ?', [$commentId]);
                    $likeCount = $comment->like_count ?? 0;

                    // Update like count
                    DB::statement(
                        'UPDATE post_comments SET like_count = (SELECT COUNT(*) FROM post_likes WHERE comment_id = ?) WHERE id = ?',
                        [$commentId, $commentId]
                    );

                    // Check if user liked
                    $liked = DB::selectOne(
                        'SELECT id FROM post_likes WHERE comment_id = ? AND user_id = ?',
                        [$commentId, $userId]
                    ) ? true : false;
                }

                return response()->json([
                    'success' => true,
                    'liked' => $liked,
                    'like_count' => $likeCount,
                    'reacted' => $liked,
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Failed to toggle like']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Toggle like error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error toggling like: '.$e->getMessage()]);
        }
    }

    /**
     * POST /social_feed/add_comment.php (JSON body)
     */
    public function addComment(Request $request): JsonResponse
    {
        try {
            $input = $request->json()->all();
            $userId = $request->user()->id;
            $postId = (int) ($input['post_id'] ?? 0);
            $content = trim((string) ($input['content'] ?? ''));
            $parentCommentId = isset($input['parent_comment_id']) && $input['parent_comment_id']
                ? (int) $input['parent_comment_id']
                : null;

            if (! $postId || empty($content)) {
                return response()->json(['success' => false, 'message' => 'Post ID and content are required']);
            }

            // Verify post exists and user can access it
            $post = DB::selectOne(
                'SELECT user_id, visibility FROM posts WHERE id = ? AND is_deleted = 0',
                [$postId]
            );

            if (! $post) {
                return response()->json(['success' => false, 'message' => 'Post not found']);
            }

            // If it's a reply, verify parent comment exists and belongs to the same post
            if ($parentCommentId) {
                $parentComment = DB::selectOne(
                    'SELECT post_id, user_id FROM post_comments WHERE id = ? AND is_deleted = 0',
                    [$parentCommentId]
                );

                if (! $parentComment || $parentComment->post_id != $postId) {
                    return response()->json(['success' => false, 'message' => 'Parent comment not found or invalid']);
                }
            }

            // Use CommentManager logic to add the comment
            $commentId = $this->addCommentRecord($postId, $userId, $content, $parentCommentId);

            if ($commentId) {
                // REMOVED: Manual comment count increment - the database trigger handles this automatically

                // Get the actual current comment count (this will reflect the trigger's update)
                $actualCount = $this->getActualCommentCount($postId);

                // Log activity
                $activityMessage = $parentCommentId ? 'Added reply to comment' : 'Added comment to post';
                $this->logActivity($userId, 'add_comment', 'comment', $commentId, $activityMessage, [
                    'post_id' => $postId,
                    'parent_comment_id' => $parentCommentId,
                ]);

                // Send notifications
                try {
                    // Send notification to post author (if not commenting on own post)
                    if ($post->user_id != $userId) {
                        $this->sendCommentNotification($postId, $commentId, $userId, $post->user_id);
                    }

                    // If replying to a comment, notify the parent comment author
                    if ($parentCommentId && isset($parentComment)) {
                        $parentCommentAuthor = $parentComment->user_id;
                        if ($parentCommentAuthor != $userId) {
                            $this->sendReplyNotification($postId, $commentId, $userId, $parentCommentAuthor);
                        }
                    }
                } catch (\Throwable $notifError) {
                    \Illuminate\Support\Facades\Log::error('Notification error: '.$notifError->getMessage());
                    // Don't fail the entire operation for notification errors
                }

                // Get the newly created comment data for response
                $row = DB::selectOne("
                    SELECT c.*, u.username, u.name, u.mi, u.surname,
                           CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                           u.profile_image, u.position,
                           parent.id as parent_id,
                           CONCAT(parent_user.name, ' ', IFNULL(CONCAT(parent_user.mi, '. '), ''), parent_user.surname) as parent_author_name
                    FROM post_comments c
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN post_comments parent ON c.parent_comment_id = parent.id
                    LEFT JOIN users parent_user ON parent.user_id = parent_user.id
                    WHERE c.id = ?
                ", [$commentId]);

                $commentData = $row ? json_decode(json_encode($row), true) : null;

                $responseMessage = $parentCommentId ? 'Reply added successfully' : 'Comment added successfully';

                return response()->json([
                    'success' => true,
                    'message' => $responseMessage,
                    'comment_id' => $commentId,
                    'actual_comment_count' => $actualCount,
                    'is_reply' => $parentCommentId !== null,
                    'parent_comment_id' => $parentCommentId,
                    'comment_data' => $commentData,
                ]);
            }

            $errorMessage = $parentCommentId ? 'Failed to add reply' : 'Failed to add comment';

            return response()->json(['success' => false, 'message' => $errorMessage]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Add comment error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error adding comment: '.$e->getMessage()]);
        }
    }

    /**
     * POST /social_feed/edit_comment.php (JSON body)
     */
    public function editComment(Request $request): JsonResponse
    {
        try {
            $input = $request->json()->all();
            $userId = $request->user()->id;
            $userRole = $request->user()->role ?? 'user';
            $commentId = (int) ($input['comment_id'] ?? 0);
            $content = trim((string) ($input['content'] ?? ''));

            if (! $commentId || empty($content)) {
                return response()->json(['success' => false, 'message' => 'Comment ID and content are required']);
            }

            $result = $this->updateCommentRecord($commentId, $userId, $content, $userRole);

            if ($result) {
                return response()->json(['success' => true, 'message' => 'Comment updated successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Failed to update comment or insufficient permissions']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Edit comment error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error updating comment: '.$e->getMessage()]);
        }
    }

    /**
     * POST /social_feed/delete_comment.php (JSON body)
     */
    public function deleteComment(Request $request): JsonResponse
    {
        try {
            $input = $request->json()->all();
            $userId = $request->user()->id;
            $userRole = $request->user()->role ?? 'user';
            $commentId = (int) ($input['comment_id'] ?? 0);

            if (! $commentId) {
                return response()->json(['success' => false, 'message' => 'Comment ID is required']);
            }

            // Get post ID BEFORE deleting the comment
            $comment = DB::selectOne(
                'SELECT post_id FROM post_comments WHERE id = ? AND is_deleted = 0',
                [$commentId]
            );

            if (! $comment) {
                return response()->json(['success' => false, 'message' => 'Comment not found']);
            }

            $postId = $comment->post_id;

            $result = $this->deleteCommentRecord($commentId, $userId, $userRole);

            if ($result) {
                // REMOVED: Manual comment count decrement - the database trigger handles this automatically

                return response()->json([
                    'success' => true,
                    'message' => 'Comment deleted successfully',
                    'post_id' => $postId,
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Failed to delete comment or insufficient permissions']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Delete comment error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error deleting comment: '.$e->getMessage()]);
        }
    }

    /**
     * POST /social_feed/reply_comment.php (JSON body)
     */
    public function replyComment(Request $request): JsonResponse
    {
        try {
            $input = $request->json()->all();
            $userId = $request->user()->id;
            $parentCommentId = (int) ($input['parent_comment_id'] ?? 0);
            $content = trim((string) ($input['content'] ?? ''));

            if (! $parentCommentId || empty($content)) {
                return response()->json(['success' => false, 'message' => 'Parent comment ID and content are required']);
            }

            // Get post ID from parent comment
            $parentComment = DB::selectOne(
                'SELECT post_id FROM post_comments WHERE id = ?',
                [$parentCommentId]
            );

            if (! $parentComment) {
                return response()->json(['success' => false, 'message' => 'Parent comment not found']);
            }

            $postId = $parentComment->post_id;

            // Create reply
            $result = DB::insert("
                INSERT INTO post_comments (post_id, user_id, parent_comment_id, content)
                VALUES (?, ?, ?, ?)
            ", [$postId, $userId, $parentCommentId, $content]);

            if ($result) {
                $commentId = DB::getPdo()->lastInsertId();

                // Update comment count
                DB::statement(
                    'UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?',
                    [$postId]
                );

                // Log activity
                $this->logActivity($userId, 'add_reply', 'comment', (int) $commentId, 'Added reply to comment', [
                    'post_id' => $postId,
                    'parent_comment_id' => $parentCommentId,
                ]);

                // Send notification to parent comment author
                $parentCommentAuthor = DB::selectOne(
                    'SELECT user_id FROM post_comments WHERE id = ?',
                    [$parentCommentId]
                );

                if ($parentCommentAuthor && $parentCommentAuthor->user_id != $userId) {
                    $this->sendReplyNotification($postId, (int) $commentId, $userId, $parentCommentAuthor->user_id);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Reply added successfully',
                    'comment_id' => (int) $commentId,
                    'post_id' => $postId,
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Failed to add reply']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Add reply error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error adding reply: '.$e->getMessage()]);
        }
    }

    // ---------------------------------------------------------------------
    // PostManager port
    // ---------------------------------------------------------------------

    private function createPostRecord(int $userId, string $content, string $contentType = 'text', string $visibility = 'public', ?array $targetDepartments = null, ?array $targetUsers = null, bool $isPinned = false): int|false
    {
        try {
            $result = DB::insert("
                INSERT INTO posts (user_id, content, content_type, visibility, target_departments, target_users, is_pinned)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [
                $userId,
                $content,
                $contentType,
                $visibility,
                $targetDepartments ? json_encode($targetDepartments) : null,
                $targetUsers ? json_encode($targetUsers) : null,
                $isPinned ? 1 : 0,
            ]);

            if (! $result) {
                return false;
            }

            $postId = (int) DB::getPdo()->lastInsertId();

            // Log activity
            $this->logActivity($userId, 'create_post', 'post', $postId, 'Created new post', [
                'content_type' => $contentType,
                'visibility' => $visibility,
            ]);

            // Send notifications for public posts or specific targets
            if ($visibility === 'public') {
                $this->sendPostNotifications($postId, $userId, 'new_post');
            } elseif ($visibility === 'department' && $targetDepartments) {
                $this->sendDepartmentPostNotifications($postId, $userId, $targetDepartments);
            } elseif ($visibility === 'custom' && $targetUsers) {
                $this->sendCustomPostNotifications($postId, $userId, $targetUsers);
            }

            return $postId;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Create post error: '.$e->getMessage());

            return false;
        }
    }

    private function canUserEditPost(int $postId, int $userId, string $userRole = 'user'): bool
    {
        try {
            $post = DB::selectOne('SELECT user_id FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);

            if (! $post) {
                return false;
            }

            // User can edit if they own the post OR they have admin privileges
            return ($post->user_id == $userId) || in_array($userRole, ['admin', 'super_admin']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Check edit post permission error: '.$e->getMessage());

            return false;
        }
    }

    private function updatePostRecord(int $postId, int $userId, string $content, string $userRole = 'user'): bool
    {
        try {
            // Enhanced authorization check
            if (! $this->canUserEditPost($postId, $userId, $userRole)) {
                \Illuminate\Support\Facades\Log::error("Unauthorized edit attempt - Post ID: $postId, User ID: $userId, Role: $userRole");

                return false;
            }

            $result = DB::update("
                UPDATE posts
                SET content = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW()
                WHERE id = ? AND is_deleted = 0
            ", [$content, $postId]);

            if ($result) {
                $this->logActivity($userId, 'edit_post', 'post', $postId, 'Edited post');
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Update post error: '.$e->getMessage());

            return false;
        }
    }

    private function deletePostRecord(int $postId, int $userId, string $userRole = 'user'): bool
    {
        try {
            // Use the same authorization check for consistency
            if (! $this->canUserEditPost($postId, $userId, $userRole)) {
                \Illuminate\Support\Facades\Log::error("Unauthorized delete attempt - Post ID: $postId, User ID: $userId, Role: $userRole");

                return false;
            }

            $result = DB::update("
                UPDATE posts
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE id = ? AND is_deleted = 0
            ", [$userId, $postId]);

            if ($result) {
                $this->logActivity($userId, 'delete_post', 'post', $postId, 'Deleted post');
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Delete post error: '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // CommentManager port
    // ---------------------------------------------------------------------

    private function getPostComments(int $postId, int $limit = 20, int $offset = 0): array
    {
        try {
            // Get top-level comments (no parent)
            $query = "
                SELECT c.id, c.post_id, c.user_id, c.parent_comment_id, c.content,
                       c.is_edited, c.edited_at, c.is_deleted, c.deleted_at, c.deleted_by,
                       c.created_at, c.updated_at, c.like_count,
                       u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                       u.profile_image, u.position,
                       d.department_code,
                       deleter.username as deleted_by_username
                FROM post_comments c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN users deleter ON c.deleted_by = deleter.id
                WHERE c.post_id = ? AND c.is_deleted = 0 AND c.parent_comment_id IS NULL
                ORDER BY c.created_at ASC
                LIMIT ? OFFSET ?
            ";

            $comments = $this->rowsToArrays(DB::select($query, [$postId, $limit, $offset]));

            // Get replies for each comment
            foreach ($comments as &$comment) {
                $comment['replies'] = $this->getCommentReplies((int) $comment['id'], 10);
                $comment['reply_count'] = count($comment['replies']);
                $comment['has_more_replies'] = false; // Could implement pagination for replies later
            }

            return $comments;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get post comments error: '.$e->getMessage());

            return [];
        }
    }

    private function getCommentReplies(int $parentCommentId, int $limit = 10): array
    {
        try {
            $query = "
                SELECT c.id, c.post_id, c.user_id, c.parent_comment_id, c.content,
                       c.is_edited, c.edited_at, c.is_deleted, c.deleted_at, c.deleted_by,
                       c.created_at, c.updated_at, c.like_count,
                       u.username, u.name, u.mi, u.surname,
                       CONCAT(u.name, ' ', IFNULL(CONCAT(u.mi, '. '), ''), u.surname) as commenter_full_name,
                       u.profile_image, u.position,
                       d.department_code
                FROM post_comments c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE c.parent_comment_id = ? AND c.is_deleted = 0
                ORDER BY c.created_at ASC
                LIMIT ?
            ";

            $replies = $this->rowsToArrays(DB::select($query, [$parentCommentId, $limit]));

            // Note: We're keeping replies flat for now (no nested replies to replies)
            foreach ($replies as &$reply) {
                $reply['replies'] = []; // Replies don't have sub-replies in this implementation
                $reply['reply_count'] = 0;
            }

            return $replies;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get comment replies error: '.$e->getMessage());

            return [];
        }
    }

    private function addCommentRecord(int $postId, int $userId, string $content, ?int $parentCommentId = null): int|false
    {
        try {
            $parentCommentId = $parentCommentId ?: null;
            $content = trim($content);

            if (empty($content)) {
                return false;
            }

            // Verify post exists
            $post = DB::selectOne('SELECT id FROM posts WHERE id = ? AND is_deleted = 0', [$postId]);
            if (! $post) {
                \Illuminate\Support\Facades\Log::error("Post $postId not found or deleted");

                return false;
            }

            // If it's a reply, verify parent comment exists and belongs to the same post
            if ($parentCommentId) {
                $parentComment = DB::selectOne(
                    'SELECT post_id FROM post_comments WHERE id = ? AND is_deleted = 0',
                    [$parentCommentId]
                );

                if (! $parentComment || $parentComment->post_id != $postId) {
                    \Illuminate\Support\Facades\Log::error("Parent comment $parentCommentId not found or doesn't belong to post $postId");

                    return false;
                }
            }

            // Insert the comment
            $result = DB::insert("
                INSERT INTO post_comments (post_id, user_id, parent_comment_id, content, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ", [$postId, $userId, $parentCommentId, $content]);

            if ($result) {
                return (int) DB::getPdo()->lastInsertId();
            }

            return false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Add comment error: '.$e->getMessage());

            return false;
        }
    }

    private function getActualCommentCount(int $postId): int
    {
        try {
            $result = DB::selectOne(
                'SELECT COUNT(*) as count FROM post_comments WHERE post_id = ? AND is_deleted = 0',
                [$postId]
            );

            return $result ? (int) $result->count : 0;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get comment count error: '.$e->getMessage());

            return 0;
        }
    }

    private function canUserEditComment(int $commentId, int $userId, string $userRole = 'user'): bool
    {
        try {
            $comment = DB::selectOne('SELECT user_id FROM post_comments WHERE id = ? AND is_deleted = 0', [$commentId]);

            if (! $comment) {
                return false;
            }

            // User can edit if they own the comment OR they have admin privileges
            return ($comment->user_id == $userId) || in_array($userRole, ['admin', 'super_admin']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Check edit comment permission error: '.$e->getMessage());

            return false;
        }
    }

    private function updateCommentRecord(int $commentId, int $userId, string $content, string $userRole = 'user'): bool
    {
        try {
            // Enhanced authorization check
            if (! $this->canUserEditComment($commentId, $userId, $userRole)) {
                \Illuminate\Support\Facades\Log::error("Unauthorized comment edit attempt - Comment ID: $commentId, User ID: $userId, Role: $userRole");

                return false;
            }

            $result = DB::update("
                UPDATE post_comments
                SET content = ?, is_edited = 1, edited_at = NOW(), updated_at = NOW()
                WHERE id = ? AND is_deleted = 0
            ", [$content, $commentId]);

            if ($result) {
                $this->logActivity($userId, 'edit_comment', 'comment', $commentId, 'Edited comment');
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Update comment error: '.$e->getMessage());

            return false;
        }
    }

    private function deleteCommentRecord(int $commentId, int $userId, string $userRole = 'user'): bool
    {
        try {
            // Enhanced authorization check
            if (! $this->canUserEditComment($commentId, $userId, $userRole)) {
                \Illuminate\Support\Facades\Log::error("Unauthorized comment delete attempt - Comment ID: $commentId, User ID: $userId, Role: $userRole");

                return false;
            }

            // Get comment info before deletion
            $comment = DB::selectOne(
                'SELECT user_id, post_id FROM post_comments WHERE id = ? AND is_deleted = 0',
                [$commentId]
            );

            if (! $comment) {
                return false;
            }

            // Soft delete the comment
            $result = DB::update("
                UPDATE post_comments
                SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                WHERE id = ?
            ", [$userId, $commentId]);

            if ($result) {
                // Also soft delete any replies to this comment
                DB::update("
                    UPDATE post_comments
                    SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
                    WHERE parent_comment_id = ?
                ", [$userId, $commentId]);

                $this->logActivity($userId, 'delete_comment', 'comment', $commentId, 'Deleted comment');
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Delete comment error: '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // ReactionManager port
    // ---------------------------------------------------------------------

    private function toggleLikeRecord(int $userId, ?int $postId = null, ?int $commentId = null): bool
    {
        try {
            $target = $postId ? 'post_id' : 'comment_id';
            $targetValue = $postId ?: $commentId;

            // Check if user already liked/reacted
            $existingLike = DB::selectOne(
                "SELECT id, reaction_type FROM post_likes WHERE {$target} = ? AND user_id = ?",
                [$targetValue, $userId]
            );

            if ($existingLike) {
                // Just remove like (no reaction type checking needed)
                $result = DB::delete('DELETE FROM post_likes WHERE id = ?', [$existingLike->id]);
                $action = 'unliked';
            } else {
                // Add new like (always use 'like')
                $result = DB::insert(
                    "INSERT INTO post_likes ({$target}, user_id, reaction_type) VALUES (?, ?, 'like')",
                    [$targetValue, $userId]
                );
                $action = 'liked';

                // Send notification
                if ($result && $postId) {
                    $post = DB::selectOne('SELECT user_id FROM posts WHERE id = ?', [$postId]);

                    if ($post && $post->user_id != $userId) {
                        $this->sendLikeNotification($postId, null, $userId, $post->user_id, 'like');
                    }
                } elseif ($result && $commentId) {
                    $comment = DB::selectOne('SELECT user_id, post_id FROM post_comments WHERE id = ?', [$commentId]);

                    if ($comment && $comment->user_id != $userId) {
                        $this->sendLikeNotification($comment->post_id, $commentId, $userId, $comment->user_id, 'like');
                    }
                }
            }

            if ($result) {
                $this->logActivity(
                    $userId,
                    $action,
                    $postId ? 'post' : 'comment',
                    (int) $targetValue,
                    "User {$action} ".($postId ? 'post' : 'comment')
                );
            }

            return (bool) $result;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Toggle like error: '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // MediaManager port
    // ---------------------------------------------------------------------

    private function getPostMedia(int $postId): array
    {
        try {
            return $this->rowsToArrays(DB::select("
                SELECT * FROM post_media
                WHERE post_id = ?
                ORDER BY sort_order ASC, created_at ASC
            ", [$postId]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Get post media error: '.$e->getMessage());

            return [];
        }
    }

    private function addPostMedia(int $postId, string $mediaType, ?string $filePath, ?string $fileName, ?string $originalName, ?int $fileSize, ?string $mimeType, int $sortOrder = 0): int|false
    {
        try {
            $result = DB::insert("
                INSERT INTO post_media (post_id, media_type, file_path, file_name, original_name, file_size, mime_type, sort_order, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $postId,
                $mediaType,
                $filePath,
                $fileName,
                $originalName,
                $fileSize,
                $mimeType,
                $sortOrder,
            ]);

            if ($result) {
                return (int) DB::getPdo()->lastInsertId();
            }

            return false;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Add post media error: '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // NotificationManager port
    // ---------------------------------------------------------------------

    private function sendPostNotifications(int $postId, int $authorId, string $type = 'new_post'): bool
    {
        try {
            // Get post details
            $post = DB::selectOne('SELECT content, visibility, target_departments FROM posts WHERE id = ?', [$postId]);

            if (! $post) {
                return false;
            }

            $message = 'New post has been shared';

            // For public posts, notify department members or all users based on role
            if ($post->visibility === 'public') {
                $users = DB::select(
                    'SELECT id FROM users WHERE id != ? AND is_approved = 1 LIMIT 100',
                    [$authorId]
                );

                foreach ($users as $user) {
                    $this->insertPostNotification($user->id, $postId, null, $authorId, $type, $message);
                }
            }

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Send post notifications error: '.$e->getMessage());

            return false;
        }
    }

    private function sendDepartmentPostNotifications(int $postId, int $authorId, array $targetDepartments): bool
    {
        try {
            $deptList = implode(',', array_map('intval', $targetDepartments));
            $users = DB::select(
                "SELECT id FROM users WHERE department_id IN ({$deptList}) AND id != ? AND is_approved = 1",
                [$authorId]
            );

            $message = 'New department post has been shared';

            foreach ($users as $user) {
                $this->insertPostNotification($user->id, $postId, null, $authorId, 'new_post', $message);
            }

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Send department post notifications error: '.$e->getMessage());

            return false;
        }
    }

    private function sendCustomPostNotifications(int $postId, int $authorId, array $targetUsers): bool
    {
        try {
            $message = "You've been mentioned in a post";

            foreach ($targetUsers as $targetUserId) {
                if ($targetUserId != $authorId) {
                    $this->insertPostNotification($targetUserId, $postId, null, $authorId, 'new_post', $message);
                }
            }

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Send custom post notifications error: '.$e->getMessage());

            return false;
        }
    }

    private function sendCommentNotification(int $postId, int $commentId, int $commenterId, int $postAuthorId): bool
    {
        try {
            $message = 'Someone commented on your post';

            return $this->insertPostNotification($postAuthorId, $postId, $commentId, $commenterId, 'post_comment', $message);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Send comment notification error: '.$e->getMessage());

            return false;
        }
    }

    private function sendReplyNotification(int $postId, int $commentId, int $replierId, int $parentCommenterId): bool
    {
        try {
            $message = 'Someone replied to your comment';

            return $this->insertPostNotification($parentCommenterId, $postId, $commentId, $replierId, 'comment_reply', $message);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Send reply notification error: '.$e->getMessage());

            return false;
        }
    }

    private function sendLikeNotification(int $postId, ?int $commentId, int $likerId, int $targetUserId, string $reactionType): bool
    {
        try {
            $message = $commentId ? 'Someone liked your comment' : 'Someone liked your post';
            $type = $commentId ? 'comment_like' : 'post_like';

            return $this->insertPostNotification($targetUserId, $postId, $commentId, $likerId, $type, $message);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Send like notification error: '.$e->getMessage());

            return false;
        }
    }

    private function insertPostNotification(int $userId, int $postId, ?int $commentId, int $triggeredBy, string $type, string $message): bool
    {
        try {
            return DB::insert("
                INSERT INTO post_notifications (user_id, post_id, comment_id, triggered_by, notification_type, message)
                VALUES (?, ?, ?, ?, ?, ?)
            ", [$userId, $postId, $commentId, $triggeredBy, $type, $message]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Insert post notification error: '.$e->getMessage());

            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Helper function to get department IDs by department codes
     */
    private function getDepartmentIdsByCodes(array $departmentCodes): array
    {
        if (empty($departmentCodes) || ! is_array($departmentCodes)) {
            return [];
        }

        // Create placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($departmentCodes), '?'));

        $rows = DB::select(
            "SELECT id FROM departments WHERE department_code IN ({$placeholders}) AND is_active = 1",
            array_values($departmentCodes)
        );

        $departmentIds = [];
        foreach ($rows as $row) {
            $departmentIds[] = (int) $row->id;
        }

        return $departmentIds;
    }

    /**
     * DB::select returns stdClass rows; legacy PDO used FETCH_ASSOC, so convert
     * rows to arrays to keep the exact JSON shape the front-end expects.
     */
    private function rowsToArrays(array $rows): array
    {
        return array_map(fn ($row) => json_decode(json_encode($row), true), $rows);
    }

    /**
     * Activity log with request IP / user agent (legacy logActivity port).
     * The legacy resource_type values used here ('post'/'comment') are not in
     * the activity_logs ENUM; ActivityLogger swallows the write failure just
     * like the legacy helper did, so the request never breaks.
     */
    private function logActivity(int $userId, string $action, string $resourceType, ?int $resourceId = null, ?string $description = null, ?array $metadata = null): void
    {
        app(ActivityLogger::class)->logRequest(
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $description,
            $metadata
        );
    }
}