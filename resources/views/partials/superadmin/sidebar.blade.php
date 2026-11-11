{{-- Sidebar Component --}}
<section id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('superadmin.dashboard') }}" class="brand">
            <img src="{{ asset('img/cvsu-logo.png') }}" alt="CVSU logo">
            <span class="text">
                <span class="brand-title">ODCI Document Hub</span>
                <span class="brand-subtitle">CVSU Naic</span>
            </span>
        </a>
        <button class="cvsu-drawer-back" type="button" aria-label="Back to page">
            <i class="bx bx-arrow-back" aria-hidden="true"></i>
            <span>Back</span>
        </button>
    </div>

    <ul class="side-menu top">
        <li>
            <a href="{{ route('superadmin.dashboard') }}" class="ajax-link" data-title="Dashboard">
                <i class='bx bxs-dashboard'></i>
                <span class="text">Dashboard</span>
            </a>
            <div class="tooltip">Dashboard</div>
        </li>
        <li>
            <a href="{{ route('superadmin.users.index') }}" class="ajax-link" data-title="Users">
                <i class='bx bxs-group'></i>
                <span class="text">Users</span>
            </a>
            <div class="tooltip">Users</div>
        </li>
        <li>
            <a href="{{ route('superadmin.departments.index') }}" class="ajax-link" data-title="Departments">
                <i class='bx bxs-buildings'></i>
                <span class="text">Departments</span>
            </a>
            <div class="tooltip">Departments</div>
        </li>
        <li class="{{ request()->routeIs('superadmin.document-tracker') ? 'active' : '' }}">
            <a href="{{ route('superadmin.document-tracker') }}" class="ajax-link" data-title="Document Tracker">
                <i class='bx bxs-file-find'></i>
                <span class="text">Document Tracker</span>
            </a>
            <div class="tooltip">Document Tracker</div>
        </li>
        {{-- Legacy: files.php --}}
        <li class="{{ request()->routeIs('superadmin.files') ? 'active' : '' }}">
            <a href="{{ route('superadmin.files') }}" class="ajax-link" data-title="Files">
                <i class='bx bxs-file'></i>
                <span class="text">Files</span>
            </a>
            <div class="tooltip">Files</div>
        </li>
        {{-- Legacy: folders.php --}}
        <li class="{{ request()->routeIs('superadmin.folders') ? 'active' : '' }}">
            <a href="{{ route('superadmin.folders') }}" class="ajax-link" data-title="Folders">
                <i class='bx bxs-folder'></i>
                <span class="text">Folders</span>
            </a>
            <div class="tooltip">Folders</div>
        </li>
        <li class="{{ request()->routeIs('superadmin.social-feed') ? 'active' : '' }}">
            <a href="{{ route('superadmin.social-feed') }}" class="ajax-link" data-title="Social Feed">
                <i class='bx bx-news'></i>
                <span class="text">Social Feed</span>
            </a>
            <div class="tooltip">Social Feed</div>
        </li>
        <li class="{{ request()->routeIs('superadmin.reports') ? 'active' : '' }}">
            <a href="{{ route('superadmin.reports') }}" class="ajax-link" data-title="Reports">
                <i class='bx bxs-report'></i>
                <span class="text">Reports</span>
            </a>
            <div class="tooltip">Reports</div>
        </li>

        <li class="menu-divider" aria-hidden="true"></li>

        <li class="{{ request()->routeIs('superadmin.settings') ? 'active' : '' }}">
            <a href="{{ route('superadmin.settings') }}" class="ajax-link" data-title="System Settings">
                <i class='bx bxs-cog'></i>
                <span class="text">System Settings</span>
            </a>
            <div class="tooltip">System Settings</div>
        </li>
        <li class="{{ request()->routeIs('superadmin.activity-logs*') ? 'active' : '' }}">
            <a href="{{ route('superadmin.activity-logs') }}" class="ajax-link" data-title="Activity Logs">
                <i class='bx bxs-time'></i>
                <span class="text">Activity Logs</span>
            </a>
            <div class="tooltip">Activity Logs</div>
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