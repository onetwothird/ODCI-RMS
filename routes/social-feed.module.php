<?php

use App\Http\Controllers\SocialFeedController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Social Feed module
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   roles/user/social_feed.php      -> GET  /user/social-feed
|   roles/admin/social-feed.php     -> GET  /admin/social-feed
|   roles/superadmin/social_feed.php-> GET  /superadmin/social-feed
|
| API endpoints (used by the shared JS):
|   social_feed/api/create_post.php   -> POST   /social-feed/posts
|   social_feed/api/get_posts.php     -> GET    /social-feed/posts
|   social_feed/api/get_post.php      -> GET    /social-feed/posts/{post}
|   social_feed/api/update_post.php   -> PUT    /social-feed/posts/{post}
|   social_feed/api/delete_post.php   -> DELETE /social-feed/posts/{post}
|   social_feed/api/toggle_like.php   -> POST   /social-feed/posts/{post}/like
|   social_feed/api/add_comment.php   -> POST   /social-feed/comments
|   social_feed/api/get_comments.php  -> GET    /social-feed/posts/{post}/comments
|   social_feed/api/edit_comment.php  -> PUT    /social-feed/comments/{comment}
|   social_feed/api/delete_comment.php-> DELETE /social-feed/comments/{comment}
|   social_feed/api/toggle_like.php   -> POST   /social-feed/comments/{comment}/like
|   social_feed/api/reply_comment.php -> POST   /social-feed/comments/{comment}/reply
|   social_feed/api/get_replies.php   -> GET    /social-feed/comments/{comment}/replies
|   social_feed/api/get_user_info.php -> GET    /social-feed/user/{user}
|   social_feed/api/get_notification_count.php -> GET /social-feed/notifications/count
|
| Loaded from routes/web.php inside the shared 'auth' + 'active.account' group.
| All routes protected by auth + active.account middleware.
| Page routes additionally gated by role.
*/

Route::middleware(['auth', 'active.account'])->group(function () {
    // Pages - one per role, same view, different layout
    Route::get('/user/social-feed', [SocialFeedController::class, 'index'])
        ->middleware('role:user')
        ->name('user.social-feed');

    Route::get('/admin/social-feed', [SocialFeedController::class, 'index'])
        ->middleware('role:admin,super_admin')
        ->name('admin.social-feed');

    Route::get('/superadmin/social-feed', [SocialFeedController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('superadmin.social-feed');

    // API endpoints (role-agnostic; JS enforces visibility via Post::visibleTo())
    Route::prefix('social-feed')->group(function () {
        Route::get('/posts', [SocialFeedController::class, 'getPosts'])
            ->name('social-feed.posts.index');
        Route::post('/posts', [SocialFeedController::class, 'storePost'])
            ->name('social-feed.posts.store');
        Route::get('/posts/{post}', [SocialFeedController::class, 'getPost'])
            ->whereNumber('post')
            ->name('social-feed.posts.show');
        Route::put('/posts/{post}', [SocialFeedController::class, 'updatePost'])
            ->whereNumber('post')
            ->name('social-feed.posts.update');
        Route::delete('/posts/{post}', [SocialFeedController::class, 'deletePost'])
            ->whereNumber('post')
            ->name('social-feed.posts.destroy');

        Route::post('/posts/{post}/like', [SocialFeedController::class, 'toggleLike'])
            ->whereNumber('post')
            ->name('social-feed.posts.like');

        Route::post('/comments', [SocialFeedController::class, 'addComment'])
            ->name('social-feed.comments.store');
        Route::get('/posts/{post}/comments', [SocialFeedController::class, 'getComments'])
            ->whereNumber('post')
            ->name('social-feed.comments.index');
        Route::put('/comments/{comment}', [SocialFeedController::class, 'editComment'])
            ->whereNumber('comment')
            ->name('social-feed.comments.update');
        Route::delete('/comments/{comment}', [SocialFeedController::class, 'deleteComment'])
            ->whereNumber('comment')
            ->name('social-feed.comments.destroy');
        Route::post('/comments/{comment}/like', [SocialFeedController::class, 'toggleCommentLike'])
            ->whereNumber('comment')
            ->name('social-feed.comments.like');
        Route::post('/comments/{comment}/reply', [SocialFeedController::class, 'replyComment'])
            ->whereNumber('comment')
            ->name('social-feed.comments.reply');
        Route::get('/comments/{comment}/replies', [SocialFeedController::class, 'getReplies'])
            ->whereNumber('comment')
            ->name('social-feed.comments.replies');

        Route::get('/user/{user}', [SocialFeedController::class, 'getUserInfo'])
            ->whereNumber('user')
            ->name('social-feed.user.info');

        Route::get('/notifications/count', [SocialFeedController::class, 'getNotificationCount'])
            ->name('social-feed.notifications.count');
    });
});