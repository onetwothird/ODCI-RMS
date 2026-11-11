@extends('layouts.superadmin')

@section('title', 'Department Management')

@section('page-css-pre')
    <link rel="stylesheet" href="{{ asset('css/superadmin/style.css') }}">
@endsection

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/superadmin/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/superadmin/departments-page.css') }}">
@endsection

@section('content')
<div class="head-title">
    <div class="left">
        <h1>Department Management</h1>
        <ul class="breadcrumb">
            <li><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
            <li><i class='bx bx-chevron-right'></i></li>
            <li><a class="active" href="{{ route('superadmin.departments.index') }}">Departments</a></li>
        </ul>
    </div>
    <button onclick="openModal('createDeptModal')" class="btn-download">
        <i class='bx bx-buildings'></i>
        <span class="text">Add Department</span>
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

{{-- Statistics Overview --}}
<div class="stats-overview">
    <div class="overview-card">
        <i class='bx bxs-buildings'></i>
        <h3>{{ $counts['all'] }}</h3>
        <p>Total Departments</p>
    </div>
    <div class="overview-card">
        <i class='bx bxs-check-circle'></i>
        <h3>{{ $counts['active'] }}</h3>
        <p>Active Departments</p>
    </div>
    <div class="overview-card">
        <i class='bx bxs-x-circle'></i>
        <h3>{{ $counts['inactive'] }}</h3>
        <p>Inactive Departments</p>
    </div>
</div>

{{-- Filters --}}
<div class="filters">
    <div class="filter-tabs">
        <a href="{{ $departments->appends(array_merge(request()->query(), ['status' => 'all', 'page' => 1])) }}"
           class="filter-tab {{ request()->query('status', 'all') === 'all' ? 'active' : '' }}">
            All ({{ $counts['all'] }})
        </a>
        <a href="{{ $departments->appends(array_merge(request()->query(), ['status' => 'active', 'page' => 1])) }}"
           class="filter-tab {{ request()->query('status') === 'active' ? 'active' : '' }}">
            Active ({{ $counts['active'] }})
        </a>
        <a href="{{ $departments->appends(array_merge(request()->query(), ['status' => 'inactive', 'page' => 1])) }}"
           class="filter-tab {{ request()->query('status') === 'inactive' ? 'active' : '' }}">
            Inactive ({{ $counts['inactive'] }})
        </a>
    </div>
</div>

{{-- Search form --}}
<form id="deptSearchForm" method="GET" style="margin-bottom: 20px;">
    <input type="hidden" name="status" value="{{ request()->query('status', 'all') }}">
    <div style="display: flex; gap: 10px; align-items: center;">
        <input type="text" 
               id="deptSearchInput" 
               name="search" 
               placeholder="Search departments..." 
               value="{{ request()->query('search', '') }}"
               style="flex: 1; max-width: 300px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
        <button type="submit" class="btn btn-primary">
            <i class='bx bx-search'></i> Search
        </button>
        @if (request()->query('search'))
            <a href="{{ $departments->appends(array_diff_key(request()->query(), ['search' => ''])) }}" class="btn btn-secondary">
                <i class='bx bx-x'></i> Clear
            </a>
        @endif
    </div>
</form>

