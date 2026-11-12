@extends('layouts.superadmin')

@section('title', 'Settings - CVSU Naic')
@section('body-class', 'superadmin-settings-page')

@section('page-css')
    <style>
        body .sa-tab { appearance: none; background: transparent; border: 0; border-bottom: 3px solid transparent; border-radius: 0; box-shadow: none;
            color: #6b7280; font: inherit; font-weight: 600; font-size: .9rem; padding: .85rem 1.1rem; display: inline-flex; flex-shrink: 0; white-space: nowrap;
            align-items: center; gap: .5rem; cursor: pointer; transition: color .2s ease, border-color .2s ease, background .2s ease; }
        body .sa-tab:hover { color: #16241c; background: #f0fdf4; }
        body .sa-tab.active { color: #1e7e34; border-bottom-color: #1e7e34; }
        .sa-tabs { scrollbar-width: thin; -webkit-overflow-scrolling: touch; }
        @media (max-width: 480px) { body .sa-tab { padding: .75rem .85rem; font-size: .85rem; } }
        .sa-panel { display: none; }
        .sa-panel.active { display: block; animation: saFade .3s ease both; }
        @keyframes saFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .setting-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #e6ebe7; }
        .setting-item:last-child { border-bottom: 0; }
        .toggle-switch { position: relative; display: inline-block; width: 46px; height: 25px; flex-shrink: 0; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-switch .toggle-slider { position: absolute; inset: 0; border-radius: 999px; background: #d1d5db; cursor: pointer; transition: background .2s ease; }
        .toggle-switch .toggle-slider::before { content: ''; position: absolute; width: 19px; height: 19px; left: 3px; top: 3px; border-radius: 50%;
            background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .2s ease; }
        .toggle-switch input:checked + .toggle-slider { background: #1e7e34; }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(21px); }
    </style>
@endsection

@section('content')
    @php
        $fullName = $currentUser->full_name ?: $currentUser->username;
        $dept = $currentUser->department;
    @endphp

    <div class="p-6 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-ink">System Settings</h1>
            <ul class="breadcrumb mt-1">
                <li><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">System Settings</a></li>
            </ul>
        </div>

        @if (session('success_message'))
    <x-ui.alert type="success">{{ session('success_message') }}</x-ui.alert>
@endif

        @if (session('error_message'))
    <x-ui.alert type="error">{{ session('error_message') }}</x-ui.alert>
@endif

        <div class="ui-card overflow-hidden">
            {{-- Header --}}
            <div class="flex flex-col gap-5 border-b border-line bg-gradient-to-r from-cvsu-green-700 to-cvsu-green-800 px-4 py-5 text-white sm:px-6 sm:py-6 sm:flex-row sm:items-center">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white/15 text-white ring-2 ring-white/30 backdrop-blur">
                    @if ($hasProfileImage)
                        <img src="{{ $profileImage }}" alt="Profile" class="h-full w-full object-cover">
                    @else
                        <span class="text-3xl font-bold text-white">{{ $initials }}</span>
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold sm:text-2xl">{{ $fullName }}</h1>
                        <span class="inline-flex items-center gap-1 rounded-full bg-cvsu-gold-400 px-2.5 py-0.5 text-xs font-bold text-cvsu-green-900">
                            <i class='bx bxs-crown'></i> Super Admin
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-cvsu-green-100">{{ $currentUser->email }}</p>
                    <p class="mt-0.5 text-sm text-cvsu-green-100">
                        <i class='bx bx-building mr-1'></i>{{ $dept?->department_name ?? 'No Department Assigned' }}
                    </p>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="sa-tabs flex gap-1 overflow-x-auto border-b border-line bg-white px-2 sm:px-4">
                <button type="button" class="sa-tab active" data-tab="profile"><i class='bx bxs-user-pin'></i> Profile</button>
                <button type="button" class="sa-tab" data-tab="security"><i class='bx bxs-lock-alt'></i> Security</button>
                <button type="button" class="sa-tab" data-tab="system"><i class='bx bxs-cog'></i> System</button>
                <button type="button" class="sa-tab" data-tab="statistics"><i class='bx bxs-bar-chart-alt-2'></i> Statistics</button>
            </div>

            <div class="p-4 sm:p-6">
                {{-- ================= PROFILE TAB ================= --}}
                <div id="panel-profile" class="sa-panel active">
                    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
                        <div class="space-y-6">
                            <div class="ui-card p-4 sm:p-5">
                                <h3 class="mb-4 flex items-center gap-2 text-sm font-bold text-ink">
                                    <i class='bx bxs-image-alt text-cvsu-green-600'></i> Profile Photo
                                </h3>
                                <div class="mx-auto flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border-4 border-cvsu-green-100 bg-cvsu-green-50">
                                    @if ($hasProfileImage)
                                        <img src="{{ $profileImage }}" alt="Profile" class="h-full w-full rounded-full object-cover">
                                    @else
                                        <span class="text-3xl font-bold text-cvsu-green-600">{{ $initials }}</span>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                                    @csrf
                                    <input type="hidden" name="action" value="upload_image">
                                    <label class="block cursor-pointer rounded-lg border border-line bg-white px-4 py-2.5 text-center text-sm font-semibold text-ink transition hover:border-cvsu-green-400 hover:bg-cvsu-green-50">
                                        <i class='bx bx-cloud-upload mr-1 text-cvsu-green-600'></i> Choose Image
                                        <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp" class="hidden" onchange="this.form.submit()">
                                    </label>
                                    <p class="text-center text-xs text-muted">JPG, PNG, GIF or WEBP. Max 5MB.</p>
                                </form>
                                @if ($hasProfileImage)
                                    <form method="POST" action="{{ route('profile.update') }}" class="mt-2">
                                        @csrf
                                        <input type="hidden" name="action" value="remove_image">
                                        <button type="submit" class="w-full rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-danger transition hover:bg-red-50">
                                            <i class='bx bx-trash mr-1'></i> Remove Photo
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('profile.update') }}" class="rounded-card border border-line bg-white p-6 shadow-card">
                            @csrf
                            <input type="hidden" name="action" value="update_profile">
                            <h3 class="mb-5 flex items-center gap-2 text-base font-bold text-ink">
                                <i class='bx bxs-user-pin text-cvsu-green-600'></i> Profile Information
                            </h3>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" value="{{ old('name', $currentUser->name) }}" required
                                           class="ui-input">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Middle Initial</label>
                                    <input type="text" name="mi" value="{{ old('mi', $currentUser->mi) }}" maxlength="5"
                                           class="ui-input">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Surname <span class="text-danger">*</span></label>
                                    <input type="text" name="surname" value="{{ old('surname', $currentUser->surname) }}" required
                                           class="ui-input">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Employee ID</label>
                                    <input type="text" name="employee_id" value="{{ old('employee_id', $currentUser->employee_id) }}"
                                           class="ui-input">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" value="{{ old('email', $currentUser->email) }}" required
                                           class="ui-input">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Phone Number</label>
                                    <input type="text" name="phone" value="{{ old('phone', $currentUser->phone) }}"
                                           class="ui-input">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Position / Job Title</label>
                                    <input type="text" name="position" value="{{ old('position', $currentUser->position) }}"
                                           class="ui-input">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Department</label>
                                    <select name="department_id"
                                            class="ui-input">
                                        <option value="">— No department —</option>
                                        @foreach ($departments as $deptOption)
                                            <option value="{{ $deptOption->id }}" @selected($currentUser->department_id == $deptOption->id)>
                                                {{ $deptOption->department_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Address</label>
                                    <input type="text" name="address" value="{{ old('address', $currentUser->address) }}"
                                           class="ui-input">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Date of Birth</label>
                                    <input type="date" name="date_of_birth"
                                           value="{{ old('date_of_birth', $currentUser->date_of_birth ? \Carbon\Carbon::parse($currentUser->date_of_birth)->format('Y-m-d') : '') }}"
                                           class="ui-input">
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end border-t border-line pt-5">
                                <button type="submit" class="ui-btn-primary px-5 py-2.5">
                                    <i class='bx bx-save'></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ================= SECURITY TAB ================= --}}
                <div id="panel-security" class="sa-panel">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <form method="POST" action="{{ route('profile.update') }}" class="rounded-card border border-line bg-white p-6 shadow-card">
                            @csrf
                            <input type="hidden" name="action" value="change_password">
                            <h3 class="mb-5 flex items-center gap-2 text-base font-bold text-ink">
                                <i class='bx bxs-lock-alt text-cvsu-gold-500'></i> Change Password
                            </h3>
                            <div class="space-y-5">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Current Password</label>
                                    <input type="password" name="current_password" required
                                           class="w-full rounded-lg border border-line bg-white px-4 py-2.5 text-sm text-ink outline-none transition focus:border-cvsu-gold-400 focus:ring-2 focus:ring-cvsu-gold-100">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">New Password</label>
                                    <input type="password" name="new_password" required minlength="8"
                                           class="w-full rounded-lg border border-line bg-white px-4 py-2.5 text-sm text-ink outline-none transition focus:border-cvsu-gold-400 focus:ring-2 focus:ring-cvsu-gold-100">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-ink">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required minlength="8"
                                           class="w-full rounded-lg border border-line bg-white px-4 py-2.5 text-sm text-ink outline-none transition focus:border-cvsu-gold-400 focus:ring-2 focus:ring-cvsu-gold-100">
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-gold-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-cvsu-gold-600">
                                        <i class='bx bxs-key'></i> Update Password
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="rounded-card border border-line bg-white p-6 shadow-card">
                            <h3 class="mb-5 flex items-center gap-2 text-base font-bold text-ink">
                                <i class='bx bxs-shield-alt-2 text-cvsu-green-600'></i> Account Security
                            </h3>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-semibold text-ink">Profile Completion</span>
                                <span class="font-bold text-cvsu-green-600">{{ $securityScore }}%</span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted/15">
                                <div class="h-full rounded-full bg-gradient-to-r from-cvsu-green-500 to-cvsu-gold-400 transition-all" style="width: {{ $securityScore }}%"></div>
                            </div>
                            <p class="mt-3 text-sm text-muted">Complete your profile to improve your account security score.</p>
                            <ul class="mt-4 space-y-2 text-sm text-muted">
                                <li class="flex items-center gap-2">
                                    <i class='bx {{ $currentUser->phone ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle' }}'></i> Contact number
                                </li>
                                <li class="flex items-center gap-2">
                                    <i class='bx {{ $currentUser->position ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle' }}'></i> Position / title
                                </li>
                                <li class="flex items-center gap-2">
                                    <i class='bx {{ $currentUser->employee_id ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle' }}'></i> Employee ID
                                </li>
                                <li class="flex items-center gap-2">
                                    <i class='bx {{ $currentUser->date_of_birth ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle' }}'></i> Date of birth
                                </li>
                                <li class="flex items-center gap-2">
                                    <i class='bx {{ $currentUser->hire_date ? 'bxs-check-circle text-cvsu-green-600' : 'bx-circle' }}'></i> Hire date
                                </li>
                            </ul>

                            <div class="mt-6 border-t border-line pt-5">
                                <h4 class="mb-1 text-sm font-bold text-ink">Login Attempts</h4>
                                <p class="mb-3 text-sm text-muted">
                                    Failed attempts: <strong class="text-ink">{{ (int) $currentUser->failed_login_attempts }}</strong>.
                                    @if ($currentUser->account_locked_until)
                                        Account locked until {{ \Carbon\Carbon::parse($currentUser->account_locked_until)->format('M d, Y h:i A') }}.
                                    @endif
                                </p>
                                <form method="POST" action="{{ route('profile.update') }}">
                                    @csrf
                                    <input type="hidden" name="action" value="clear_login_attempts">
                                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink transition hover:bg-muted/10">
                                        <i class='bx bx-refresh'></i> Clear Login Attempts
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ================= SYSTEM TAB ================= --}}
                <div id="panel-system" class="sa-panel">
                    <form method="POST" action="{{ route('superadmin.settings.update') }}" class="rounded-card border border-line bg-white p-6 shadow-card">
                        @csrf
                        <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-ink">
                            <i class='bx bxs-cog text-cvsu-green-600'></i> System Configuration
                        </h3>
                        <div class="divide-y divide-line">
                            <div class="setting-item">
                                <div>
                                    <label class="setting-label text-sm font-semibold text-ink">Maintenance Mode</label>
                                    <p class="text-sm text-muted">Enable maintenance mode to temporarily disable user access</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="system_settings[maintenance_mode]" value="1" @checked($systemSettings['maintenance_mode'])>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="w-full max-w-xs">
                                    <label class="setting-label text-sm font-semibold text-ink" for="setting_max_file">Maximum File Size</label>
                                    <p class="text-sm text-muted">Largest allowed upload, in megabytes</p>
                                </div>
                                <div class="flex w-36 items-center rounded-lg border border-line focus-within:border-cvsu-green-500">
                                    <input type="number" id="setting_max_file" name="system_settings[max_file_size]"
                                           value="{{ (int) $systemSettings['max_file_size'] }}" min="1" max="1024" required
                                           class="w-full rounded-l-lg px-3 py-2 text-sm text-ink outline-none">
                                    <span class="px-3 text-sm font-bold text-muted">MB</span>
                                </div>
                            </div>

                            <div class="setting-item">
                                <div class="w-full max-w-xs">
                                    <label class="setting-label text-sm font-semibold text-ink" for="setting_timeout">Session Timeout</label>
                                    <p class="text-sm text-muted">Automatically log inactive users out after this many minutes</p>
                                </div>
                                <div class="flex w-36 items-center rounded-lg border border-line focus-within:border-cvsu-green-500">
                                    <input type="number" id="setting_timeout" name="system_settings[session_timeout]"
                                           value="{{ (int) $systemSettings['session_timeout'] }}" min="5" max="1440" required
                                           class="w-full rounded-l-lg px-3 py-2 text-sm text-ink outline-none">
                                    <span class="px-3 text-sm font-bold text-muted">min</span>
                                </div>
                            </div>

                            <div class="setting-item">
                                <div>
                                    <label class="setting-label text-sm font-semibold text-ink">Email Notifications</label>
                                    <p class="text-sm text-muted">Send system notifications by email</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="system_settings[email_notifications]" value="1" @checked($systemSettings['email_notifications'])>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div>
                                    <label class="setting-label text-sm font-semibold text-ink">New User Registration</label>
                                    <p class="text-sm text-muted">Allow new users to register on the login page</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="system_settings[user_registration]" value="1" @checked($systemSettings['user_registration'])>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div>
                                    <label class="setting-label text-sm font-semibold text-ink">Auto Approve New Users</label>
                                    <p class="text-sm text-muted">Approve new registrations automatically (skips admin approval)</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="system_settings[auto_approve_users]" value="1" @checked($systemSettings['auto_approve_users'])>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="w-full max-w-xs">
                                    <label class="setting-label text-sm font-semibold text-ink" for="setting_backup">Backup Retention</label>
                                    <p class="text-sm text-muted">Keep automated backups for this many days</p>
                                </div>
                                <div class="flex w-36 items-center rounded-lg border border-line focus-within:border-cvsu-green-500">
                                    <input type="number" id="setting_backup" name="system_settings[backup_retention_days]"
                                           value="{{ (int) $systemSettings['backup_retention_days'] }}" min="1" max="365" required
                                           class="w-full rounded-l-lg px-3 py-2 text-sm text-ink outline-none">
                                    <span class="px-3 text-sm font-bold text-muted">days</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end border-t border-line pt-5">
                            <button type="submit" class="ui-btn-primary px-5 py-2.5">
                                <i class='bx bx-save'></i> Save System Settings
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ================= STATISTICS TAB ================= --}}
                <div id="panel-statistics" class="sa-panel">
                    <div class="mb-5 flex items-center gap-2">
                        <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                            <i class='bx bxs-bar-chart-alt-2 text-cvsu-green-600'></i> System Statistics
                        </h3>
                        <span class="rounded-full bg-cvsu-green-50 px-3 py-1 text-xs font-semibold text-cvsu-green-700">Live from database</span>
                    </div>

                    @php
                        $groups = [
                            'Users' => [
                                ['Total Users', $stats['total_users'], 'bxs-user', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['Approved', $stats['approved_users'], 'bxs-user-check', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['Pending Approval', $stats['pending_users'], 'bx-user-plus', 'from-cvsu-gold-400 to-cvsu-gold-600'],
                                ['Admins', $stats['admins'], 'bxs-shield-alt-2', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['Super Admins', $stats['super_admins'], 'bxs-crown', 'from-cvsu-gold-400 to-cvsu-gold-600'],
                            ],
                            'Departments & Files' => [
                                ['Departments', $stats['total_departments'], 'bx-building', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['Active Departments', $stats['active_departments'], 'bxs-building-house', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['Total Files', $stats['total_files'], 'bxs-file-blank', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['File Downloads', $stats['file_downloads'], 'bx-download', 'from-cvsu-gold-400 to-cvsu-gold-600'],
                            ],
                            'Announcements & Requests' => [
                                ['Total Announcements', $stats['total_announcements'], 'bxs-megaphone', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['Published', $stats['published_announcements'], 'bxs-megaphone', 'from-cvsu-green-500 to-cvsu-green-700'],
                                ['Document Requests', $stats['total_requests'], 'bx-file', 'from-cvsu-gold-400 to-cvsu-gold-600'],
                                ['Pending Requests', $stats['pending_requests'], 'bx-time', 'from-cvsu-gold-400 to-cvsu-gold-600'],
                                ['Completed Requests', $stats['completed_requests'], 'bxs-check-circle', 'from-cvsu-green-500 to-cvsu-green-700'],
                            ],
                        ];
                    @endphp

                    @foreach ($groups as $groupTitle => $cards)
                        <div class="mb-6">
                            <h4 class="mb-3 text-sm font-bold uppercase tracking-wide text-muted">{{ $groupTitle }}</h4>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
                                @foreach ($cards as [$label, $value, $icon, $gradient])
                                    <div class="ui-card p-4 sm:p-5">
                                        <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br {{ $gradient }} text-white shadow-card">
                                            <i class='bx {{ $icon }} text-xl'></i>
                                        </div>
                                        <p class="text-2xl font-extrabold text-ink">{{ number_format((int) $value) }}</p>
                                        <p class="mt-0.5 text-xs font-medium text-muted">{{ $label }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const tabs = document.querySelectorAll('.sa-tab');
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (t) { t.classList.remove('active'); });
                    document.querySelectorAll('.sa-panel').forEach(function (p) { p.classList.remove('active'); });
                    this.classList.add('active');
                    document.getElementById('panel-' + this.dataset.tab).classList.add('active');
                });
            });
        })();
    </script>
@endsection