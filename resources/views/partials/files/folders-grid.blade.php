{{--
    Shared "All Folders" management page.
    Included by admin/folders.blade.php and superadmin/folders.blade.php.

    Vars: $pageTitle, $baseRoute, $folders, $totalFolders, $totalPages, $page,
          $summary, $queryState, $hasActiveFilters, $departments, $statuses, $types
--}}
@php
    $fs = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        return $bytes.' B';
    };
    $qs = function (array $overrides = []) use ($queryState) {
        $state = array_filter($queryState, fn ($v) => $v !== '' && $v !== null);
        return http_build_query(array_merge($state, $overrides));
    };
    $pageUrl = function (int $p) use ($baseRoute, $qs) { return $baseRoute.'?'.$qs(['page' => $p]); };
    $typeLabel = function (string $t) { return ucfirst($t); };
@endphp

<div class="p-6 lg:p-8 fade-in">
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ $pageTitle }}</h1>
            <ul class="breadcrumb mt-1">
                <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">{{ $pageTitle }}</a></li>
            </ul>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="window.location.reload()"
                    class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10">
                <i class='bx bx-refresh'></i><span>Refresh</span>
            </button>
            <div class="inline-flex rounded-lg border border-line bg-white">
                <button type="button" onclick="switchView('grid')" id="viewGridBtn"
                        class="inline-flex items-center gap-2 rounded-l-lg px-3 py-2 text-sm font-semibold text-cvsu-green-600 hover:bg-muted/5"><i class='bx bx-grid-alt'></i></button>
                <button type="button" onclick="switchView('list')" id="viewListBtn"
                        class="inline-flex items-center gap-2 rounded-r-lg px-3 py-2 text-sm font-semibold text-muted hover:bg-muted/5"><i class='bx bx-list-ul'></i></button>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-6 flex items-center gap-2 rounded-lg border-l-4 border-cvsu-green-500 bg-cvsu-green-50 px-4 py-3 text-sm font-semibold text-cvsu-green-700">
            <i class='bx bx-check-circle text-lg'></i>{{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 flex items-center gap-2 rounded-lg border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            <i class='bx bx-error-circle text-lg'></i>{{ session('error') }}
        </div>
    @endif

    {{-- Summary tiles --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-card border border-line bg-white p-4 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600"><i class='bx bxs-folder text-xl'></i></div>
                <div><div class="text-xl font-extrabold text-ink">{{ number_format($summary->total ?? 0) }}</div><div class="text-xs text-muted">Total Folders</div></div>
            </div>
        </div>
        <div class="rounded-card border border-line bg-white p-4 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cvsu-gold-100 text-cvsu-gold-600"><i class='bx bx-globe text-xl'></i></div>
                <div><div class="text-xl font-extrabold text-ink">{{ number_format($summary->public_count ?? 0) }}</div><div class="text-xs text-muted">Public Folders</div></div>
            </div>
        </div>
        <div class="rounded-card border border-line bg-white p-4 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cvsu-gold-100 text-cvsu-gold-600"><i class='bx bx-lock-alt text-xl'></i></div>
                <div><div class="text-xl font-extrabold text-ink">{{ number_format($summary->private_count ?? 0) }}</div><div class="text-xs text-muted">Private Folders</div></div>
            </div>
        </div>
        <div class="rounded-card border border-line bg-white p-4 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600"><i class='bx bxs-file text-xl'></i></div>
                <div><div class="text-xl font-extrabold text-ink">{{ number_format($summary->total_files ?? 0) }}</div><div class="text-xs text-muted">Files Held</div></div>
            </div>
        </div>
        <div class="rounded-card border border-line bg-white p-4 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600"><i class='bx bxs-size text-xl'></i></div>
                <div><div class="text-xl font-extrabold text-ink">{{ $fs($summary->total_size ?? 0) }}</div><div class="text-xs text-muted">Total Size</div></div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="mb-6 rounded-card border border-line bg-white shadow-card overflow-hidden">
        <div class="border-b border-line px-5 py-4">
            <h3 class="flex items-center gap-2 font-bold text-ink"><i class='bx bxs-filter-alt text-xl text-cvsu-green-600'></i> Filter Folders</h3>
        </div>
        <form method="GET" action="{{ $baseRoute }}" class="grid grid-cols-1 gap-3 px-5 py-4 md:grid-cols-2 xl:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Search</label>
                <input type="text" name="search" value="{{ $queryState['search'] }}" placeholder="Folder name, description..."
                       class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Department</label>
                <select name="department" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" {{ (string) $queryState['departmentFilter'] === (string) $dept->id ? 'selected' : '' }}>{{ $dept->department_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Folder Type</label>
                <select name="folder_type" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">All Types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" {{ $queryState['typeFilter'] === $type ? 'selected' : '' }}>{{ $typeLabel($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Status</label>
                <select name="status" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" {{ $queryState['statusFilter'] === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                    <i class='bx bx-filter-alt'></i><span>Apply</span>
                </button>
                <a href="{{ $baseRoute }}" class="inline-flex items-center gap-2 rounded-lg border border-line px-4 py-2 text-sm font-semibold text-muted hover:bg-muted/10">
                    <i class='bx bx-x'></i><span>Clear</span>
                </a>
            </div>
        </form>
    </div>

    {{-- Grid view --}}
    <div id="foldersGridView" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($folders as $folder)
            <div class="rounded-card border border-line bg-white shadow-card overflow-hidden transition-colors hover:border-cvsu-green-300">
                <div class="flex items-start justify-between border-b border-line px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl text-white shadow-sm" style="background: linear-gradient(135deg, {{ $folder->colour }}, {{ $folder->colour }}cc);">
                            <i class='bx {{ $folder->icon }} text-2xl'></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-ink">{{ $folder->folder_name }}</h4>
                            <div class="text-xs text-muted">{{ $folder->department_name ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase {{ $folder->is_public ? 'bg-cvsu-green-100 text-cvsu-green-700' : 'bg-muted/10 text-muted' }}">
                            {{ $folder->is_public ? 'Public' : 'Private' }}
                        </span>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase
                            {{ $folder->folder_status === 'active' ? 'bg-cvsu-gold-100 text-cvsu-gold-700' : ($folder->folder_status === 'archived' ? 'bg-muted/10 text-muted' : 'bg-red-100 text-red-700') }}">
                            {{ $folder->status_label }}
                        </span>
                    </div>
                </div>

                <div class="px-4 py-3">
                    @if (! empty($folder->description))
                        <p class="line-clamp-2 text-sm text-muted">{{ $folder->description }}</p>
                    @endif
                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-cvsu-green-50 py-2">
                            <div class="text-base font-extrabold text-cvsu-green-700">{{ number_format($folder->file_count) }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-muted">Files</div>
                        </div>
                        <div class="rounded-lg bg-cvsu-green-50 py-2">
                            <div class="text-base font-extrabold text-cvsu-green-700">{{ $fs($folder->folder_size) }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-muted">Size</div>
                        </div>
                        <div class="rounded-lg bg-cvsu-gold-50 py-2">
                            <div class="text-base font-extrabold text-cvsu-gold-700">{{ $typeLabel($folder->folder_type) }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-muted">Type</div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-line bg-muted/5 px-4 py-2">
                    <div class="text-xs text-muted">
                        <i class='bx bxs-user'></i> {{ $folder->creator_full_name ?: 'Unknown' }}
                        @if (! empty($folder->parent_folder_name))
                            <span class="ml-1 inline-flex items-center gap-0.5 text-cvsu-green-600"><i class='bx bx-folder'></i>{{ $folder->parent_folder_name }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        <form method="POST" action="{{ route('folders.action') }}" class="inline">
                            @csrf
                            <input type="hidden" name="action" value="toggle_public">
                            <input type="hidden" name="folder_id" value="{{ $folder->id }}">
                            @foreach ($queryState as $k => $v)
                                @if ($v !== '' && $v !== null)
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
                            <button type="submit" title="Make {{ $folder->is_public ? 'private' : 'public' }}"
                                    class="rounded-lg p-1.5 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600">
                                <i class='bx {{ $folder->is_public ? 'bx-lock-alt' : 'bx-globe' }}'></i>
                            </button>
                        </form>
                        <div class="relative inline-block">
                            <button type="button" onclick="toggleStatusMenu(this)" title="Change status"
                                    class="rounded-lg p-1.5 text-muted hover:bg-cvsu-gold-100 hover:text-cvsu-gold-600"><i class='bx bx-flag'></i></button>
                            <form method="POST" action="{{ route('folders.action') }}" class="status-menu absolute right-0 top-8 z-20 hidden w-40 rounded-lg border border-line bg-white p-2 shadow-lg">
                                @csrf
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="folder_id" value="{{ $folder->id }}">
                                @foreach ($queryState as $k => $v)
                                    @if ($v !== '' && $v !== null)
                                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                    @endif
                                @endforeach
                                @foreach ($statuses as $status)
                                    <button type="submit" name="status" value="{{ $status }}"
                                            class="block w-full rounded-md px-3 py-1.5 text-left text-xs font-semibold text-ink hover:bg-muted/10 {{ $folder->folder_status === $status ? 'bg-cvsu-green-50 text-cvsu-green-700' : '' }}">
                                        {{ ucfirst($status) }}
                                    </button>
                                @endforeach
                            </form>
                        </div>
                        <form method="POST" action="{{ route('folders.action') }}" class="inline" onsubmit="return confirm('Delete this folder permanently?')">
                            @csrf
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="folder_id" value="{{ $folder->id }}">
                            @foreach ($queryState as $k => $v)
                                @if ($v !== '' && $v !== null)
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
                            <button type="submit" title="Delete"
                                    class="rounded-lg p-1.5 text-muted hover:bg-red-100 hover:text-red-600"><i class='bx bx-trash'></i></button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-card border border-line bg-white py-16 text-center shadow-card">
                <i class='bx bxs-folder-open text-5xl text-muted/40'></i>
                <p class="mt-3 font-semibold text-ink">No folders found</p>
                <p class="text-sm text-muted">Try adjusting your filters.</p>
            </div>
        @endforelse
    </div>

    {{-- List view --}}
    <div id="foldersListView" class="hidden">
        <div class="rounded-card border border-line bg-white shadow-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-line bg-muted/5 text-xs font-semibold uppercase tracking-wide text-muted">
                            <th class="px-4 py-3">Folder</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Visibility</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3 text-center">Files</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($folders as $folder)
                            <tr class="transition-colors hover:bg-cvsu-green-50/60">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-lg text-white" style="background: {{ $folder->colour }};"><i class='bx {{ $folder->icon }}'></i></div>
                                        <div>
                                            <div class="font-semibold text-ink">{{ $folder->folder_name }}</div>
                                            @if (! empty($folder->parent_folder_name))
                                                <div class="text-xs text-muted"><i class='bx bx-folder'></i>{{ $folder->parent_folder_name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-lg bg-muted/10 px-2 py-1 text-xs font-semibold text-ink">{{ $typeLabel($folder->folder_type) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase
                                        {{ $folder->folder_status === 'active' ? 'bg-cvsu-gold-100 text-cvsu-gold-700' : ($folder->folder_status === 'archived' ? 'bg-muted/10 text-muted' : 'bg-red-100 text-red-700') }}">
                                        {{ $folder->status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink">
                                    <span class="inline-flex items-center gap-1"><i class='bx {{ $folder->is_public ? 'bx-globe text-cvsu-green-600' : 'bx-lock-alt text-muted' }}'></i>{{ $folder->is_public ? 'Public' : 'Private' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $folder->department_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $folder->creator_full_name ?: 'Unknown' }}</td>
                                <td class="px-4 py-3 text-center text-sm font-semibold text-ink">{{ number_format($folder->file_count) }}</td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $fs($folder->folder_size) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ route('folders.action') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="action" value="toggle_public">
                                            <input type="hidden" name="folder_id" value="{{ $folder->id }}">
                                            <button type="submit" title="Toggle public/private" class="rounded-lg p-1.5 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600">
                                                <i class='bx {{ $folder->is_public ? 'bx-lock-alt' : 'bx-globe' }}'></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('folders.action') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="folder_id" value="{{ $folder->id }}">
                                            @foreach ($queryState as $k => $v)
                                                @if ($v !== '' && $v !== null)
                                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                                @endif
                                            @endforeach
                                            <select name="status" onchange="this.form.submit()" title="Change status"
                                                    class="rounded-lg border border-line bg-white px-2 py-1 text-xs font-semibold text-ink focus:border-cvsu-green-500 focus:outline-none">
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status }}" {{ $folder->folder_status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                        <form method="POST" action="{{ route('folders.action') }}" class="inline" onsubmit="return confirm('Delete this folder permanently?')">
                                            @csrf
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="folder_id" value="{{ $folder->id }}">
                                            <button type="submit" title="Delete" class="rounded-lg p-1.5 text-muted hover:bg-red-100 hover:text-red-600"><i class='bx bx-trash'></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-16 text-center">
                                    <i class='bx bxs-folder-open text-5xl text-muted/40'></i>
                                    <p class="mt-3 font-semibold text-ink">No folders found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($totalPages > 1)
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-white px-5 py-4 shadow-card">
            <p class="text-sm text-muted">Showing page {{ $page }} of {{ $totalPages }} ({{ number_format($totalFolders) }} folders)</p>
            <div class="flex gap-1">
                @if ($page > 1)
                    <a href="{{ $pageUrl($page - 1) }}" class="rounded-lg border border-line px-3 py-1.5 text-sm font-semibold text-ink hover:bg-muted/10"><i class='bx bx-chevron-left'></i></a>
                @endif
                @for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++)
                    <a href="{{ $pageUrl($p) }}" class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $p === $page ? 'bg-cvsu-green-600 text-white' : 'border border-line text-ink hover:bg-muted/10' }}">{{ $p }}</a>
                @endfor
                @if ($page < $totalPages)
                    <a href="{{ $pageUrl($page + 1) }}" class="rounded-lg border border-line px-3 py-1.5 text-sm font-semibold text-ink hover:bg-muted/10"><i class='bx bx-chevron-right'></i></a>
                @endif
            </div>
        </div>
    @endif
</div>

<style>
    .fade-in { animation: folderFade .35s ease both; }
    @keyframes folderFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
</style>

<script>
    function switchView(view) {
        const grid = document.getElementById('foldersGridView');
        const list = document.getElementById('foldersListView');
        const gridBtn = document.getElementById('viewGridBtn');
        const listBtn = document.getElementById('viewListBtn');
        if (!grid || !list) return;
        const showGrid = view === 'grid';
        grid.classList.toggle('hidden', !showGrid);
        list.classList.toggle('hidden', showGrid);
        gridBtn.classList.toggle('text-cvsu-green-600', showGrid);
        gridBtn.classList.toggle('text-muted', !showGrid);
        listBtn.classList.toggle('text-cvsu-green-600', !showGrid);
        listBtn.classList.toggle('text-muted', showGrid);
    }
    function toggleStatusMenu(btn) {
        document.querySelectorAll('.status-menu').forEach(m => { if (m !== btn.nextElementSibling) m.classList.add('hidden'); });
        btn.nextElementSibling.classList.toggle('hidden');
    }
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.status-menu') && !e.target.closest('button[onclick*="toggleStatusMenu"]')) {
            document.querySelectorAll('.status-menu').forEach(m => m.classList.add('hidden'));
        }
    });
</script>