{{-- Department Grid --}}
<div class="dept-grid">
    @forelse ($departments as $dept)
        <div class="dept-card {{ $dept->is_active ? '' : 'inactive' }}">
            <div class="dept-header">
                <div class="dept-icon">
                    <i class='bx bxs-buildings'></i>
                </div>
                <div class="dept-status status-{{ $dept->is_active ? 'active' : 'inactive' }}">
                    {{ $dept->is_active ? 'Active' : 'Inactive' }}
                </div>
            </div>

            <div class="dept-info">
                <div class="dept-code">{{ $dept->department_code }}</div>
                <h3>{{ $dept->department_name }}</h3>
                @if ($dept->description)
                    <div class="dept-description">{{ $dept->description }}</div>
                @endif
            </div>

            <div class="dept-stats">
                <div class="stat-item">
                    <span class="stat-number">{{ $dept->user_count }}</span>
                    <div class="stat-label">Users</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">{{ $dept->folder_count }}</span>
                    <div class="stat-label">Folders</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">{{ $dept->file_count }}</span>
                    <div class="stat-label">Files</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">{{ formatFileSize($dept->total_size) }}</span>
                    <div class="stat-label">Storage</div>
                </div>
            </div>

            @if ($dept->head_of_department || $dept->contact_email || $dept->contact_phone)
                <div class="dept-contact">
                    @if ($dept->head_of_department)
                        <div><i class='bx bx-user'></i> {{ $dept->head_of_department }}</div>
                    @endif
                    @if ($dept->contact_email)
                        <div><i class='bx bx-envelope'></i> {{ $dept->contact_email }}</div>
                    @endif
                    @if ($dept->contact_phone)
                        <div><i class='bx bx-phone'></i> {{ $dept->contact_phone }}</div>
                    @endif
                </div>
            @endif

            <div class="dept-actions">
                <button onclick="editDepartment({{ $dept->id }})" class="btn btn-primary">
                    <i class='bx bx-edit'></i> Edit
                </button>

                <button onclick="toggleStatus({{ $dept->id }})" 
                        class="btn {{ $dept->is_active ? 'btn-warning' : 'btn-success' }}">
                    <i class='bx {{ $dept->is_active ? 'bx-pause' : 'bx-play' }}'></i>
                    {{ $dept->is_active ? 'Deactivate' : 'Activate' }}
                </button>

                @if ($dept->user_count == 0 && $dept->folder_count == 0)
                    <button onclick="deleteDepartment({{ $dept->id }}, '{{ $dept->department_name }}')" 
                            class="btn btn-danger">
                        <i class='bx bx-trash'></i> Delete
                    </button>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align: center; padding: 60px; color: #666;">
            <i class='bx bx-buildings' style="font-size: 64px; margin-bottom: 20px; display: block;"></i>
            <h3>No departments found</h3>
            <p>Try adjusting your search criteria or create a new department</p>
            <button onclick="openModal('createDeptModal')" class="btn btn-primary" style="margin-top: 15px;">
                <i class='bx bx-plus'></i> Add Department
            </button>
        </div>
    @endforelse
</div>

{{-- Pagination --}}
@if ($departments->lastPage() > 1)
    <div class="pagination">
        @if ($departments->currentPage() > 1)
            <a href="{{ $departments->previousPageUrl() }}">&laquo; Previous</a>
        @endif

        @for ($i = max(1, $departments->currentPage() - 2); $i <= min($departments->lastPage(), $departments->currentPage() + 2); $i++)
            @if ($i == $departments->currentPage())
                <span class="current">{{ $i }}</span>
            @else
                <a href="{{ $departments->url($i) }}">{{ $i }}</a>
            @endif
        @endfor

        @if ($departments->currentPage() < $departments->lastPage())
            <a href="{{ $departments->nextPageUrl() }}">Next &raquo;</a>
        @endif
    </div>
@endif

{{-- Modals --}}
@include('superadmin.departments._modals')
@endsection

@section('scripts')
<script>
    // Search form
    document.getElementById('deptSearchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const searchInput = document.getElementById('deptSearchInput');
        if (searchInput && searchInput.value.trim()) {
            window.location.href = `{{ route('superadmin.departments.index') }}?search=${encodeURIComponent(searchInput.value.trim())}`;
        }
    });

    // Modal open/close
    window.openModal = function(id) {
        document.getElementById(id).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };
    window.closeModal = function(id) {
        document.getElementById(id).style.display = 'none';
        document.body.style.overflow = '';
    };

    // Edit department
    window.editDepartment = function(deptId) {
        fetch('{{ route('superadmin.departments.index') }}/' + deptId + '/edit')
            .then(response => response.json())
            .then(data => {
                if (data.department) {
                    document.getElementById('editDeptForm').action =
                        '{{ route('superadmin.departments.update', '__DEPT__') }}'.replace('__DEPT__', deptId);
                    document.getElementById('edit_department_id').value = data.department.id;
                    document.getElementById('edit_department_code').value = data.department.department_code;
                    document.getElementById('edit_department_name').value = data.department.department_name;
                    document.getElementById('edit_description').value = data.department.description || '';
                    document.getElementById('edit_head_of_department').value = data.department.head_of_department || '';
                    document.getElementById('edit_contact_email').value = data.department.contact_email || '';
                    document.getElementById('edit_contact_phone').value = data.department.contact_phone || '';
                    document.getElementById('edit_is_active').checked = data.department.is_active;
                    
                    openModal('editDeptModal');
                }
            });
    };

    // Toggle status
    window.toggleStatus = function(deptId) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('superadmin.departments.index') }}/' + deptId + '/toggle-status';
        form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
        document.body.appendChild(form);
        form.submit();
    };

    // Delete department
    window.deleteDepartment = function(deptId, deptName) {
        if (confirm('Are you sure you want to delete ' + deptName + '? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('superadmin.departments.index') }}/' + deptId;
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

    // Deep link: ?action=create (dashboard quick action)
    if (new URLSearchParams(window.location.search).get('action') === 'create') {
        openModal('createDeptModal');
    }
</script>
@endsection