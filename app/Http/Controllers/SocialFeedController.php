<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostMedia;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * Social Feed module — port of social_feed/ PHP + JS.
 *
 * All three roles share the same feed UI; the only difference is the
 * surrounding layout (user/admin/superadmin). The legacy JS in
 * public/js/social_feed/* is used as-is and expects the same JSON
 * payloads the legacy API endpoints returned.
 */
class SocialFeedController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * GET /user/social-feed  |  /admin/social-feed  |  /superadmin/social-feed
     *
     * The view is identical for all roles — only the layout differs.
     * Route middleware enforces role gates.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $departments = DB::table('departments')
            ->where('is_active', 1)
            ->orderBy('department_name')
            ->get(['id', 'department_code', 'department_name']);

        return view('social-feed.index', [
            'currentUser' => $user,
            'departments' => $departments,
        ]);
    }

    // -----------------------------------------------------------------
    // API endpoints (consumed by the legacy JS modules)
    // -----------------------------------------------------------------

    /**
     * POST /social-feed/posts
     * Legacy: social_feed/api/create_post.php
     */
    public function storePost(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
            'visibility' => 'required|in:public,department,private,custom',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'integer|exists:departments,id',
            'target_users' => 'nullable|array',
            'target_users.*' => 'integer|exists:users,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'media' => 'nullable|array',
            'media.*.media_type' => 'required_with:media|in:image,file,link',
            'media.*.file_path' => 'nullable|string',
            'media.*.file_name' => 'nullable|string',
            'media.*.original_name' => 'nullable|string',
            'media.*.file_size' => 'nullable|integer',
            'media.*.mime_type' => 'nullable|string',
            'media.*.url' => 'nullable|url',
            'media.*.url_title' => 'nullable|string',
            'media.*.url_description' => 'nullable|string',
            'media.*.url_image' => 'nullable|string',
            'media.*.thumbnail_path' => 'nullable|string',
            'media.*.sort_order' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            $post = Post::create([
                'user_id' => $user->id,
                'content' => $validated['content'],
                'content_type' => $this->determineContentType($validated['media'] ?? []),
                'visibility' => $validated['visibility'],
                'target_departments' => $validated['target_departments'] ?? [],
                'target_users' => $validated['target_users'] ?? [],
                'priority' => $validated['priority'] ?? 'normal',
                'is_pinned' => 0,
            ]);

            if (!empty($validated['media'])) {
                foreach ($validated['media'] as $index => $media) {
                    $media['post_id'] = $post->id;
                    $media['sort_order'] = $media['sort_order'] ?? $index;
                    PostMedia::create($media);
                }
            }

            DB::commit();

            $this->activity->logRequest(
                $user->id,
                'create_post',
                'post',
                $post->id,
                'Created social feed post',
                ['visibility' => $post->visibility]
            );

            return response()->json([
                'success' => true,
                'post' => $this->loadPostForResponse($post->id, $user),
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Create post error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to create post'], 500);
        }
    }

    /**
     * GET /social-feed/posts
     * Legacy: social_feed/api/get_posts.php
     */
    public function getPosts(Request $request): JsonResponse
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all'); // all, my_posts, department, pinned
        $page = (int) $request->query('page', 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $query = Post::query()
            ->notDeleted()
            ->with([
                'author:id,username,name,mi,surname,profile_image,department_id',
                'media',
                'likes' => fn($q) => $q->where('comment_id', null),
            ])
            ->visibleTo($user);

        // Apply filter
        match ($filter) {
            'my_posts' => $query->where('user_id', $user->id),
            'department' => $query->where(function ($q) use ($user) {
                if ($user->department_id) {
                    $q->where('visibility', 'department')
                        ->whereRaw('JSON_CONTAINS(target_departments, ?, "$")', [json_encode($user->department_id)]);
                }
            }),
            'pinned' => $query->where('is_pinned', 1),
            default => null, // 'all' = no extra filter
        };

        $total = $query->count();
        $posts = $query->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get();

        $postsData = $posts->map(fn($p) => $this->formatPost($p, $user))->all();

        return response()->json([
            'success' => true,
            'posts' => $postsData,
            'has_more' => ($offset + $limit) < $total,
            'total' => $total,
        ]);
    }

    /**
     * GET /social-feed/posts/{post}
     * Legacy: social_feed/api/get_post.php
     */
    public function getPost(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();

        $post = Post::query()
            ->notDeleted()
            ->with([
                'author:id,username,name,mi,surname,profile_image,department_id',
                'media',
                'likes' => fn($q) => $q->where('comment_id', null),
            ])
            ->visibleTo($user)
            ->find($postId);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        return response()->json([
            'success' => true,
            'post' => $this->formatPost($post, $user),
        ]);
    }

    /**
     * PUT /social-feed/posts/{post}
     * Legacy: social_feed/api/update_post.php
     */
    public function updatePost(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();

        $post = Post::query()->notDeleted()->find($postId);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        if ($post->user_id !== $user->id && !$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
            'visibility' => 'required|in:public,department,private,custom',
            'target_departments' => 'nullable|array',
            'target_departments.*' => 'integer|exists:departments,id',
            'target_users' => 'nullable|array',
            'target_users.*' => 'integer|exists:users,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ]);

        $post->update([
            'content' => $validated['content'],
            'visibility' => $validated['visibility'],
            'target_departments' => $validated['target_departments'] ?? [],
            'target_users' => $validated['target_users'] ?? [],
            'priority' => $validated['priority'] ?? 'normal',
            'is_edited' => 1,
            'edited_at' => now(),
        ]);

        $this->activity->logRequest(
            $user->id,
            'update_post',
            'post',
            $post->id,
            'Updated social feed post'
        );

        return response()->json([
            'success' => true,
            'post' => $this->loadPostForResponse($post->id, $user),
        ]);
    }

    /**
     * DELETE /social-feed/posts/{post}
     * Legacy: social_feed/api/delete_post.php
     */
    public function deletePost(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();

        $post = Post::query()->notDeleted()->find($postId);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        if ($post->user_id !== $user->id && !$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $post->update([
            'is_deleted' => 1,
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);

        $this->activity->logRequest(
            $user->id,
            'delete_post',
            'post',
            $post->id,
            'Deleted social feed post'
        );

        return response()->json(['success' => true, 'message' => 'Post deleted']);
    }

    /**
     * POST /social-feed/posts/{post}/like
     * Legacy: social_feed/api/toggle_like.php
     */
    public function toggleLike(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();
        $reaction = $request->input('reaction_type', 'like');

        $post = Post::query()->notDeleted()->find($postId);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $existing = PostLike::query()
            ->where('post_id', $postId)
            ->where('user_id', $user->id)
            ->whereNull('comment_id')
            ->first();

        if ($existing) {
            if ($existing->reaction_type === $reaction) {
                // Toggle off
                $existing->delete();
                $post->decrement('like_count');
                $liked = false;
            } else {
                // Change reaction
                $existing->update(['reaction_type' => $reaction]);
                $liked = true;
            }
        } else {
            // New like
            PostLike::create([
                'post_id' => $postId,
                'user_id' => $user->id,
                'reaction_type' => $reaction,
            ]);
            $post->increment('like_count');
            $liked = true;
        }

        $this->activity->logRequest(
            $user->id,
            $liked ? 'like_post' : 'unlike_post',
            'post',
            $postId,
            $liked ? "Reacted with {$reaction}" : 'Removed reaction'
        );

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'like_count' => $post->fresh()->like_count,
            'reaction_type' => $liked ? $reaction : null,
        ]);
    }

    /**
     * POST /social-feed/comments
     * Legacy: social_feed/api/add_comment.php
     */
    public function addComment(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'content' => 'required|string|max:5000',
            'parent_comment_id' => 'nullable|integer|exists:post_comments,id',
        ]);

        $post = Post::query()->notDeleted()->find($validated['post_id']);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        // Check visibility
        $visible = Post::query()
            ->notDeleted()
            ->visibleTo($user)
            ->where('id', $post->id)
            ->exists();

        if (!$visible) {
            return response()->json(['success' => false, 'message' => 'Cannot comment on this post'], 403);
        }

        $comment = PostComment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_comment_id' => $validated['parent_comment_id'] ?? null,
            'content' => $validated['content'],
        ]);

        $post->increment('comment_count');

        $this->activity->logRequest(
            $user->id,
            'add_comment',
            'post_comment',
            $comment->id,
            'Added comment to post'
        );

        return response()->json([
            'success' => true,
            'comment' => $this->formatComment($comment, $user),
            'comment_count' => $post->fresh()->comment_count,
        ]);
    }

    /**
     * GET /social-feed/posts/{post}/comments
     * Legacy: social_feed/api/get_comments.php
     */
    public function getComments(Request $request, int $postId): JsonResponse
    {
        $user = $request->user();

        $post = Post::query()->notDeleted()->find($postId);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $visible = Post::query()
            ->notDeleted()
            ->visibleTo($user)
            ->where('id', $postId)
            ->exists();

        if (!$visible) {
            return response()->json(['success' => false, 'message' => 'Cannot view comments'], 403);
        }

        $comments = PostComment::query()
            ->notDeleted()
            ->where('post_id', $postId)
            ->whereNull('parent_comment_id')
            ->with([
                'author:id,username,name,mi,surname,profile_image',
                'replies' => fn($q) => $q->notDeleted()
                    ->with('author:id,username,name,mi,surname,profile_image')
                    ->orderBy('created_at'),
                'likes' => fn($q) => $q->whereNull('comment_id')->where('comment_id', '!=', null),
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'comments' => $comments->map(fn($c) => $this->formatComment($c, $user))->all(),
        ]);
    }

    /**
     * PUT /social-feed/comments/{comment}
     * Legacy: social_feed/api/edit_comment.php
     */
    public function editComment(Request $request, int $commentId): JsonResponse
    {
        $user = $request->user();

        $comment = PostComment::query()->notDeleted()->find($commentId);

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        if ($comment->user_id !== $user->id && !$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $comment->update([
            'content' => $validated['content'],
            'is_edited' => 1,
            'edited_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'comment' => $this->formatComment($comment->fresh(), $user),
        ]);
    }

    /**
     * DELETE /social-feed/comments/{comment}
     * Legacy: social_feed/api/delete_comment.php
     */
    public function deleteComment(Request $request, int $commentId): JsonResponse
    {
        $user = $request->user();

        $comment = PostComment::query()->notDeleted()->find($commentId);

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        if ($comment->user_id !== $user->id && !$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $post = $comment->post;

        $comment->update([
            'is_deleted' => 1,
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);

        if ($post) {
            $post->decrement('comment_count');
        }

        $this->activity->logRequest(
            $user->id,
            'delete_comment',
            'post_comment',
            $commentId,
            'Deleted comment'
        );

        return response()->json(['success' => true, 'message' => 'Comment deleted']);
    }

    /**
     * POST /social-feed/comments/{comment}/like
     * Legacy: social_feed/api/toggle_like.php (with comment_id)
     */
    public function toggleCommentLike(Request $request, int $commentId): JsonResponse
    {
        $user = $request->user();
        $reaction = $request->input('reaction_type', 'like');

        $comment = PostComment::query()->notDeleted()->find($commentId);

        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        $existing = PostLike::query()
            ->where('comment_id', $commentId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if ($existing->reaction_type === $reaction) {
                $existing->delete();
                $comment->decrement('like_count');
                $liked = false;
            } else {
                $existing->update(['reaction_type' => $reaction]);
                $liked = true;
            }
        } else {
            PostLike::create([
                'comment_id' => $commentId,
                'user_id' => $user->id,
                'reaction_type' => $reaction,
            ]);
            $comment->increment('like_count');
            $liked = true;
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'like_count' => $comment->fresh()->like_count,
            'reaction_type' => $liked ? $reaction : null,
        ]);
    }

    /**
     * POST /social-feed/comments/{comment}/reply
     * Legacy: social_feed/api/reply_comment.php
     */
    public function replyComment(Request $request, int $commentId): JsonResponse
    {
        $user = $request->user();

        $parent = PostComment::query()->notDeleted()->find($commentId);

        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $reply = PostComment::create([
            'post_id' => $parent->post_id,
            'user_id' => $user->id,
            'parent_comment_id' => $parent->id,
            'content' => $validated['content'],
        ]);

        $post = $parent->post;
        if ($post) {
            $post->increment('comment_count');
        }

        return response()->json([
            'success' => true,
            'reply' => $this->formatComment($reply, $user),
        ]);
    }

    /**
     * GET /social-feed/comments/{comment}/replies
     * Legacy: social_feed/api/get_replies.php
     */
    public function getReplies(Request $request, int $commentId): JsonResponse
    {
        $user = $request->user();

        $replies = PostComment::query()
            ->notDeleted()
            ->where('parent_comment_id', $commentId)
            ->with([
                'author:id,username,name,mi,surname,profile_image',
                'likes' => fn($q) => $q->whereNull('comment_id'),
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'replies' => $replies->map(fn($r) => $this->formatComment($r, $user))->all(),
        ]);
    }

    /**
     * GET /social-feed/user/{userId}
     * Legacy: social_feed/api/get_user_info.php
     */
    public function getUserInfo(Request $request, int $userId): JsonResponse
    {
        $user = User::query()
            ->select('id', 'username', 'name', 'mi', 'surname', 'profile_image', 'department_id', 'role')
            ->find($userId);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'profile_image_url' => $user->profile_image_url,
                'initials' => $user->initials,
                'department_id' => $user->department_id,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * GET /social-feed/notifications/count
     * Legacy: social_feed/api/get_notification_count.php
     */
    public function getNotificationCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = DB::table('post_notifications')
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function determineContentType(?array $media): string
    {
        if (empty($media)) {
            return 'text';
        }

        $types = array_column($media, 'media_type');
        $unique = array_unique($types);

        if (count($unique) === 1) {
            return $unique[0] === 'link' ? 'link' : $unique[0];
        }

        return 'mixed';
    }

    private function formatPost(Post $post, User $viewer): array
    {
        $userLiked = PostLike::query()
            ->where('post_id', $post->id)
            ->where('user_id', $viewer->id)
            ->whereNull('comment_id')
            ->first();

        return [
            'id' => $post->id,
            'user_id' => $post->user_id,
            'author' => $post->author ? [
                'id' => $post->author->id,
                'username' => $post->author->username,
                'full_name' => $post->author->full_name,
                'profile_image_url' => $post->author->profile_image_url,
                'initials' => $post->author->initials,
                'department_id' => $post->author->department_id,
            ] : null,
            'content' => $post->content,
            'content_type' => $post->content_type,
            'visibility' => $post->visibility,
            'target_departments' => $post->target_departments ?? [],
            'target_users' => $post->target_users ?? [],
            'priority' => $post->priority,
            'is_pinned' => (bool) $post->is_pinned,
            'is_edited' => (bool) $post->is_edited,
            'edited_at' => $post->edited_at?->format('Y-m-d H:i:s'),
            'created_at' => $post->created_at?->format('Y-m-d H:i:s'),
            'like_count' => (int) $post->like_count,
            'comment_count' => (int) $post->comment_count,
            'view_count' => (int) $post->view_count,
            'share_count' => (int) $post->share_count,
            'user_liked' => $userLiked !== null,
            'user_reaction' => $userLiked?->reaction_type ?? null,
            'media' => $post->media->map(fn($m) => [
                'id' => $m->id,
                'media_type' => $m->media_type,
                'file_path' => $m->file_path,
                'file_name' => $m->file_name,
                'original_name' => $m->original_name,
                'file_size' => (int) $m->file_size,
                'mime_type' => $m->mime_type,
                'url' => $m->url,
                'url_title' => $m->url_title,
                'url_description' => $m->url_description,
                'url_image' => $m->url_image,
                'thumbnail_path' => $m->thumbnail_path,
                'sort_order' => (int) $m->sort_order,
            ])->all(),
            'can_edit' => $post->user_id === $viewer->id || $viewer->isAdmin() || $viewer->isSuperAdmin(),
            'can_delete' => $post->user_id === $viewer->id || $viewer->isAdmin() || $viewer->isSuperAdmin(),
        ];
    }

    private function formatComment(PostComment $comment, User $viewer): array
    {
        $userLiked = PostLike::query()
            ->where('comment_id', $comment->id)
            ->where('user_id', $viewer->id)
            ->first();

        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'user_id' => $comment->user_id,
            'parent_comment_id' => $comment->parent_comment_id,
            'author' => $comment->author ? [
                'id' => $comment->author->id,
                'username' => $comment->author->username,
                'full_name' => $comment->author->full_name,
                'profile_image_url' => $comment->author->profile_image_url,
                'initials' => $comment->author->initials,
            ] : null,
            'content' => $comment->content,
            'is_edited' => (bool) $comment->is_edited,
            'edited_at' => $comment->edited_at?->format('Y-m-d H:i:s'),
            'created_at' => $comment->created_at?->format('Y-m-d H:i:s'),
            'like_count' => (int) $comment->like_count,
            'user_liked' => $userLiked !== null,
            'user_reaction' => $userLiked?->reaction_type ?? null,
            'replies_count' => $comment->replies()->notDeleted()->count(),
            'can_edit' => $comment->user_id === $viewer->id || $viewer->isAdmin() || $viewer->isSuperAdmin(),
            'can_delete' => $comment->user_id === $viewer->id || $viewer->isAdmin() || $viewer->isSuperAdmin(),
        ];
    }

    private function loadPostForResponse(int $postId, User $user): array
    {
        $post = Post::query()
            ->notDeleted()
            ->with([
                'author:id,username,name,mi,surname,profile_image,department_id',
                'media',
                'likes' => fn($q) => $q->where('comment_id', null),
            ])
            ->find($postId);

        return $this->formatPost($post, $user);
    }
}