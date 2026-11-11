@extends('layouts.superadmin')

@section('title', 'User Management')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/superadmin/users.css') }}">
@endsection

@section('content')
<div class="head-title">
    <div class="left">
        <h1>User Management</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="{{ route('superadmin.users.index') }}">Users</a></li>
        </ul>
    </div>
    <button onclick="openModal('createUserModal')" class="btn-download">
        <i class='bx bx-user-plus'></i>
        <span class="text">Add User</span>
    </button>
</div>

{{-- Flash messages --}}
@if (session('success'))
    <div class="alert alert-success">
        <i class='bx bx-check-circle'></i> {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-error">
        <i class='bx bx-error-circle'></i> {{ session('error') }}
    </div>
@endif

{{-- Filters --}}
<div class="filters">
    <div class="filter-tabs">
        <a href="{{ $users->appends(array_merge(request()->query(), ['filter' => 'all', 'page' => 1])) }}"
           class="filter-tab {{ request()->query('filter', 'all') === 'all' ? 'active' : '' }}">
            All ({{ $counts['all'] }})
        </a>
        <a href="{{ $users->appends(array_merge(request()->query(), ['filter' => 'pending', 'page' => 1])) }}"
           class="filter-tab {{ request()->query('filter') === 'pending' ? 'active' : '' }}">
            Pending ({{ $counts['pending'] }})
        </a>
        <a href="{{ $users->appends(array_merge(request()->query(), ['filter' => 'approved', 'page' => 1])) }}"
           class="filter-tab {{ request()->query('filter') === 'approved' ? 'active' : '' }}">
            Approved ({{ $counts['approved'] }})
        </a>
        <a href="{{ $users->appends(array_merge(request()->query(), ['filter' => 'restricted', 'page' => 1])) }}"
           class="filter-tab {{ request()->query('filter') === 'restricted' ? 'active' : '' }}">
            Restricted ({{ $counts['restricted'] }})
        </a>
    </div>

    <div class="filter-group">
        <label for="department-filter">Department:</label>
        <select id="department-filter" onchange="filterUsers()">
            <option value="">All Departments</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}" {{ request()->query('department') == $dept->id ? 'selected' : '' }}>
                    {{ $dept->department_code }} - {{ $dept->department_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="filter-group">
        <label for="role-filter">Role:</label>
        <select id="role-filter" onchange="filterUsers()">
            <option value="">All Roles</option>
            <option value="user" {{ request()->query('role') === 'user' ? 'selected' : '' }}>User</option>
            <option value="admin" {{ request()->query('role') === 'admin' ? 'selected' : '' }}>Admin</option>
            <option value="super_admin" {{ request()->query('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
        </select>
    </div>
</div>

{{-- Search form (JS will wire it up) --}}
<form id="userSearchForm" method="GET" style="margin-bottom: 20px;">
    <input type="hidden" name="filter" value="{{ request()->query('filter', 'all') }}">
    <input type="hidden" name="department" value="{{ request()->query('department', '') }}">
    <input type="hidden" name="role" value="{{ request()->query('role', '') }}">
    <div style="display: flex; gap: 10px; align-items: center;">
        <input type="text" 
               id="userSearchInput" 
               name="search" 
               placeholder="Search users..." 
               value="{{ request()->query('search', '') }}"
               style="flex: 1; max-width: 300px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
        <button type="submit" class="btn btn-primary">
            <i class='bx bx-search'></i> Search
        </button>
        @if (request()->query('search'))
            <a href="{{ $users->appends(array_diff_key(request()->query(), ['search' => ''])) }}" class="btn btn-secondary">
                <i class='bx bx-x'></i> Clear
            </a>
        @endif
    </div>
</form>

{{-- User Grid --}}
<div class="user-grid">
    @forelse ($users as $user)
        <div class="user-card">
            <div class="user-header">
                <div class="user-avatar">
                    @php
                        $initials = strtoupper(mb_substr($user->name, 0, 1) . mb_substr($user->surname, 0, 1));
                        $hasProfileImage = !empty($user->profile_image_url);
                    @endphp

                    @if ($hasProfileImage)
                        <img src="{{ $user->profile_image_url }}" 
                             alt="{{ $user->name }} {{ $user->surname }}" 
                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="fallback-avatar" 
                             style="display: none; width: 100%; height: 100%; 
                                    background: linear-gradient(135deg, #007bff, #4a90e2); 
                                    color: white; font-weight: bold; 
                                    align-items: center; justify-content: center; 
                                    border-radius: 50%;">
                            {{ $initials }}
                        </div>
                    @else
                        <div class="letter-avatar" 
                             style="width: 100%; height: 100%; 
                                    background: linear-gradient(135deg, #007bff, #4a90e2); 
                                    color: white; font-weight: bold; 
                                    display: flex; align-items: center; justify-content: center; 
                                    border-radius: 50%;">
                            {{ $initials }}
                        </div>
                    @endif
                </div>
                <div class="user-info">
                    <h3>{{ $user->name }} {{ $user->mi ? $user->mi . '. ' : '' }}{{ $user->surname }}</h3>
                    <p>{{ "@".$user->username }}</p>
                </div>
            </div>

            <div class="user-details">
                <div>
                    <span>Email:</span>
                    <span>{{ $user->email }}</span>
                </div>
                <div>
                    <span>Employee ID:</span>
                    <span>{{ $user->employee_id ?? 'N/A' }}</span>
                </div>
                <div>
                    <span>Department:</span>
                    <span>{{ $user->department->department_code ?? 'N/A' }}</span>
                </div>
                <div>
                    <span>Position:</span>
                    <span>{{ $user->position ?? 'N/A' }}</span>
                </div>
                <div>
                    <span>Role:</span>
                    <span style="text-transform: capitalize;">{{ $user->role }}</span>
                </div>
                <div>
                    <span>Status:</span>
                    <span class="user-status {{ 
                        !$user->is_approved ? 'status-pending' : 
                        ($user->is_restricted ? 'status-restricted' : 'status-approved') 
                    }}">
                        @if (!$user->is_approved) Pending
                        @elseif ($user->is_restricted) Restricted
                        @else Approved
                        @endif
                    </span>
                </div>
                <div>
                    <span>Joined:</span>
                    <span>{{ \Carbon\Carbon::parse($user->created_at)->format('M j, Y') }}</span>
                </div>
                @if ($user->last_login)
                    <div>
                        <span>Last Login:</span>
                        <span>{{ \Carbon\Carbon::parse($user->last_login)->format('M j, Y') }}</span>
                    </div>
                @endif
            </div>

            <div class="user-actions">
                <button onclick="editUser({{ $user->id }})" class="btn btn-primary">
                    <i class='bx bx-edit'></i> Edit
                </button>

                @if (!$user->is_approved)
                    <button onclick="approveUser({{ $user->id }})" class="btn btn-success">
                        <i class='bx bx-check'></i> Approve
                    </button>
                @endif

                <button onclick="resetPassword({{ $user->id }})" class="btn btn-warning">
                    <i class='bx bx-key'></i> Reset
                </button>

                @if ($user->role !== 'super_admin')
                    <button onclick="deleteUser({{ $user->id }}, '{{ $user->name }} {{ $user->surname }}')" class="btn btn-danger">
                        <i class='bx bx-trash'></i> Delete
                    </button>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align: center; padding: 60px; color: #666;">
            <i class='bx bx-user' style="font-size: 64px; margin-bottom: 20px; display: block;"></i>
            <h3>No users found</h3>
            <p>Try adjusting your search or filter criteria</p>
        </div>
    @endforelse
</div>

{{-- Pagination --}}
@if ($users->lastPage() > 1)
    <div class="pagination">
        {{-- Previous --}}
        @if ($users->currentPage() > 1)
            <a href="{{ $users->previousPageUrl() }}">&laquo; Previous</a>
        @endif

        {{-- Page numbers --}}
        @for ($i = max(1, $users->currentPage() - 2); $i <= min($users->lastPage(), $users->currentPage() + 2); $i++)
            @if ($i == $users->currentPage())
                <span class="current">{{ $i }}</span>
            @else
                <a href="{{ $users->url($i) }}">{{ $i }}</a>
            @endif
        @endfor

        {{-- Next --}}
        @if ($users->currentPage() < $users->lastPage())
            <a href="{{ $users->nextPageUrl() }}">Next &raquo;</a>
        @endif
    </div>
@endif

{{-- Modals --}}
@include('superadmin.users._modals')
@endsection

@section('scripts')
<script>
    // Search form submission
    document.getElementById('userSearchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const searchInput = document.getElementById('userSearchInput');
        if (searchInput && searchInput.value.trim()) {
            window.location.href = `{{ route('superadmin.users.index') }}?search=${encodeURIComponent(searchInput.value.trim())}`;
        }
    });

    // Filter functions
    function filterUsers() {
        const department = document.getElementById('department-filter').value;
        const role = document.getElementById('role-filter').value;
        const search = document.getElementById('userSearchInput')?.value || '';
        
        let url = '{{ route('superadmin.users.index') }}?';
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (department) params.set('department', department);
        if (role) params.set('role', role);
        // preserve current filter tab
        params.set('filter', '{{ request()->query('filter', 'all') }}');
        
        window.location.href = url + params.toString();
    }

    // Modal open/close helpers
    window.openModal = function(id) {
        document.getElementById(id).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };
    window.closeModal = function(id) {
        document.getElementById(id).style.display = 'none';
        document.body.style.overflow = '';
    };

    // Edit user
    window.editUser = function(userId) {
        fetch('{{ route('superadmin.users.index') }}/' + userId + '/edit')
            .then(response => response.json())
            .then(data => {
                if (data.user) {
                    document.getElementById('editUserForm').action =
                        '{{ route('superadmin.users.update', '__USER__') }}'.replace('__USER__', userId);
                    document.getElementById('edit_user_id').value = data.user.id;
                    document.getElementById('edit_name').value = data.user.name;
                    document.getElementById('edit_mi').value = data.user.mi || '';
                    document.getElementById('edit_surname').value = data.user.surname;
                    document.getElementById('edit_employee_id').value = data.user.employee_id || '';
                    document.getElementById('edit_position').value = data.user.position || '';
                    document.getElementById('edit_department_id').value = data.user.department_id || '';
                    document.getElementById('edit_role').value = data.user.role;
                    document.getElementById('edit_phone').value = data.user.phone || '';
                    document.getElementById('edit_address').value = data.user.address || '';
                    document.getElementById('edit_is_approved').checked = data.user.is_approved;
                    document.getElementById('edit_is_restricted').checked = data.user.is_restricted;
                    
                    openModal('editUserModal');
                }
            });
    };

    // Approve user
    window.approveUser = function(userId) {
        if (confirm('Are you sure you want to approve this user?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('superadmin.users.index') }}/' + userId + '/approve';
            form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
            document.body.appendChild(form);
            form.submit();
        }
    };

    // Reset password
    window.resetPassword = function(userId) {
        document.getElementById('resetPasswordForm').action =
            '{{ route('superadmin.users.reset-password', '__USER__') }}'.replace('__USER__', userId);
        document.getElementById('reset_user_id').value = userId;
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        openModal('resetPasswordModal');
    };

    // Delete user
    window.deleteUser = function(userId, userName) {
        if (confirm('Are you sure you want to delete ' + userName + '? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('superadmin.users.index') }}/' + userId;
            form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                             '<input type="hidden" name="_method" value="DELETE">';
            document.body.appendChild(form);
            form.submit();
        }
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this.id);
        });
    });

    // Deep links: ?action=create (dashboard quick action) / ?filter=*
    if (new URLSearchParams(window.location.search).get('action') === 'create') {
        openModal('createUserModal');
    }
</script>
@endsection