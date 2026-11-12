@extends('layouts.superadmin')

@section('title', 'Dashboard - CVSU Naic')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/superadmin/dashboard.css') }}">
@endsection

@section('content')
    <div class="head-title">
        <div class="left">
            <h1>Super Admin Dashboard</h1>
            <ul class="breadcrumb">
                <li><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">Overview</a></li>
            </ul>
        </div>
        <div class="right">
            <button class="btn btn-secondary" type="button" title="Refresh" onclick="window.location.reload()">
                <i class='bx bx-refresh'></i>
                <span>Refresh</span>
            </button>
            <a href="{{ route('superadmin.users.index') }}" class="btn btn-primary" title="Manage users">
                <i class='bx bx-user-plus'></i>
                <span>Add User</span>
            </a>
        </div>
    </div>

    {{-- Alert for pending users --}}
    @if ($stats['pending_users'] > 0)
        <div class="alert alert-warning">
            <i class='bx bxs-error-circle'></i>
            <div>
                <strong>Pending Approvals:</strong> {{ $stats['pending_users'] }} user
                registration(s) are waiting for approval.
                <a href="{{ route('superadmin.users.index') }}?filter=pending">Review Now</a>
            </div>
        </div>
    @endif

    @if (session('error') === 'access_denied')
        <div class="alert alert-warning">
            <i class='bx bxs-error-circle'></i>
            <div>Access denied. You do not have permission to view that page.</div>
        </div>
    @endif

    {{-- System Statistics --}}
    <div class="stats-grid lg:!grid-cols-3">
        <div class="stat-card">
            <div class="stat-icon users">
                <i class='bx bxs-group'></i>
            </div>
            <div class="stat-info">
                <h3>{{ number_format($stats['total_users']) }}</h3>
                <p>Total Users</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon files">
                <i class='bx bxs-file'></i>
            </div>
            <div class="stat-info">
                <h3>{{ number_format($stats['total_files']) }}</h3>
                <p>Total Files</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon folders">
                <i class='bx bxs-folder'></i>
            </div>
            <div class="stat-info">
                <h3>{{ number_format($stats['total_folders']) }}</h3>
                <p>Total Folders</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon departments">
                <i class='bx bxs-buildings'></i>
            </div>
            <div class="stat-info">
                <h3>{{ number_format($stats['total_departments']) }}</h3>
                <p>Departments</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon announcements">
                <i class='bx bxs-megaphone'></i>
            </div>
            <div class="stat-info">
                <h3>{{ number_format($stats['total_announcements']) }}</h3>
                <p>Announcements</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon storage">
                <i class='bx bxs-cloud'></i>
            </div>
            <div class="stat-info">
                <h3>{{ formatFileSize($stats['storage_used']) }}</h3>
                <p>Storage Used</p>
            </div>
        </div>
    </div>

    {{-- Main Dashboard Content --}}
    <div class="table-data">
        {{-- Recent System Activities --}}
        <div class="order" style="flex: 2;">
            <div class="head">
                <h3>Recent System Activities</h3>
                <i class='bx bx-refresh'></i>
                <i class='bx bx-filter'></i>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                @if ($stats['recent_activities']->isNotEmpty())
                    @foreach ($stats['recent_activities'] as $activity)
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class='bx {{ activityIcon($activity->action) }}'></i>
                            </div>
                            <div class="activity-content">
                                <h4>
                                    @if ($activity->name)
                                        {{ $activity->name }}{{ $activity->mi ? ' '.$activity->mi.'. ' : ' ' }}{{ $activity->surname }}
                                    @else
                                        System
                                    @endif
                                </h4>
                                <p>{{ actionText($activity->action) }}</p>
                                @if ($activity->description)
                                    <p style="margin-top: 3px; font-size: 11px;">{{ $activity->description }}</p>
                                @endif
                            </div>
                            <div class="activity-time">
                                {{ \Illuminate\Support\Carbon::parse($activity->created_at)->format('M j, g:i A') }}
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="empty-state">
                        <i class='bx bx-time' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                        <p>No recent activities</p>
                    </div>
                @endif
            </div>
            <div style="text-align: center; margin-top: 15px;">
                <a href="{{ route('superadmin.activity-logs') }}" style="text-decoration: none; font-size: 14px;">
                    View All Activities &rarr;
                </a>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="todo" style="flex: 1;">
            <div class="head">
                <h3>Quick Actions</h3>
                <i class='bx bx-plus'></i>
            </div>
            <div style="display: grid; gap: 12px;">
                <a href="{{ route('superadmin.users.index') }}?action=create" class="quick-action">
                    <i class='bx bx-user-plus'></i>
                    <div>Add User</div>
                </a>
                <a href="{{ route('superadmin.departments.index') }}?action=create" class="quick-action">
                    <i class='bx bx-buildings'></i>
                    <div>Add Department</div>
                </a>
                <a href="{{ route('superadmin.files') }}" class="quick-action">
                    <i class='bx bxs-file'></i>
                    <div>Manage Files</div>
                </a>
                <a href="{{ route('superadmin.document-tracker') }}" class="quick-action">
                    <i class='bx bx-check-square'></i>
                    <div>Document Tracker</div>
                </a>
                <a href="{{ route('superadmin.reports') }}" class="quick-action">
                    <i class='bx bx-bar-chart-alt'></i>
                    <div>Generate Report</div>
                </a>
                <a href="{{ route('superadmin.settings') }}" class="quick-action">
                    <i class='bx bx-cog'></i>
                    <div>System Settings</div>
                </a>
            </div>
        </div>
    </div>

    {{-- Department Statistics --}}
    <div class="table-data" style="margin-top: 20px;">
        <div class="order" style="flex: 1;">
            <div class="head">
                <h3>Department Statistics</h3>
                <i class='bx bx-bar-chart'></i>
            </div>
            @if ($stats['department_stats']->isNotEmpty())
                <div class="table-scroll">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Department</th>
                            <th style="text-align: center;">Users</th>
                            <th style="text-align: center;">Folders</th>
                            <th style="text-align: center;">Files</th>
                            <th style="text-align: right;">Storage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats['department_stats'] as $dept)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $dept->department_code }}</strong>
                                        <br><small style="color: #666;">{{ $dept->department_name }}</small>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge primary">{{ $dept->user_count }}</span>
                                </td>
                                <td style="text-align: center;">{{ $dept->folder_count }}</td>
                                <td style="text-align: center;">{{ $dept->file_count }}</td>
                                <td style="text-align: right;">
                                    <strong>{{ formatFileSize($dept->total_size) }}</strong>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @else
                <div class="empty-state">
                    <i class='bx bx-buildings' style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                    <p>No department data available</p>
                </div>
            @endif
        </div>
    </div>

    {{-- System Information --}}
    <div class="table-data" style="margin-top: 20px;">
        <div class="order" style="flex: 1;">
            <div class="head">
                <h3>System Information</h3>
                <i class='bx bx-info-circle'></i>
            </div>
            <div style="padding: 20px 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 180px), 1fr)); gap: 20px; font-size: 14px;">
                    <div><strong>Application</strong><br><span style="color: var(--text-secondary);">ODCI Document</span></div>
                    <div><strong>Campus</strong><br><span style="color: var(--text-secondary);">CVSU Naic</span></div>
                    <div><strong>PHP Version</strong><br><span style="color: var(--text-secondary);">{{ phpversion() }}</span></div>
                    <div><strong>Database</strong><br><span style="color: var(--text-secondary);">MySQL / MariaDB</span></div>
                    <div><strong>Storage Used</strong><br><span style="color: var(--text-secondary);">{{ formatFileSize($stats['storage_used']) }}</span></div>
                    <div><strong>Server Time</strong><br><span style="color: var(--text-secondary);">{{ now()->format('M j, Y g:i A') }}</span></div>
                </div>
            </div>
        </div>
    </div>
@endsection
