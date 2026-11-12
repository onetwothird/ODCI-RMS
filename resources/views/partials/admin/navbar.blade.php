{{-- Navbar Component - port of roles/admin/components/navbar.html --}}
@php
    $user = auth()->user();
    $fullName = $user->full_name;
    $userName = $fullName ?: $user->username;
    $userRole = ucwords(str_replace('_', ' ', $user->role));
    $profileImageUrl = $user->profile_image_url;
    $defaultProfileImage = asset('img/cvsu-logo.png');
@endphp

<nav class="app-navbar superadmin-navbar">
    <div class="nav-left">
        <button type="button" class="toggle-sidebar" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false">
            <i class='bx bx-menu' aria-hidden="true"></i>
        </button>
    </div>

    <div class="nav-right">
        <div class="notification-container">
            <button class="notification-btn" type="button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" aria-controls="notificationDropdown">
                <i class='bx bx-bell' aria-hidden="true"></i>
                <span class="notification-badge" hidden>0</span>
            </button>
            <div class="notification-dropdown" id="notificationDropdown" data-endpoint="{{ route('notifications.index') }}" data-feed-url="{{ route('admin.social-feed') }}">
                <div class="notification-header">
                    <h4>Notifications</h4>
                    <button class="mark-all-read" type="button">Mark all read</button>
                </div>
                <div class="notification-list" id="notificationList" data-csrf-token="{{ csrf_token() }}" aria-live="polite">
                    <div class="cvsu-notification-loading" aria-label="Loading notifications"><span></span><span></span><span></span></div>
                </div>
                <div class="notification-footer"><a href="{{ route('admin.social-feed') }}">Open Social Feed</a></div>
            </div>
        </div>

        <div class="admin-quick-access">
            <a href="{{ route('admin.dashboard') }}" class="quick-link" title="Dashboard">
                <i class='bx bx-home'></i>
            </a>
            {{-- Legacy: ../admin/profile.php --}}
            <a href="{{ route('admin.profile') }}" class="quick-link" title="My Profile">
                <i class='bx bx-cog'></i>
            </a>
        </div>

        <div class="profile-section">
            <div class="profile-info">
                <span class="admin-name">{{ $userName }}</span>
                <span class="admin-role">{{ $userRole }}</span>
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
                    <i class='bx bx-user'></i>
                    <a href="{{ route('admin.profile') }}">My Profile</a>
                </div>
                <div class="dropdown-item">
                    <i class='bx bx-shield'></i>
                    <a href="{{ route('admin.view-faculty-staff') }}">Users Management</a>
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
