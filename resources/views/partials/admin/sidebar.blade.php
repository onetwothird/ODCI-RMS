{{-- Sidebar Component - port of roles/admin/components/sidebar.html --}}
<section id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('admin.dashboard') }}" class="brand">
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
        <li>
            <a href="{{ route('admin.dashboard') }}" class="ajax-link" data-title="Dashboard">
                <i class='bx bxs-dashboard'></i>
                <span class="text">Dashboard</span>
            </a>
            <div class="tooltip">Dashboard</div>
        </li>
        {{-- Legacy: social-feed.php --}}
        <li class="{{ request()->routeIs('admin.social-feed') ? 'active' : '' }}">
            <a href="{{ route('admin.social-feed') }}" class="ajax-link" data-title="Social Feed">
                <i class='bx bx-news'></i>
                <span class="text">Social Feed</span>
            </a>
            <div class="tooltip">Social Feed</div>
        </li>
        {{-- Legacy: document-tracker.php --}}
        <li class="{{ request()->routeIs('admin.document-tracker') ? 'active' : '' }}">
            <a href="{{ route('admin.document-tracker') }}" class="ajax-link" data-title="Document Tracker">
                <i class='bx bxs-file-find'></i>
                <span class="text">Document Tracker</span>
            </a>
            <div class="tooltip">Document Tracker</div>
        </li>
        {{-- Legacy: view-faculty-staff.php --}}
        <li class="{{ request()->routeIs('admin.view-faculty-staff') ? 'active' : '' }}">
            <a href="{{ route('admin.view-faculty-staff') }}" class="ajax-link" data-title="View Faculty Staff">
                <i class='bx bxs-group'></i>
                <span class="text">View Faculty Staff</span>
            </a>
            <div class="tooltip">View Faculty Staff</div>
        </li>
        {{-- Legacy: files.php --}}
        <li class="{{ request()->routeIs('admin.files') ? 'active' : '' }}">
            <a href="{{ route('admin.files') }}" class="ajax-link" data-title="All Files">
                <i class='bx bxs-file'></i>
                <span class="text">All Files</span>
            </a>
            <div class="tooltip">All Files</div>
        </li>
        {{-- Legacy: folders.php --}}
        <li class="{{ request()->routeIs('admin.folders') ? 'active' : '' }}">
            <a href="{{ route('admin.folders') }}" class="ajax-link" data-title="All Folders">
                <i class='bx bxs-folder'></i>
                <span class="text">All Folders</span>
            </a>
            <div class="tooltip">All Folders</div>
        </li>

        <li class="menu-divider" aria-hidden="true"></li>

        {{-- Legacy: profile.php --}}
        <li class="{{ request()->routeIs('admin.profile') ? 'active' : '' }}">
            <a href="{{ route('admin.profile') }}" class="ajax-link" data-title="Settings">
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
