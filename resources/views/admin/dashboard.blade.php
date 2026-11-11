@extends('layouts.admin')

@section('title', 'Admin Dashboard - CVSU Naic')

@section('body-class', 'admin-dashboard-page')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/admin/pages/dashboard/stats_card.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/dashboard/grid_layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/dashboard/file_table.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/pages/dashboard/responsive.css') }}">
@endsection

@section('content')
    @php
        $u = auth()->user();
        $firstName = trim((string) $u->name);
        $greetingName = $firstName !== '' ? $firstName : $u->surname;
        $fmtBytes = [\App\Services\AdminDashboardData::class, 'formatBytes'];
    @endphp

    <!-- Enhanced Header -->
    <div class="head-title">
        <div class="left">
            <h1>Welcome Back, {{ $greetingName }}!</h1>
            <ul class="breadcrumb">
                <li><a href="#">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">Overview</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><span class="scope-note">{{ $scopeLabel }}</span></li>
            </ul>
        </div>
        <div class="right">
            <button class="btn btn-secondary" title="Refresh" type="button" onclick="window.location.reload()">
                <i class='bx bx-refresh'></i>
                <span>Refresh</span>
            </button>
            {{-- Legacy: files.php (module not migrated yet) --}}
            <a class="btn btn-primary" title="Upload File" href="#">
                <i class='bx bxs-cloud-upload'></i>
                <span>Upload File</span>
            </a>
        </div>
    </div>

    <!-- System Status Overview: only the tiles an admin can act on. The database
         health and storage figures live in Storage Analytics below. -->
    <ul class="box-info stats-overview">
        <li class="status-item {{ $summary['users_active'] > 0 ? 'healthy' : 'warning' }}">
            <div class="stat-head">
                <i class='bx bxs-user-account'></i>
                <h3>{{ number_format($summary['users_active']) }}</h3>
            </div>
            <p class="stat-label">Active Users &middot; last 30 days</p>
        </li>
        <li class="status-item {{ $summary['users_pending'] > 0 ? 'warning' : 'healthy' }}">
            <div class="stat-head">
                <i class='bx bxs-user-plus'></i>
                <h3>{{ number_format($summary['users_pending']) }}</h3>
            </div>
            <p class="stat-label">Pending Approval{{ $summary['users_total'] > 0 ? ' · '.number_format($summary['users_pending']).' of '.number_format($summary['users_total']).' accounts' : '' }}</p>
        </li>
    </ul>

    <!-- Enhanced Statistics Cards -->
    <ul class="box-info">
        <li>
            <div class="stat-head">
                <i class='bx bxs-file'></i>
                <h3>{{ number_format($summary['files_total']) }}</h3>
            </div>
            <p class="stat-label">Total Files</p>
            <div class="change {{ $changes['files']['class'] }}">
                <i class='bx {{ $changes['files']['icon'] }}'></i>
                <span>{{ $changes['files']['label'] }}</span>
            </div>
            <div class="progress-bar" title="{{ number_format($bars['files'], 1) }}% of all files in the system">
                <div class="progress-fill" style="width: {{ $bars['files'] }}%;"></div>
            </div>
            <div class="bar-note">{{ number_format($bars['files'], 1) }}% of all system files</div>
        </li>

        <li>
            <div class="stat-head">
                <i class='bx bxs-folder'></i>
                <h3>{{ number_format($summary['folders_total']) }}</h3>
            </div>
            <p class="stat-label">Total Folders</p>
            <div class="change {{ $changes['folders']['class'] }}">
                <i class='bx {{ $changes['folders']['icon'] }}'></i>
                <span>{{ $changes['folders']['label'] }}</span>
            </div>
            <div class="progress-bar" title="{{ number_format($bars['folders'], 1) }}% of all folders in the system">
                <div class="progress-fill" style="width: {{ $bars['folders'] }}%;"></div>
            </div>
            <div class="bar-note">{{ number_format($bars['folders'], 1) }}% of all system folders</div>
        </li>

        <li>
            <div class="stat-head">
                <i class='bx bxs-cloud'></i>
                <h3>{{ $fmtBytes($summary['storage_bytes']) }}</h3>
            </div>
            <p class="stat-label">Storage Used</p>
            <div class="change {{ $changes['storage']['class'] }}">
                <i class='bx {{ $changes['storage']['icon'] }}'></i>
                <span>{{ $changes['storage']['label'] }}</span>
            </div>
            <div class="progress-bar" title="{{ number_format($bars['storage'], 1) }}% of the uploads volume">
                <div class="progress-fill" style="width: {{ $bars['storage'] }}%;"></div>
            </div>
            <div class="bar-note">{{ number_format($bars['storage'], 1) }}% of the uploads volume</div>
        </li>

        <li>
            <div class="stat-head">
                <i class='bx bxs-user-account'></i>
                <h3>{{ number_format($summary['users_active']) }}</h3>
            </div>
            <p class="stat-label">Active Users</p>
            <div class="change neutral">
                <i class='bx bx-calendar'></i>
                <span>of {{ number_format($summary['users_approved']) }} approved</span>
            </div>
            <div class="progress-bar" title="{{ number_format($bars['users'], 1) }}% of approved users signed in within 30 days">
                <div class="progress-fill" style="width: {{ $bars['users'] }}%;"></div>
            </div>
            <div class="bar-note">Signed in within the last 30 days</div>
        </li>
    </ul>

    <!-- Main Dashboard Content -->
    <div class="dashboard-grid">
        <!-- Recent Activity -->
        <div class="dashboard-card fade-in">
            <div class="card-header">
                <h3>
                    <i class='bx bxs-time'></i>
                    Recent Activity
                </h3>
                <div class="card-actions">
                    <button class="btn-icon" title="Filter">
                        <i class='bx bx-filter'></i>
                    </button>
                    <button class="btn-icon" title="Refresh" type="button" onclick="window.location.reload()">
                        <i class='bx bx-refresh'></i>
                    </button>
                    <button class="btn-icon" title="Export">
                        <i class='bx bx-download'></i>
                    </button>
                </div>
            </div>

            <table class="files-table enhanced-table">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>User</th>
                        <th>Department</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @if (empty($recentActivity))
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class='bx bx-history'></i>
                                    <p>No activity recorded in the last 7 days.</p>
                                </div>
                            </td>
                        </tr>
                    @else
                        @foreach ($recentActivity as $activity)
                            <tr>
                                <td>
                                    <div class="file-info">
                                        <div class="file-icon">
                                            <i class='bx {{ $activity['icon'] }}'></i>
                                        </div>
                                        <div class="file-details">
                                            <p>{{ $activity['label'] }}</p>
                                            <div class="file-meta">
                                                @if ($activity['occurrences'] > 1)
                                                    {{ $activity['occurrences'] }} times in the last 7 days
                                                @else
                                                    Once in the last 7 days
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $activity['user'] }}</td>
                                <td>{{ $activity['department'] }}</td>
                                <td>{{ $activity['when'] }}</td>
                                <td>
                                    <span class="status-badge {{ $activity['badge'] }}">
                                        <i class='bx {{ $activity['badgeIcon'] }}'></i>
                                        {{ $activity['badgeText'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Enhanced Quick Actions -->
        <div class="dashboard-card slide-up">
            <div class="card-header">
                <h3>
                    <i class='bx bxs-zap'></i>
                    Quick Actions
                </h3>
            </div>

            <div class="quick-actions">
                {{-- Legacy: files.php (module not migrated yet) --}}
                <a href="#" class="action-item">
                    <div class="action-content">
                        <div class="action-icon">
                            <i class='bx bx-upload'></i>
                        </div>
                        <p>Upload Files</p>
                    </div>
                    <i class='bx bx-chevron-right action-arrow'></i>
                </a>

                {{-- Legacy: folders.php (module not migrated yet) --}}
                <a href="#" class="action-item">
                    <div class="action-content">
                        <div class="action-icon">
                            <i class='bx bx-folder-plus'></i>
                        </div>
                        <p>Create Folder</p>
                    </div>
                    <i class='bx bx-chevron-right action-arrow'></i>
                </a>

                {{-- Legacy: view-faculty-staff.php --}}
                <a href="{{ route('admin.view-faculty-staff') }}" class="action-item">
                    <div class="action-content">
                        <div class="action-icon">
                            <i class='bx bx-user-plus'></i>
                        </div>
                        <p>Manage Users</p>
                    </div>
                    <i class='bx bx-chevron-right action-arrow'></i>
                </a>

                {{-- Legacy: reports.php (module not migrated yet) --}}
                <a href="#" class="action-item">
                    <div class="action-content">
                        <div class="action-icon">
                            <i class='bx bx-bar-chart-alt-2'></i>
                        </div>
                        <p>Generate Reports</p>
                    </div>
                    <i class='bx bx-chevron-right action-arrow'></i>
                </a>

                {{-- Legacy: profile.php (module not migrated yet) --}}
                <a href="#" class="action-item">
                    <div class="action-content">
                        <div class="action-icon">
                            <i class='bx bx-cog'></i>
                        </div>
                        <p>System Settings</p>
                    </div>
                    <i class='bx bx-chevron-right action-arrow'></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Additional Information Cards -->
    <div class="dashboard-grid" style="margin-top: 24px;">
        <!-- Storage & Activity -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>
                    <i class='bx bxs-pie-chart-alt-2'></i>
                    Storage Analytics
                </h3>
                <div class="card-actions">
                    {{-- Legacy: reports.php (module not migrated yet) --}}
                    <a class="btn-icon" href="#" title="View Details">
                        <i class='bx bx-show'></i>
                    </a>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 16px;">
                <div class="info-item">
                    <div class="info-label">Documents Stored</div>
                    <div class="info-value" style="color: var(--cvsu-green-700); font-size: 24px; font-weight: 700;">{{ $fmtBytes($summary['storage_bytes']) }}</div>
                    <div class="progress-bar" style="margin-top: 8px;">
                        <div class="progress-fill" style="width: {{ $bars['storage'] }}%;"></div>
                    </div>
                    <div class="bar-note">{{ number_format($bars['storage'], 1) }}% of the uploads volume</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Uploads Volume</div>
                    <div class="info-value" style="color: var(--cvsu-gold-700); font-size: 24px; font-weight: 700;">{{ $quotaBytes > 0 ? $fmtBytes($quotaBytes, 0) : 'Unknown' }}</div>
                    <div class="bar-note" style="margin-top: 8px;">
                        {{ $freeBytes > 0 ? $fmtBytes($freeBytes, 0).' free' : 'Free space unavailable' }}
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Growth Rate</div>
                    <div class="info-value" style="color: {{ $changes['storage']['class'] === 'negative' ? 'var(--cvsu-gold-700)' : 'var(--cvsu-green-700)' }}; font-size: 24px; font-weight: 700;">{{ $changes['storage']['label'] }}</div>
                    <div style="color: var(--cvsu-grey-500); font-size: 12px; margin-top: 8px;">
                        <i class='bx {{ $changes['storage']['icon'] }}'></i> Last 30 days vs previous 30
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Recent Activity</div>
                    <div class="info-value" style="font-size: 24px; font-weight: 700;">{{ number_format($summary['uploads_this_week']) }}</div>
                    <div class="bar-note" style="margin-top: 8px;">
                        {{ $summary['uploads_this_week'] === 1 ? 'upload' : 'uploads' }} in the last 7 days
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="dashboard-card slide-up">
            <div class="card-header">
                <h3>
                    <i class='bx bxs-user-detail'></i>
                    Account Information
                </h3>
                <div class="card-actions">
                    {{-- Legacy: profile.php (module not migrated yet) --}}
                    <a class="btn-icon" href="#" title="Edit Profile">
                        <i class='bx bx-edit'></i>
                    </a>
                </div>
            </div>

            <div class="account-info">
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value">{{ $account['department'] }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Employee ID</div>
                    <div class="info-value">{{ $account['employeeId'] }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Login</div>
                    <div class="info-value">{{ $account['lastLogin'] }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Member Since</div>
                    <div class="info-value">{{ $account['memberSince'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications and Alerts -->
    <div class="dashboard-card fade-in" style="margin-top: 24px;">
        <div class="card-header">
            <h3>
                <i class='bx bxs-bell'></i>
                Recent Notifications
            </h3>
            <div class="card-actions">
                @if ($unreadNotifications > 0)
                    <span class="unread-chip" title="Unread notifications">
                        {{ $unreadNotifications }} unread
                    </span>
                @endif
                <button class="btn-icon" title="Mark all as read">
                    <i class='bx bx-check-double'></i>
                </button>
                <button class="btn-icon" title="Settings">
                    <i class='bx bx-cog'></i>
                </button>
            </div>
        </div>

        <div class="notif-list">
            @if (empty($recentNotifications))
                <div class="notif-item">
                    <div class="notif-icon info">
                        <i class='bx bx-bell-off'></i>
                    </div>
                    <div class="notif-body">
                        <h4>No notifications</h4>
                        <p>You have no notifications right now. New alerts appear here as they arrive.</p>
                    </div>
                </div>
            @else
                @foreach ($recentNotifications as $notification)
                    <div class="notif-item{{ $notification['isRead'] ? '' : ' unread' }}">
                        <div class="notif-icon {{ $notification['tone'] }}">
                            <i class='bx {{ $notification['icon'] }}'></i>
                        </div>
                        <div class="notif-body">
                            <h4>{{ $notification['title'] }}</h4>
                            <p>{{ $notification['message'] }}</p>
                            <span>{{ $notification['when'] }}</span>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection
