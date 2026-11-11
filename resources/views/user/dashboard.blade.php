@extends('layouts.user')

@section('title', 'Dashboard - CVSU Naic')

@section('body-class', 'user-dashboard-page')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/user/pages/dashboard/file_table.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user/pages/dashboard/grid_layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user/pages/dashboard/profile_system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user/pages/dashboard/responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/user/pages/dashboard/stats_card.css') }}">
@endsection

@section('content')
    @php
        $u = auth()->user();
        $welcomeName = $u->full_name ?: $u->username;
    @endphp

    <!-- Welcome Section -->
    <div class="head-title">
        <div class="left">
            <h1>Welcome, {{ $welcomeName }}!</h1>
            <ul class="breadcrumb">
                <li>
                    <a href="#">Dashboard</a>
                </li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li>
                    <a class="active" href="#">Overview</a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Enhanced Statistics Cards -->
    <ul class="box-info">
        <li>
            <i class='bx bxs-file'></i>
            <span class="text">
                <h3>{{ number_format($stats['total_files']) }}</h3>
                <p>Total Files</p>
            </span>
        </li>
        <li>
            <i class='bx bxs-folder'></i>
            <span class="text">
                <h3>{{ number_format($stats['total_folders']) }}</h3>
                <p>Total Folders</p>
            </span>
        </li>
        <li>
            <i class='bx bxs-cloud'></i>
            <span class="text">
                <h3>{{ formatFileSize($stats['storage_used']) }}</h3>
                <p>Storage Used</p>
            </span>
        </li>
        <li>
            <i class='bx bxs-download'></i>
            <span class="text">
                <h3>{{ number_format($stats['downloads_today']) }}</h3>
                <p>Downloads Today</p>
            </span>
        </li>
    </ul>

    <!-- Dashboard Grid Layout -->
    <div class="dashboard-grid">
        <!-- Recent Files -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Recent Files</h3>
                <div class="card-actions">
                    <i class='bx bx-search' title="Search Files"></i>
                    <i class='bx bx-filter' title="Filter Files"></i>
                    <i class='bx bx-refresh' title="Refresh"></i>
                </div>
            </div>

            @if ($stats['recent_uploads']->isNotEmpty())
                <div style="overflow-x: auto;">
                    <table class="files-table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Folder</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stats['recent_uploads']->take(6) as $file)
                                <tr>
                                    <td>
                                        <div class="file-info">
                                            <div class="file-icon">
                                                @php
                                                    $ext = $file->file_type ?: ($file->file_extension ?: pathinfo($file->original_name, PATHINFO_EXTENSION));
                                                @endphp
                                                <i class='bx {{ fileTypeIcon($ext) }}'></i>
                                            </div>
                                            <div class="file-details">
                                                @php
                                                    // Legacy: strlen > 30 ? substr($name, 0, 30).'...' : $name
                                                    $displayName = strlen($file->original_name) > 30
                                                        ? substr($file->original_name, 0, 30).'...'
                                                        : $file->original_name;
                                                @endphp
                                                <p>{{ $displayName }}</p>
                                                <div class="file-meta">{{ number_format($file->download_count) }} downloads</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $file->folder_name }}</td>
                                    <td>{{ formatFileSize($file->file_size) }}</td>
                                    <td>
                                        @if ($file->is_favorite)
                                            <span class="status-badge status-favorite">&#9733; Favorite</span>
                                        @elseif ($file->is_public)
                                            <span class="status-badge status-public">&#127760; Public</span>
                                        @else
                                            <span class="status-badge status-private">&#128274; Private</span>
                                        @endif
                                    </td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($file->uploaded_at)->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($stats['recent_uploads']->count() > 6)
                    <div style="text-align: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.06);">
                        {{-- Legacy: files.php (module not migrated yet) --}}
                        <a href="#" style="color: var(--blue); text-decoration: none; font-weight: 500; font-size: 14px;">
                            View All Files ({{ $stats['recent_uploads']->count() }} total) &rarr;
                        </a>
                    </div>
                @endif
            @else
                <div class="empty-state">
                    <i class='bx bx-file'></i>
                    <p>No files uploaded yet</p>
                    {{-- Legacy: folders.php (module not migrated yet) --}}
                    <a href="#">Upload your first file</a>
                </div>
            @endif
        </div>

        <!-- Quick Actions & Info -->
        <div>
            <!-- Quick Actions -->
            <div class="dashboard-card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="quick-actions">
                    {{-- Legacy: folders.php (module not migrated yet) --}}
                    <a href="#" class="action-item">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class='bx bx-upload'></i>
                            </div>
                            <p>Upload Files</p>
                        </div>
                        <i class='bx bx-chevron-right action-arrow'></i>
                    </a>

                    <a href="#" class="action-item">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class='bx bx-folder-plus'></i>
                            </div>
                            <p>Create Folder</p>
                        </div>
                        <i class='bx bx-chevron-right action-arrow'></i>
                    </a>

                    <a href="#" class="action-item">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class='bx bx-folder-open'></i>
                            </div>
                            <p>Organize Files</p>
                        </div>
                        <i class='bx bx-chevron-right action-arrow'></i>
                    </a>

                    <a href="#" class="action-item">
                        <div class="action-content">
                            <div class="action-icon">
                                <i class='bx bx-share'></i>
                            </div>
                            <p>Share Documents</p>
                        </div>
                        <i class='bx bx-chevron-right action-arrow'></i>
                    </a>
                </div>
            </div>

            <!-- Account Information -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Account Information</h3>
                    <div class="card-actions">
                        <i class='bx bx-edit' title="Edit Profile"></i>
                    </div>
                </div>
                <div class="account-info">
                    <div class="info-item">
                        <div class="info-label">Department</div>
                        <div class="info-value">{{ $u->department?->department_name ?? 'N/A' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Employee ID</div>
                        <div class="info-value">{{ $u->employee_id ?? 'N/A' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Position</div>
                        <div class="info-value">{{ $u->position ?? 'N/A' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value">{{ $u->created_at?->format('M j, Y') ?? 'Unknown' }}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value">
                            {{ $u->last_login ? \Illuminate\Support\Carbon::parse($u->last_login)->format('M j, Y g:i A') : 'First login' }}
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value" style="color: {{ $u->is_approved ? '#059669' : '#dc2626' }}; font-weight: 600;">
                            {{ $u->is_approved ? '✓ Approved' : '⏳ Pending' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Statistics Row -->
    <div class="dashboard-grid" style="margin-top: 24px;">
        <!-- Storage & Activity -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>Storage &amp; Activity Overview</h3>
                <div class="card-actions">
                    <i class='bx bx-bar-chart' title="View Analytics"></i>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 16px;">
                <div class="info-item">
                    <div class="info-label">Public Files</div>
                    <div class="info-value" style="color: #059669;">{{ number_format($stats['public_files']) }} files</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Favorite Files</div>
                    <div class="info-value" style="color: #d97706;">{{ number_format($stats['favorite_files']) }} files</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department Files</div>
                    <div class="info-value" style="color: var(--blue);">{{ number_format($stats['department_files']) }} files</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Recent Activity</div>
                    <div class="info-value">
                        @php
                            $recentCount = $stats['recent_uploads']->count();
                        @endphp
                        {{ $recentCount > 0 ? "$recentCount uploads this week" : 'No recent activity' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3>System Status</h3>
                <div class="card-actions">
                    <i class='bx bx-info-circle' title="System Information"></i>
                </div>
            </div>
            <div class="account-info">
                <div class="info-item">
                    <div class="info-label">Server Status</div>
                    <div class="info-value" style="color: #059669;">&#128994; Online</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Backup Status</div>
                    <div class="info-value" style="color: #059669;">✓ Up to date</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Storage Health</div>
                    <div class="info-value" style="color: #059669;">Optimal</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Sync</div>
                    <div class="info-value">{{ now()->format('g:i A') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
