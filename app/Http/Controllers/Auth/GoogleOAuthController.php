<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Port of login/script/google_oauth.php + login/script/google_callback.php.
 *
 * Google's credentials are read from the environment (GOOGLE_CLIENT_ID,
 * GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI) exactly like the legacy app.
 * The login button stays hidden until a client id is configured.
 */
class GoogleOAuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * GET /login/google - build the consent URL and bounce the user to Google.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        $clientId = (string) config('services.google.client_id');

        if ($clientId === '') {
            return redirect()->route('login')->with('error', 'Google sign-in is not configured.');
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return redirect()->away('https://accounts.google.com/o/oauth2/auth?'.http_build_query($params));
    }

    /**
     * GET /login/google/callback - exchange the code, load the profile and
     * either log the user in or create a pending account.
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $request->has('code')) {
            if ($request->filled('error')) {
                Log::error('Google OAuth Error: '.$request->input('error').' - '.$request->input('error_description'));

                if ($request->input('error') === 'access_denied') {
                    return redirect()->route('oauth.google.cancelled');
                }

                return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
            }

            return redirect()->route('login')->with('error', 'No authorization code received from Google.');
        }

        try {
            $tokenData = $this->exchangeCode((string) $request->input('code'));

            if (! $tokenData || empty($tokenData['access_token'])) {
                Log::error('Failed to get access token: '.json_encode($tokenData));

                return redirect()->route('login')->with('error', 'Failed to get access token from Google.');
            }

            $userInfo = $this->fetchUserInfo((string) $tokenData['access_token']);

            if (! $userInfo || empty($userInfo['email'])) {
                Log::error('Failed to get user info: '.json_encode($userInfo));

                return redirect()->route('login')->with('error', 'Failed to get user information from Google.');
            }

            return $this->handleGoogleUser($userInfo);
        } catch (Throwable $e) {
            Log::error('Google OAuth callback exception: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'An error occurred during Google authentication. Please try again.');
        }
    }

    /**
     * GET /login/google/complete - collect the profile details Google does not give us.
     */
    public function showComplete(Request $request)
    {
        $user = $this->pendingGoogleUser();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Please sign in with Google again to finish registering.');
        }

        $departments = \App\Models\Department::query()->where('is_active', 1)->orderBy('department_name')->get();

        return view('auth.google-complete', compact('user', 'departments'));
    }

    /**
     * POST /login/google/complete
     */
    public function storeComplete(Request $request): RedirectResponse
    {
        $user = $this->pendingGoogleUser();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Please sign in with Google again to finish registering.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'mi' => ['nullable', 'string', 'max:5'],
            'surname' => ['required', 'string', 'max:100'],
            'employee_id' => ['required', 'string', 'max:50', 'unique:users,employee_id,'.$user->id],
            'position' => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user->forceFill($data)->save();
        session()->forget('google_complete_user');

        $this->activityLogger->logRequest($user->id, 'register_google_complete', 'user', $user->id, 'Completed Google registration details');

        return redirect()->route('login')->with('success', 'Registration complete! An administrator will review your account before you can sign in.');
    }

    private function pendingGoogleUser(): ?User
    {
        $id = session('google_complete_user');

        return $id ? User::query()->where('id', $id)->where('is_approved', 0)->first() : null;
    }

    /**
     * GET /login/google/cancelled
     */
    public function cancelled(): \Illuminate\Contracts\View\View
    {
        return view('auth.oauth-cancelled');
    }

    private function redirectUri(): string
    {
        return config('services.google.redirect')
            ?: route('oauth.google.callback');
    }

    private function exchangeCode(string $code): array|false
    {
        $response = Http::asForm()
            ->withOptions(['verify' => false])
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

        if (! $response->successful()) {
            Log::error('HTTP Error '.$response->status().': '.$response->body());

            return false;
        }

        return $response->json();
    }

    private function fetchUserInfo(string $accessToken): array|false
    {
        $response = Http::withToken($accessToken)
            ->withOptions(['verify' => false])
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            Log::error('HTTP Error '.$response->status().': '.$response->body());

            return false;
        }

        return $response->json();
    }

    /**
     * Port of GoogleOAuth::handleGoogleUser().
     */
    private function handleGoogleUser(array $userInfo): RedirectResponse
    {
        try {
            if (empty($userInfo['email']) || empty($userInfo['name'])) {
                return redirect()->route('login')->with('error', 'Incomplete user information from Google.');
            }

            $existingUser = User::query()->where('email', $userInfo['email'])->first();

            if ($existingUser) {
                // Legacy: approved users are logged straight in, unapproved
                // accounts get the pending-approval error.
                if ((int) $existingUser->is_approved === 1) {
                    $this->loginUser($existingUser);

                    // Legacy bounced EVERYONE to roles/user/social_feed.php. New
                    // OAuth accounts are always 'user' role, so the legacy and
                    // modern landing pages coincide for them; existing admin /
                    // super-admin accounts get their own dashboard instead of
                    // being dumped into the user feed.
                    $role = $existingUser->role === User::ROLE_USER
                        ? route('user.dashboard')
                        : getDashboardUrl($existingUser->role);

                    return redirect()->to($role);
                }

                if (empty($existingUser->employee_id) || empty($existingUser->department_id)) {
                    session(['google_complete_user' => $existingUser->id]);

                    return redirect()->route('oauth.google.complete');
                }

                return redirect()->route('login')->with('error', 'Your account is pending approval. Please contact the administrator.');
            }

            // New user: create a pending account (is_approved=0, email_verified=1).
            [$firstName, $lastName] = $this->parseFullName((string) $userInfo['name']);
            $username = $this->generateUniqueUsername($firstName, $lastName);

            $user = new User();
            $user->forceFill([
                'username' => $username,
                'email' => $userInfo['email'],
                // Legacy stored a random bcrypt temp password via password_hash.
                // The model's `hashed` cast hashes this once - do NOT pre-hash.
                'password' => bin2hex(random_bytes(32)),
                'name' => $firstName,
                'surname' => $lastName,
                'role' => User::ROLE_USER,
                'is_approved' => 0,
                'email_verified' => 1,
            ])->save();

            $this->activityLogger->logRequest(
                $user->id,
                'register_google',
                'user',
                $user->id,
                'User registered via Google OAuth',
            );

            // Google gives us a name and email only. Collect the rest before the
            // account goes to an administrator for approval.
            session(['google_complete_user' => $user->id]);

            return redirect()->route('oauth.google.complete');
        } catch (Throwable $e) {
            Log::error('Google OAuth handleUser error: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'Registration failed. Please try again later.');
        }
    }

    /** @return array{0: string, 1: string} [first, last] */
    private function parseFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstName = $parts[0] ?? '';
        $lastName = count($parts) > 1 ? (string) end($parts) : '';

        return [$firstName, $lastName];
    }

    private function generateUniqueUsername(string $firstName, string $lastName): string
    {
        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $firstName.$lastName));

        if (strlen($base) < 3) {
            $base = 'user'.$base;
        }

        $username = $base;
        $counter = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = $base.$counter;
            $counter++;
        }

        return $username;
    }

    private function loginUser(User $user): void
    {
        Auth::login($user);

        session()->regenerate();
        session()->put('login_time', time());
        session()->put('last_activity', time());

        // Legacy: UPDATE users SET last_login = NOW() WHERE id = ?
        $user->forceFill(['last_login' => now()])->save();

        $this->activityLogger->logRequest(
            $user->id,
            'login_google',
            'user',
            $user->id,
            'User logged in via Google OAuth',
        );
    }
}