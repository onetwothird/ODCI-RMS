<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Header bell dropdown. Port of legacy includes/notifications_endpoint.php:
 * system notifications and social (post) notifications in one feed.
 */
class NotificationController extends Controller
{
    private const SOCIAL_TITLES = [
        'new_post' => 'New post',
        'post_comment' => 'Post comment',
        'post_like' => 'Post reaction',
        'comment_like' => 'Comment reaction',
        'comment_reply' => 'Comment reply',
        'post_mention' => 'Post mention',
        'comment_mention' => 'Comment mention',
    ];

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $system = DB::table('notifications')
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->select('id', 'title', 'message', 'type', 'is_read', 'created_at')
            ->orderByDesc('created_at')->limit(12)->get()
            ->map(fn ($n) => ['id' => $n->id, 'source' => 'system', 'title' => $n->title, 'message' => $n->message,
                'type' => $n->type, 'is_read' => (int) $n->is_read, 'created_at' => $n->created_at]);

        $social = DB::table('post_notifications')
            ->where('user_id', $userId)
            ->select('id', 'notification_type', 'message', 'is_read', 'created_at')
            ->orderByDesc('created_at')->limit(12)->get()
            ->map(fn ($n) => ['id' => $n->id, 'source' => 'social',
                'title' => self::SOCIAL_TITLES[$n->notification_type] ?? 'Social activity', 'message' => $n->message,
                'type' => $n->notification_type, 'is_read' => (int) $n->is_read, 'created_at' => $n->created_at]);

        $items = $system->concat($social)->sortByDesc('created_at')->take(12)->values();

        $unread = DB::table('notifications')->where('user_id', $userId)->where('is_read', 0)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count()
            + DB::table('post_notifications')->where('user_id', $userId)->where('is_read', 0)->count();

        return response()->json(['success' => true, 'items' => $items, 'unreadCount' => $unread]);
    }

    public function update(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $action = $request->input('action');

        if ($action === 'mark_all_read') {
            foreach (['notifications', 'post_notifications'] as $table) {
                DB::table($table)->where('user_id', $userId)->where('is_read', 0)
                    ->update(['is_read' => 1, 'read_at' => now()]);
            }

            return response()->json(['success' => true]);
        }

        if ($action === 'mark_read') {
            $id = filter_var($request->input('id'), FILTER_VALIDATE_INT);
            $source = $request->input('source');
            if (! $id || ! in_array($source, ['system', 'social'], true)) {
                return response()->json(['success' => false, 'message' => 'A valid notification is required.'], 400);
            }
            DB::table($source === 'system' ? 'notifications' : 'post_notifications')
                ->where('id', $id)->where('user_id', $userId)
                ->update(['is_read' => 1, 'read_at' => now()]);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid notification action.'], 400);
    }
}
