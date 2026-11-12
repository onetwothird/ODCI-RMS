@extends('layouts.superadmin')

@section('title', 'Department Management')

@section('page-css-pre')
    <link rel="stylesheet" href="{{ asset('css/superadmin/style.css') }}">
@endsection

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/superadmin/dashboard.css') }}">
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
    <div class="right">
        <button class="btn btn-secondary" type="button" title="Refresh" onclick="window.location.reload()">
            <i class='bx bx-refresh'></i>
            <span>Refresh</span>
        </button>
        <button onclick="openModal('createDeptModal')" class="btn btn-primary">
            <i class='bx bx-buildings'></i>
            <span>Add Department</span>
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

{{-- Statistics Overview --}}
<div class="mb-5 grid grid-cols-3 gap-2 sm:gap-4">
    @foreach ([
        ['bxs-buildings', $counts['all'], 'Total departments'],
        ['bxs-check-circle', $counts['active'], 'Active departments'],
        ['bxs-x-circle', $counts['inactive'], 'Inactive departments'],
    ] as [$icon, $value, $label])
        <div class="ui-card flex flex-col gap-1 p-3 sm:flex-row sm:items-center sm:gap-3 sm:p-4">
            <i class='bx {{ $icon }} text-2xl text-cvsu-green-600'></i>
            <div>
                <div class="text-2xl font-bold leading-none text-ink">{{ $value }}</div>
                <div class="mt-1 text-xs text-muted sm:text-sm">{{ $label }}</div>
            </div>
        </div>
    @endforeach
</div>

{{-- Filters + search --}}
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex gap-1 overflow-x-auto rounded-full bg-white p-1 shadow-card">
        @foreach (['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
            @php $isCurrent = request()->query('status', 'all') === $key; @endphp
            <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => 1]) }}"
               class="whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $isCurrent ? 'bg-cvsu-green-600 text-white' : 'text-cvsu-green-700 hover:bg-cvsu-green-50' }}">
                {{ $label }} ({{ $counts[$key] }})
            </a>
        @endforeach
    </div>

    <form id="deptSearchForm" method="GET" class="flex gap-2">
        <input type="hidden" name="status" value="{{ request()->query('status', 'all') }}">
        <input type="text" id="deptSearchInput" name="search" placeholder="Search departments"
               value="{{ request()->query('search', '') }}" class="ui-input w-full sm:w-64">
        <button type="submit" class="ui-btn-primary"><i class='bx bx-search'></i> Search</button>
        @if (request()->query('search'))
            <a href="{{ request()->fullUrlWithoutQuery(['search', 'page']) }}" class="ui-btn-ghost">
                <i class='bx bx-x'></i> Clear
            </a>
        @endif
    </form>
</div>

{{-- Department grid --}}
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($departments as $dept)
        <article class="ui-card flex flex-col gap-3 p-4 sm:p-5 {{ $dept->is_active ? '' : 'opacity-75' }}">
            <header class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-bold text-cvsu-green-700">{{ $dept->department_code }}</div>
                    <h3 class="mt-0.5 text-base font-semibold leading-snug text-ink">{{ $dept->department_name }}</h3>
                </div>
                <span class="ui-badge shrink-0 {{ $dept->is_active ? 'bg-cvsu-green-100 text-cvsu-green-800' : 'bg-gray-100 text-muted' }}">
                    {{ $dept->is_active ? 'Active' : 'Inactive' }}
                </span>
            </header>

            @if ($dept->description)
                <p class="text-sm text-muted">{{ $dept->description }}</p>
            @endif

            <dl class="grid grid-cols-4 rounded-xl bg-[#f1f5f2] py-3 text-center">
                @foreach ([['Users', $dept->user_count], ['Folders', $dept->folder_count], ['Files', $dept->file_count], ['Storage', formatFileSize($dept->total_size)]] as [$label, $value])
                    <div class="min-w-0 px-1">
                        <dd class="whitespace-nowrap text-base font-bold text-ink">{{ $value }}</dd>
                        <dt class="text-xs text-muted">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>

            @if ($dept->head_of_department || $dept->contact_email || $dept->contact_phone)
                <ul class="grid gap-1 text-sm text-gray-700">
                    @if ($dept->head_of_department)
                        <li class="flex items-center gap-2"><i class='bx bx-user text-cvsu-green-600'></i> {{ $dept->head_of_department }}</li>
                    @endif
                    @if ($dept->contact_email)
                        <li class="flex items-center gap-2 break-all"><i class='bx bx-envelope text-cvsu-green-600'></i> {{ $dept->contact_email }}</li>
                    @endif
                    @if ($dept->contact_phone)
                        <li class="flex items-center gap-2"><i class='bx bx-phone text-cvsu-green-600'></i> {{ $dept->contact_phone }}</li>
                    @endif
                </ul>
            @endif

            <footer class="mt-auto grid grid-cols-2 gap-2 pt-1 [&>*:last-child:nth-child(odd)]:col-span-2">
                <button onclick="editDepartment({{ $dept->id }})" class="ui-btn-ghost flex-1">
                    <i class='bx bx-edit'></i> Edit
                </button>
                <button onclick="toggleStatus({{ $dept->id }})" class="ui-btn-ghost flex-1">
                    <i class='bx {{ $dept->is_active ? 'bx-pause' : 'bx-play' }}'></i>
                    {{ $dept->is_active ? 'Deactivate' : 'Activate' }}
                </button>
                @if ($dept->user_count == 0 && $dept->folder_count == 0)
                    <button onclick="deleteDepartment({{ $dept->id }}, @js($dept->department_name))" class="ui-btn-danger flex-1">
                        <i class='bx bx-trash'></i> Delete
                    </button>
                @endif
            </footer>
        </article>
    @empty
        <div class="ui-card col-span-full flex flex-col items-center gap-2 px-6 py-14 text-center">
            <i class='bx bx-buildings text-5xl text-cvsu-green-600'></i>
            <h3 class="text-lg font-semibold text-ink">No departments found</h3>
            <p class="text-sm text-muted">Change the search or add a new department.</p>
            <button onclick="openModal('createDeptModal')" class="ui-btn-primary mt-2">
                <i class='bx bx-plus'></i> Add department
            </button>
        </div>
    @endforelse
</div>

{{-- Pagination --}}
@if ($departments->lastPage() > 1)
    <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-sm">
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