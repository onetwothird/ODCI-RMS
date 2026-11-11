{{-- Navbar Component - port of roles/user/components/navbar.html --}}
@php
    $user = auth()->user();
    $fullName = $user->full_name;
    $userName = $fullName ?: $user->username;
    $userRole = $user->position ?: ucwords($user->role);
    $userDepartment = $user->department?->department_name ?? '';
    $profileImageUrl = $user->profile_image_url;
    $defaultProfileImage = asset('img/cvsu-logo.png');
    // Legacy: SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0
    $notificationCount = \App\Models\Notification::query()
        ->where('user_id', $user->id)
        ->unread()
        ->count();
@endphp

<nav class="app-navbar user-navbar">
    <div class="nav-left">
        <button type="button" class="toggle-sidebar" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
            <i class='bx bx-menu' aria-hidden="true"></i>
        </button>
    </div>

    <div class="nav-right">
        <!-- Quick Actions -->
        <div class="nav-actions">
            <button type="button" class="nav-action-btn" title="New Post" aria-label="Create a post" data-action="new-post">
                <i class='bx bx-plus' aria-hidden="true"></i>
            </button>

            <button type="button" class="nav-action-btn" title="Upload File" aria-label="Upload a file" data-action="upload">
                <i class='bx bx-upload' aria-hidden="true"></i>
            </button>
        </div>

        <!-- Notifications -->
        <div class="notification-container">
            <button class="notification-btn" type="button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" aria-controls="notificationDropdown">
                <i class='bx bx-bell'></i>
                <span class="notification-badge" {{ $notificationCount > 0 ? '' : 'hidden' }}>{{ $notificationCount }}</span>
            </button>
            <div class="notification-dropdown" id="notificationDropdown" data-endpoint="#" data-feed-url="#">
                <div class="notification-header">
                    <h4>Notifications</h4>
                    <button class="mark-all-read" type="button">Mark all read</button>
                </div>
                <div class="notification-list" id="notificationList" aria-live="polite">
                    <div class="cvsu-notification-loading" aria-label="Loading notifications">
                        <span></span><span></span><span></span>
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="{{ route('user.social-feed') }}">Open Social Feed</a>
                </div>
            </div>
        </div>

        <!-- User Profile -->
        <div class="profile-section">
            <div class="profile-info">
                <span class="user-name">{{ $userName }}</span>
                <span class="user-department">{{ $userDepartment ?: $userRole }}</span>
            </div>

            <a href="#" class="profile-image">
                @if ($profileImageUrl)
                    <img src="{{ $profileImageUrl }}" alt="{{ $userName }}" loading="lazy">
                @else
                    <img src="{{ $defaultProfileImage }}" alt="{{ $userName }}" loading="lazy">
                @endif
            </a>

            <div class="profile-dropdown">
                <div class="dropdown-item">
                    <i class='bx bx-cog'></i>
                    <a href="{{ route('user.settings') }}">Account Settings</a>
                </div>
                <div class="dropdown-divider"></div>
                <div class="dropdown-item">
                    <i class='bx bx-log-out'></i>
                    <a href="{{ route('logout-link') }}" class="dropdown-link">Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>
