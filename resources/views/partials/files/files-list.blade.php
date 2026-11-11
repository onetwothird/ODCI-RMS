{{--
    Shared "All Files" page markup + self-contained JS.
    Included by admin/files.blade.php and superadmin/files.blade.php.

    Vars: $pageTitle, $pageIcon, $baseRoute, $isSuperAdmin, $files, $stats,
          $todayUploads, $totalFiles, $totalPages, $page, $limit,
          $departments, $fileTypes, $users, $academicYears, $semesters, $filters
--}}
@php
    $fs = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        if ($bytes > 1) return $bytes.' bytes';
        if ($bytes === 1) return '1 byte';
        return '0 bytes';
    };
    $qs = function (array $overrides = []) use ($filters) {
        $query = array_filter(array_merge($filters, $overrides), fn ($v) => $v !== '' && $v !== null && $v !== 0);
        return http_build_query($query);
    };
    $pageUrl = function (int $p) use ($baseRoute, $qs) { return $baseRoute.'?'.$qs(['page' => $p]); };
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
            @if (Route::has('user.folders'))
                <a href="{{ route('user.folders') }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                    <i class='bx bxs-cloud-upload'></i><span>Upload File</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Stats row --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600"><i class='bx bxs-file text-xl'></i></div>
                <div>
                    <div class="text-2xl font-extrabold text-ink">{{ number_format($totalFiles) }}</div>
                    <div class="text-sm text-muted">Document Files</div>
                </div>
            </div>
        </div>
        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-cvsu-gold-100 text-cvsu-gold-600"><i class='bx bxs-size text-xl'></i></div>
                <div>
                    <div class="text-2xl font-extrabold text-ink">{{ $fs($stats->total_size ?? 0) }}</div>
                    <div class="text-sm text-muted">Total Storage Used</div>
                </div>
            </div>
        </div>
        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600"><i class='bx bxs-user-account text-xl'></i></div>
                <div>
                    <div class="text-2xl font-extrabold text-ink">{{ number_format($stats->unique_uploaders ?? 0) }}</div>
                    <div class="text-sm text-muted">Unique Uploaders</div>
                </div>
            </div>
        </div>
        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-cvsu-gold-100 text-cvsu-gold-600"><i class='bx bxs-calendar text-xl'></i></div>
                <div>
                    <div class="text-2xl font-extrabold text-ink">{{ number_format($todayUploads) }}</div>
                    <div class="text-sm text-muted">Uploaded Today</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters card --}}
    <div class="mb-6 rounded-card border border-line bg-white shadow-card overflow-hidden">
        <div class="border-b border-line px-5 py-4">
            <h3 class="flex items-center gap-2 font-bold text-ink"><i class='bx bxs-filter-alt text-xl text-cvsu-green-600'></i> Filters</h3>
        </div>
        <form method="GET" action="{{ $baseRoute }}" class="grid grid-cols-1 gap-3 px-5 py-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="File name, uploader..."
                       class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Department</label>
                <select name="department" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">All Departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" {{ (string) $filters['departmentFilter'] === (string) $dept->id ? 'selected' : '' }}>{{ $dept->department_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">File Type</label>
                <select name="file_type" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">All Types</option>
                    @foreach ($fileTypes as $type)
                        <option value="{{ $type }}" {{ $filters['typeFilter'] === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Uploaded By</label>
                <select name="user_filter" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">Anyone</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" {{ (int) $filters['userFilter'] === (int) $u->id ? 'selected' : '' }}>
                            {{ trim($u->name.' '.($u->mi ?? '').' '.$u->surname) }} {{ $u->department_code ? '('.$u->department_code.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Academic Year</label>
                <select name="academic_year" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">All Years</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year }}" {{ (string) $filters['yearFilter'] === (string) $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Semester</label>
                <select name="semester" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">All Semesters</option>
                    @foreach ($semesters as $sem)
                        <option value="{{ $sem }}" {{ $filters['semesterFilter'] === $sem ? 'selected' : '' }}>{{ $sem }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-muted">Upload Date</label>
                <select name="date_filter" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="">Any Time</option>
                    <option value="today" {{ $filters['dateFilter'] === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="week" {{ $filters['dateFilter'] === 'week' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="month" {{ $filters['dateFilter'] === 'month' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="quarter" {{ $filters['dateFilter'] === 'quarter' ? 'selected' : '' }}>Last 90 Days</option>
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

    {{-- Table card --}}
    <div class="rounded-card border border-line bg-white shadow-card overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h3 class="flex items-center gap-2 font-bold text-ink"><i class='{{ $pageIcon }} text-xl text-cvsu-green-600'></i> {{ $pageTitle }} ({{ number_format($totalFiles) }})</h3>
            <div class="flex gap-1">
                <button type="button" onclick="exportFileTable('csv')" title="Export CSV"
                        class="rounded-lg p-2 text-muted hover:bg-muted/10 hover:text-ink"><i class='bx bx-download'></i></button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left" id="fileTable">
                <thead>
                    <tr class="border-b border-line bg-muted/5 text-xs font-semibold uppercase tracking-wide text-muted">
                        <th class="px-4 py-3">File</th>
                        <th class="px-4 py-3">Uploaded By</th>
                        @if ($isSuperAdmin)
                            <th class="px-4 py-3">Department</th>
                        @endif
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Period</th>
                        <th class="px-4 py-3">Size</th>
                        <th class="px-4 py-3">Downloads</th>
                        <th class="px-4 py-3">Uploaded</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($files as $file)
                        <tr class="transition-colors hover:bg-cvsu-green-50/60">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cvsu-green-100 text-cvsu-green-600">
                                        <i class='bx {{ $file->icon }} text-xl'></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="max-w-[260px] truncate text-sm font-semibold text-ink" title="{{ $file->file_name }}">{{ $file->file_name }}</div>
                                        @if (! empty($file->description))
                                            <div class="max-w-[260px] truncate text-xs text-muted" title="{{ $file->description }}">{{ $file->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-ink">{{ $file->uploader_full_name ?: $file->username }}</div>
                                <div class="text-xs text-muted">{{ $file->position ?: 'Faculty' }}</div>
                            </td>
                            @if ($isSuperAdmin)
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-cvsu-green-100 px-2.5 py-1 text-xs font-semibold text-cvsu-green-700">
                                        <i class='bx bx-building'></i>{{ $file->department_name ?? '—' }}
                                    </span>
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                <span class="rounded-lg bg-muted/10 px-2 py-1 text-xs font-semibold text-ink">{{ $file->file_type ?: 'document' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-ink">{{ $file->semester_period ?: '—' }}</div>
                                <div class="text-xs text-muted">{{ $file->academic_year ?: '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $fs($file->file_size) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 text-sm text-ink"><i class='bx bx-download'></i>{{ number_format($file->download_count) }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-muted">{{ \Carbon\Carbon::parse($file->uploaded_at)->format('M d, Y') }}<br><span class="text-xs">{{ \Carbon\Carbon::parse($file->uploaded_at)->format('h:i A') }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="Preview" onclick="previewFile({{ $file->id }}, {{ $file->previewable ? 'true' : 'false' }})"
                                            class="rounded-lg p-2 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600"><i class='bx bx-show'></i></button>
                                    <a href="{{ route('files.download', $file->id) }}" title="Download"
                                       class="rounded-lg p-2 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600"><i class='bx bx-download'></i></a>
                                    <button type="button" title="Edit" onclick="openEditModal({{ $file->id }}, this.getAttribute('data-desc'))" data-desc="{{ $file->description ?? '' }}"
                                            class="rounded-lg p-2 text-muted hover:bg-cvsu-gold-100 hover:text-cvsu-gold-600"><i class='bx bx-edit-alt'></i></button>
                                    <button type="button" title="Delete" onclick="deleteFile({{ $file->id }})"
                                            class="rounded-lg p-2 text-muted hover:bg-red-100 hover:text-red-600"><i class='bx bx-trash'></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isSuperAdmin ? 9 : 8 }}" class="px-4 py-16 text-center">
                                <i class='bx bxs-folder-open text-5xl text-muted/40'></i>
                                <p class="mt-3 font-semibold text-ink">No files found</p>
                                <p class="text-sm text-muted">Try adjusting your filters, or upload a new document.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalPages > 1)
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4">
                <p class="text-sm text-muted">Showing page {{ $page }} of {{ $totalPages }} ({{ number_format($totalFiles) }} files)</p>
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
</div>

{{-- Preview modal --}}
<div class="modal-bg" id="previewModal">
    <div class="modal-panel">
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <h3 class="text-lg font-bold text-ink">File Preview</h3>
            <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div class="max-h-[70vh] overflow-hidden px-6 py-5">
            <div class="flex min-h-[260px] items-center justify-center" id="previewContent"></div>
        </div>
    </div>
</div>

{{-- Edit modal --}}
<div class="modal-bg" id="editModal">
    <div class="modal-panel">
        <div class="flex items-center justify-between border-b border-line px-6 py-4">
            <h3 class="text-lg font-bold text-ink">Edit File Description</h3>
            <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeModal('editModal')">&times;</button>
        </div>
        <div class="px-6 py-5">
            <textarea id="editDescription" rows="4"
                      class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none"
                      placeholder="Add a short description for this file..."></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <button class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10" onclick="closeModal('editModal')">Cancel</button>
                <button class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700" onclick="saveEdit()">
                    <i class='bx bx-save'></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Share modal removed: file_shares.file_id FK points to the `files` table, so the
All Files (document_files) listing cannot share rows. Sharing lives on the user
department folders page (workspace / files-table uploads). --}}

<style>
    .modal-bg { position: fixed; inset: 0; z-index: 60; display: none; align-items: flex-start;
        justify-content: center; padding: 6vh 1rem; background: rgba(11, 61, 30, .5);
        backdrop-filter: blur(3px); overflow-y: auto; }
    .modal-bg.show { display: flex; }
    .modal-panel { width: 100%; max-width: 640px; background: #fff; border-radius: 16px;
        box-shadow: 0 24px 60px rgba(11, 61, 30, .28); }
    .fade-in { animation: fileFade .35s ease both; }
    @keyframes fileFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
</style>

<script>
    window.fileRoutes = {
        update: @json(route('files.update')),
        'delete': @json(route('files.delete')),
        preview: @json(route('files.preview', ['file' => 'FILE_ID'])),
        download: @json(route('files.download', ['file' => 'FILE_ID'])),
    };
    const fileRoutes = window.fileRoutes;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function closeModal(id) { const el = document.getElementById(id); if (el) { el.classList.remove('show'); document.body.style.overflow = ''; } }
    function openModal(id) { const el = document.getElementById(id); if (el) { el.classList.add('show'); document.body.style.overflow = 'hidden'; } }

    function previewFile(fileId, previewable) {
        const content = document.getElementById('previewContent');
        content.innerHTML = '<div class="flex items-center gap-2 py-10 text-muted"><div class="h-5 w-5 animate-spin rounded-full border-2 border-cvsu-green-500 border-t-transparent"></div><span class="text-sm">Loading preview...</span></div>';
        openModal('previewModal');
        const src = fileRoutes.preview.replace('FILE_ID', fileId);
        if (!previewable) {
            content.innerHTML = `
                <div class="text-center py-10">
                    <i class='bx bxs-file text-5xl text-muted/40'></i>
                    <p class="mt-3 font-semibold text-ink">No inline preview available</p>
                    <p class="text-sm text-muted">This file type can't be previewed in the browser. Download it instead.</p>
                    <a href="${fileRoutes.download.replace('FILE_ID', fileId)}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                        <i class='bx bx-download'></i> Download File
                    </a>
                </div>`;
            return;
        }
        // Load the framed preview.
        const frame = document.createElement('iframe');
        frame.src = src;
        frame.className = 'h-[65vh] w-full rounded-lg border border-line bg-white';
        frame.onload = () => { content.innerHTML = ''; content.appendChild(frame); };
        frame.onerror = () => { content.innerHTML = '<div class="text-center py-10 text-red-600"><i class="bx bx-error-circle text-4xl"></i><p class="mt-2">Unable to load preview.</p></div>'; };
    }

    let editingFileId = null;
    function openEditModal(fileId, description) {
        editingFileId = fileId;
        document.getElementById('editDescription').value = description || '';
        openModal('editModal');
    }
    function saveEdit() {
        const description = document.getElementById('editDescription').value;
        fetch(fileRoutes.update, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ file_id: editingFileId, description: description })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { closeModal('editModal'); notify(data.message, 'success'); setTimeout(() => location.reload(), 600); }
            else notify(data.message || 'Update failed', 'error');
        })
        .catch(() => notify('Network error while saving', 'error'));
    }

    function deleteFile(fileId) {
        if (!confirm('Delete this file permanently? This cannot be undone.')) return;
        fetch(fileRoutes['delete'], {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ file_id: fileId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { notify(data.message, 'success'); setTimeout(() => location.reload(), 600); }
            else notify(data.message || 'Delete failed', 'error');
        })
        .catch(() => notify('Network error while deleting', 'error'));
    }

    function escapeCsv(v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; }
    function exportFileTable() {
        const table = document.getElementById('fileTable');
        if (!table) return;
        const rows = [];
        table.querySelectorAll('thead tr, tbody tr').forEach(tr => {
            const cells = [];
            tr.querySelectorAll('th, td').forEach(td => {
                cells.push(escapeCsv(td.innerText.replace(/\s+/g, ' ').trim()));
            });
            if (cells.length) rows.push(cells.join(','));
        });
        const csv = rows.join('\r\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'all-files-' + Date.now() + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
        notify('Exported to CSV.', 'success');
    }

    function notify(message, type) {
        const old = document.querySelector('.odci-notify');
        if (old) old.remove();
        const n = document.createElement('div');
        n.className = 'odci-notify';
        const colors = { success: ['#d4edd9', '#155724', '#28a745'], error: ['#f8d7da', '#721c24', '#dc3545'], info: ['#d1ecf1', '#0c5460', '#17a2b8'] };
        const c = colors[type] || colors.info;
        n.style.cssText = 'position:fixed;top:30px;right:30px;z-index:1001;padding:14px 18px;border-radius:10px;font-weight:600;font-size:14px;box-shadow:0 10px 40px rgba(0,0,0,.2);background:'+c[0]+';color:'+c[1]+';border-left:5px solid '+c[2]+';transform:translateX(400px);transition:all .4s cubic-bezier(.175,.885,.32,1.275);';
        n.textContent = message;
        document.body.appendChild(n);
        setTimeout(() => { n.style.transform = 'translateX(0)'; }, 50);
        setTimeout(() => { n.style.transform = 'translateX(400px)'; setTimeout(() => n.remove(), 400); }, 2200);
    }

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-bg')) {
            e.target.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
</script>