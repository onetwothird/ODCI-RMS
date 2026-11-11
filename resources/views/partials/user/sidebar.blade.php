{{-- Sidebar Component - port of roles/user/components/sidebar.html --}}
<section id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('user.dashboard') }}" class="brand">
            <img src="{{ asset('img/cvsu-logo.png') }}" alt="CVSU logo">
            <span class="text">
                <span class="brand-title">ODCI Document Hub</span>
                <span class="brand-subtitle">CVSU Naic</span>
            </span>
        </a>
        <button class="cvsu-drawer-back" type="button" aria-label="Back to page">
            <i class="bx bx-arrow-back" aria-hidden="true"></i>
        </button>
    </div>

    <ul class="side-menu top">
        <li class="{{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
            <a href="{{ route('user.dashboard') }}" class="ajax-link" data-title="Dashboard">
                <i class='bx bxs-dashboard'></i>
                <span class="text">Dashboard</span>
            </a>
            <div class="tooltip">Dashboard</div>
        </li>
        {{-- Legacy: social_feed.php --}}
        <li class="{{ request()->routeIs('user.social-feed') ? 'active' : '' }}">
            <a href="{{ route('user.social-feed') }}" class="ajax-link" data-title="Social Feed">
                <i class='bx bx-news'></i>
                <span class="text">Social Feed</span>
            </a>
            <div class="tooltip">Social Feed</div>
        </li>
        {{-- Legacy: colleagues.php --}}
        <li class="{{ request()->routeIs('user.colleagues') ? 'active' : '' }}">
            <a href="{{ route('user.colleagues') }}" class="ajax-link" data-title="Colleagues">
                <i class='bx bxs-group'></i>
                <span class="text">Colleagues</span>
            </a>
            <div class="tooltip">Colleagues</div>
        </li>
        {{-- Legacy: folders.php --}}
        <li class="{{ request()->routeIs('user.folders') ? 'active' : '' }}">
            <a href="{{ route('user.folders') }}" class="ajax-link" data-title="Department Folders">
                <i class='bx bxs-folder'></i>
                <span class="text">Department Folders</span>
            </a>
            <div class="tooltip">Department Folders</div>
        </li>
        {{-- Legacy: my_reports.php --}}
        <li class="{{ request()->routeIs('user.my-reports') ? 'active' : '' }}">
            <a href="{{ route('user.my-reports') }}" class="ajax-link" data-title="My Reports">
                <i class='bx bxs-report'></i>
                <span class="text">My Reports</span>
            </a>
            <div class="tooltip">My Reports</div>
        </li>

        <li class="menu-divider" aria-hidden="true"></li>

        {{-- Legacy: settings.php --}}
        <li class="{{ request()->routeIs('user.settings') ? 'active' : '' }}">
            <a href="{{ route('user.settings') }}" class="ajax-link" data-title="Settings">
                <i class='bx bxs-cog'></i>
                <span class="text">Settings</span>
            </a>
            <div class="tooltip">Settings</div>
        </li>
        <li class="logout-item">
            <a href="{{ route('logout-link') }}" class="logout ajax-link" data-title="Logout">
                <i class='bx bx-log-out'></i>
                <span class="text">Logout</span>
            </a>
            <div class="tooltip">Logout</div>
        </li>
    </ul>
</section>
