<?php

namespace App\Http\Controllers\SocialFeed;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Port of the three legacy Social Feed pages:
 *
 *   roles/user/social_feed.php       (guard: requireAuth)
 *   roles/admin/social-feed.php      (guard: requireAdmin -> admin,super_admin)
 *   roles/superadmin/social_feed.php (guard: requireAuth)
 *
 * All three render the same shared feed DOM; only the breadcrumb text and
 * the surrounding role layout differ.
 */
class SocialFeedController extends Controller
{
    /**
     * GET /user/social-feed
     */
    public function user(Request $request): View
    {
        return view('user.social_feed');
    }

    /**
     * GET /admin/social-feed
     */
    public function admin(Request $request): View
    {
        return view('admin.social-feed');
    }

    /**
     * GET /superadmin/social-feed
     */
    public function superadmin(Request $request): View
    {
        return view('superadmin.social_feed');
    }
}