@extends('layouts.user')

@section('title', 'Colleagues - CVSU Naic')

@section('body-class', 'user-colleagues-page')

@section('page-css')
    <!-- styles moved out of the body so the shared theme can win -->
    <link rel="stylesheet" href="{{ asset('css/user/colleagues.css') }}">
@endsection

@section('content')
    <div class="head-title">
        <div class="left">
            <h1>Colleagues</h1>
            <ul class="breadcrumb">
                <li><a href="{{ route('user.dashboard') }}">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">Colleagues</a></li>
            </ul>
        </div>
    </div>

    <!-- Statistics Cards -->
    <ul class="box-info">
        <li>
            <i class='bx bxs-group'></i>
            <span class="text">
                <h3>{{ $totalColleagues }}</h3>
                <p>Total Colleagues</p>
            </span>
        </li>
        <li>
            <i class='bx bxs-building'></i>
            <span class="text">
                <h3>{{ $deptColleaguesCount }}</h3>
                <p>Department Colleagues</p>
            </span>
        </li>
        <li>
            <i class='bx bxs-user-check'></i>
            <span class="text">
                <h3>{{ $activeThisWeek }}</h3>
                <p>Active This Week</p>
            </span>
        </li>
    </ul>

    <!-- Search and Filter -->
    <div class="table-data">
        <div class="order">
            <div class="head">
                <h3>Search & Filter</h3>
            </div>
            <form method="GET" action="{{ route('user.colleagues') }}" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Search:</label>
                        <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, username, email, or position...">
                    </div>

                    <div class="form-group">
                        <label>Department:</label>
                        <select name="department">
                            <option value="">All Departments</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $departmentFilter == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->department_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Role:</label>
                        <select name="role">
                            <option value="">All Roles</option>
                            <option value="admin" {{ $roleFilter == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="user" {{ $roleFilter == 'user' ? 'selected' : '' }}>User</option>
                            <option value="super_admin" {{ $roleFilter == 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="{{ route('user.colleagues') }}" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Colleagues List -->
    <div class="table-data">
        <div class="order">
            <div class="head">
                <h3>Colleagues ({{ count($colleagues) }} found)</h3>
            </div>

            @if (empty($colleagues))
                <div class="empty-state">
                    <i class='bx bx-user-x'></i>
                    <p>No colleagues found matching your criteria</p>
                </div>
            @else
                <div class="colleagues-grid">
                    @foreach ($colleagues as $colleague)
                        <div class="colleague-card">
                            <div class="colleague-header">
                                <div class="colleague-avatar">
                                    @if ($colleague['profile_image_url'])
                                        <img src="{{ $colleague['profile_image_url'] }}" alt="{{ $colleague['full_name'] }}">
                                    @else
                                        <span class="avatar-initials" aria-label="{{ $colleague['full_name'] }} profile photo not provided">{{ $colleague['initials'] }}</span>
                                    @endif
                                </div>

                                <div class="colleague-info">
                                    <h4>{{ $colleague['full_name'] }}</h4>
                                    <p class="username">{{ '@' . $colleague['username'] }}</p>

                                    @if ($colleague['position'])
                                        <p class="position">{{ $colleague['position'] }}</p>
                                    @endif
                                </div>

                                <div class="colleague-status">
                                    @php
                                        $statusClass = 'offline';
                                        $statusText = 'Offline';

                                        if ($colleague['last_login']) {
                                            $lastLogin = strtotime($colleague['last_login']);
                                            $now = time();
                                            $diff = $now - $lastLogin;

                                            if ($diff < 300) { // 5 minutes
                                                $statusClass = 'online';
                                                $statusText = 'Online';
                                            } elseif ($diff < 3600) { // 1 hour
                                                $statusClass = 'recent';
                                                $statusText = 'Recently Active';
                                            } elseif ($diff < 86400) { // 1 day
                                                $statusClass = 'today';
                                                $statusText = 'Active Today';
                                            }
                                        }
                                    @endphp
                                    <span class="status-indicator {{ $statusClass }}"></span>
                                    <span class="status-text">{{ $statusText }}</span>
                                </div>
                            </div>

                            <div class="colleague-details">
                                <div class="detail-item">
                                    <i class='bx bx-envelope'></i>
                                    <span>{{ $colleague['email'] }}</span>
                                </div>

                                @if ($colleague['department_name'])
                                    <div class="detail-item">
                                        <i class='bx bx-building'></i>
                                        <span>{{ $colleague['department_name'] }} ({{ $colleague['department_code'] }})</span>
                                    </div>
                                @endif

                                @if ($colleague['employee_id'])
                                    <div class="detail-item">
                                        <i class='bx bx-id-card'></i>
                                        <span>ID: {{ $colleague['employee_id'] }}</span>
                                    </div>
                                @endif

                                @if ($colleague['phone'])
                                    <div class="detail-item">
                                        <i class='bx bx-phone'></i>
                                        <span>{{ $colleague['phone'] }}</span>
                                    </div>
                                @endif

                                <div class="detail-item">
                                    <i class='bx bx-time'></i>
                                    <span>Joined {{ date('M Y', strtotime($colleague['created_at'])) }}</span>
                                </div>

                                @if ($colleague['last_login'])
                                    <div class="detail-item">
                                        <i class='bx bx-log-in'></i>
                                        <span>Last seen {{ date('M d, Y g:i A', strtotime($colleague['last_login'])) }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="colleague-actions">
                                <a href="mailto:{{ $colleague['email'] }}" class="btn-action" title="Send Email">
                                    <i class='bx bx-envelope'></i>
                                </a>

                                @if ($colleague['phone'])
                                    <a href="tel:{{ $colleague['phone'] }}" class="btn-action" title="Call">
                                        <i class='bx bx-phone'></i>
                                    </a>
                                @endif

                                <button type="button" class="btn-action" title="View Profile" aria-label="View {{ $colleague['full_name'] }} profile"
                                    data-profile-name="{{ $colleague['full_name'] }}"
                                    data-profile-username="{{ $colleague['username'] }}"
                                    data-profile-position="{{ $colleague['position'] ?? '' }}"
                                    data-profile-email="{{ $colleague['email'] ?? '' }}"
                                    data-profile-department="{{ trim(($colleague['department_name'] ?? '') . (! empty($colleague['department_code']) ? ' (' . $colleague['department_code'] . ')' : '')) }}"
                                    data-profile-employee-id="{{ $colleague['employee_id'] ?? '' }}"
                                    data-profile-phone="{{ $colleague['phone'] ?? '' }}"
                                    data-profile-joined="{{ date('M Y', strtotime($colleague['created_at'])) }}"
                                    data-profile-last-seen="{{ $colleague['last_login'] ? date('M d, Y g:i A', strtotime($colleague['last_login'])) : '' }}"
                                    data-profile-photo="{{ $colleague['profile_image_url'] }}"
                                    data-profile-initials="{{ $colleague['initials'] }}">
                                    <i class='bx bx-user' aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <dialog class="profile-dialog" id="colleagueProfileDialog" aria-labelledby="profileDialogName">
        <div class="profile-dialog-content">
            <form method="dialog">
                <button class="profile-dialog-close" type="submit" aria-label="Close profile">
                    <i class="bx bx-x" aria-hidden="true"></i>
                </button>
            </form>
            <div class="profile-dialog-header">
                <span class="avatar-initials" id="profileDialogInitials" aria-hidden="true"></span>
                <img id="profileDialogPhoto" alt="" hidden>
                <div>
                    <h2 id="profileDialogName"></h2>
                    <p id="profileDialogUsername"></p>
                </div>
            </div>
            <dl class="profile-dialog-details">
                <div id="profileDialogPositionRow"><dt>Position</dt><dd id="profileDialogPosition"></dd></div>
                <div id="profileDialogDepartmentRow"><dt>Department</dt><dd id="profileDialogDepartment"></dd></div>
                <div id="profileDialogEmailRow"><dt>Email</dt><dd id="profileDialogEmail"></dd></div>
                <div id="profileDialogEmployeeIdRow"><dt>Employee ID</dt><dd id="profileDialogEmployeeId"></dd></div>
                <div id="profileDialogPhoneRow"><dt>Phone</dt><dd id="profileDialogPhone"></dd></div>
                <div><dt>Joined</dt><dd id="profileDialogJoined"></dd></div>
                <div id="profileDialogLastSeenRow"><dt>Last seen</dt><dd id="profileDialogLastSeen"></dd></div>
            </dl>
        </div>
    </dialog>
@endsection

@section('scripts')
    @verbatim
    <script>
    (() => {
        const dialog = document.getElementById('colleagueProfileDialog');
        const photo = document.getElementById('profileDialogPhoto');
        const initials = document.getElementById('profileDialogInitials');

        if (!dialog || !photo || !initials || typeof dialog.showModal !== 'function') {
            console.error('Colleague profile dialog is not supported in this browser.');
            return;
        }

        const profileFields = [
            ['Position', 'profileDialogPosition', 'profileDialogPositionRow'],
            ['Department', 'profileDialogDepartment', 'profileDialogDepartmentRow'],
            ['Email', 'profileDialogEmail', 'profileDialogEmailRow'],
            ['EmployeeId', 'profileDialogEmployeeId', 'profileDialogEmployeeIdRow'],
            ['Phone', 'profileDialogPhone', 'profileDialogPhoneRow'],
            ['Joined', 'profileDialogJoined', null],
            ['LastSeen', 'profileDialogLastSeen', 'profileDialogLastSeenRow']
        ];

        document.addEventListener('click', event => {
            const button = event.target instanceof Element
                ? event.target.closest('[data-profile-name]')
                : null;
            if (!button) return;

            document.getElementById('profileDialogName').textContent = button.dataset.profileName;
            document.getElementById('profileDialogUsername').textContent = '@' + button.dataset.profileUsername;
            initials.textContent = button.dataset.profileInitials;

            for (const [field, elementId, rowId] of profileFields) {
                const value = button.dataset['profile' + field] || '';
                const element = document.getElementById(elementId);
                if (field === 'Email' && value) {
                    const link = document.createElement('a');
                    link.href = 'mailto:' + value;
                    link.textContent = value;
                    element.replaceChildren(link);
                } else {
                    element.textContent = value;
                }
                if (rowId) document.getElementById(rowId).hidden = !value;
            }

            const imageUrl = button.dataset.profilePhoto || '';
            photo.hidden = !imageUrl;
            initials.hidden = Boolean(imageUrl);
            if (imageUrl) {
                photo.src = imageUrl;
                photo.onerror = () => {
                    photo.hidden = true;
                    initials.hidden = false;
                };
                photo.onload = () => {
                    photo.hidden = false;
                    initials.hidden = true;
                };
            } else {
                photo.removeAttribute('src');
            }

            dialog.showModal();
        });

        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });
    })();
    </script>
    @endverbatim
@endsection