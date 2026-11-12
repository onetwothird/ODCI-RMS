@extends('layouts.superadmin')

@section('title', 'User Management')

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
    <div class="right">
        <button class="btn btn-secondary" type="button" title="Refresh" onclick="window.location.reload()">
            <i class='bx bx-refresh'></i>
            <span>Refresh</span>
        </button>
        <button onclick="openModal('createUserModal')" class="btn btn-primary">
            <i class='bx bx-user-plus'></i>
            <span>Add User</span>
        </button>
    </div>
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
<div class="ui-card mb-5 flex flex-col gap-4 p-3 sm:p-4 lg:flex-row lg:items-end lg:justify-between">
    <div class="flex gap-1 overflow-x-auto">
        @foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'restricted' => 'Restricted'] as $key => $label)
            @php $isCurrent = request()->query('filter', 'all') === $key; @endphp
            <a href="{{ request()->fullUrlWithQuery(['filter' => $key, 'page' => 1]) }}"
               class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $isCurrent ? 'bg-cvsu-green-600 text-white' : 'text-cvsu-green-700 hover:bg-cvsu-green-50' }}">
                {{ $label }} ({{ $counts[$key] }})
            </a>
        @endforeach
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:w-[34rem]">
        <div>
            <label for="department-filter" class="ui-label">Department</label>
            <select id="department-filter" onchange="filterUsers()" class="ui-input">
                <option value="">All departments</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" {{ request()->query('department') == $dept->id ? 'selected' : '' }}>
                        {{ $dept->department_code }} - {{ $dept->department_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="role-filter" class="ui-label">Role</label>
            <select id="role-filter" onchange="filterUsers()" class="ui-input">
                <option value="">All roles</option>
                <option value="user" {{ request()->query('role') === 'user' ? 'selected' : '' }}>User</option>
                <option value="admin" {{ request()->query('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="super_admin" {{ request()->query('role') === 'super_admin' ? 'selected' : '' }}>Super admin</option>
            </select>
        </div>
    </div>
</div>

{{-- Search --}}
<form id="userSearchForm" method="GET" class="mb-5 flex gap-2">
    <input type="hidden" name="filter" value="{{ request()->query('filter', 'all') }}">
    <input type="hidden" name="department" value="{{ request()->query('department', '') }}">
    <input type="hidden" name="role" value="{{ request()->query('role', '') }}">
    <input type="text" id="userSearchInput" name="search" placeholder="Search users"
           value="{{ request()->query('search', '') }}" class="ui-input w-full sm:w-72">
    <button type="submit" class="ui-btn-primary"><i class='bx bx-search'></i> Search</button>
    @if (request()->query('search'))
        <a href="{{ request()->fullUrlWithoutQuery(['search', 'page']) }}" class="ui-btn-ghost">
            <i class='bx bx-x'></i> Clear
        </a>
    @endif
</form>

{{-- User grid --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($users as $user)
        @php
            $initials = strtoupper(mb_substr($user->name, 0, 1) . mb_substr($user->surname, 0, 1));
            $statusLabel = ! $user->is_approved ? 'Pending' : ($user->is_restricted ? 'Restricted' : 'Approved');
            $statusClass = ! $user->is_approved
                ? 'bg-cvsu-gold-100 text-cvsu-gold-700'
                : ($user->is_restricted ? 'bg-red-100 text-red-700' : 'bg-cvsu-green-100 text-cvsu-green-800');
        @endphp
        <article class="ui-card flex flex-col gap-4 p-4 sm:p-5">
            <header class="flex items-center gap-3">
                <div class="relative grid h-12 w-12 flex-none place-items-center overflow-hidden rounded-full bg-cvsu-green-600 text-base font-bold text-white">
                    <span>{{ $initials }}</span>
                    @if (! empty($user->profile_image_url))
                        <img src="{{ $user->profile_image_url }}" alt="{{ $user->name }} {{ $user->surname }}"
                             class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-base font-semibold text-ink">{{ $user->name }} {{ $user->mi ? $user->mi . '. ' : '' }}{{ $user->surname }}</h3>
                    <p class="truncate text-sm text-muted">{{ '@'.$user->username }}</p>
                </div>
                <span class="ui-badge shrink-0 {{ $statusClass }}">{{ $statusLabel }}</span>
            </header>

            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                @foreach ([
                    'Email' => $user->email,
                    'Employee ID' => $user->employee_id ?? 'N/A',
                    'Department' => $user->department->department_code ?? 'N/A',
                    'Position' => $user->position ?? 'N/A',
                    'Role' => ucwords(str_replace('_', ' ', $user->role)),
                    'Joined' => \Carbon\Carbon::parse($user->created_at)->format('M j, Y'),
                    'Last login' => $user->last_login ? \Carbon\Carbon::parse($user->last_login)->format('M j, Y') : 'Never',
                ] as $label => $value)
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="min-w-0 break-words text-right font-medium text-ink">{{ $value }}</dd>
                @endforeach
            </dl>

            <footer class="mt-auto grid grid-cols-2 gap-2 [&>*:last-child:nth-child(odd)]:col-span-2">
                <button onclick="editUser({{ $user->id }})" class="ui-btn-primary flex-1"><i class='bx bx-edit'></i> Edit</button>
                @if (! $user->is_approved)
                    <button onclick="approveUser({{ $user->id }})" class="ui-btn-primary flex-1"><i class='bx bx-check'></i> Approve</button>
                @endif
                <button onclick="resetPassword({{ $user->id }})" class="ui-btn-ghost flex-1"><i class='bx bx-key'></i> Reset password</button>
                @if ($user->role !== 'super_admin')
                    <button onclick="deleteUser({{ $user->id }}, @js($user->name.' '.$user->surname))" class="ui-btn-danger flex-1"><i class='bx bx-trash'></i> Delete</button>
                @endif
            </footer>
        </article>
    @empty
        <div class="ui-card col-span-full flex flex-col items-center gap-2 px-6 py-14 text-center">
            <i class='bx bx-user text-5xl text-cvsu-green-600'></i>
            <h3 class="text-lg font-semibold text-ink">No users found</h3>
            <p class="text-sm text-muted">Change the search or filters to see more people.</p>
        </div>
    @endforelse
</div>

{{-- Pagination --}}
@if ($users->lastPage() > 1)
    <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-sm">
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