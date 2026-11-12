@extends('layouts.superadmin')

@section('title', 'Activity Logs - CVSU Naic')
@section('body-class', 'superadmin-activity-logs-page')

@section('page-css')
    <style>
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-primary { background: #e0e7ff; color: #4338ca; }
        .log-row { cursor: pointer; transition: background .15s ease; }
        .log-row:hover { background: #f0fdf4; }
        .modal-bg { position: fixed; inset: 0; z-index: 60; display: none; align-items: flex-start; justify-content: center;
            padding: 6vh 1rem; background: rgba(11, 61, 30, .5); backdrop-filter: blur(3px); overflow-y: auto; }
        .modal-bg.show { display: flex; }
        .modal-panel { width: 100%; max-width: 560px; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(11, 61, 30, .28); }
        .fade-in { animation: alFade .35s ease both; }
        @keyframes alFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .spin { animation: spinF .8s linear infinite; }
        @keyframes spinF { to { transform: rotate(360deg); } }
    </style>
@endsection

@section('content')
    @php
        $apiBase = route('superadmin.activity-logs.data');
        $exportUrl = route('superadmin.activity-logs.export');
        $statsUrl = route('superadmin.activity-logs.stats');
        $clearUrl = route('superadmin.activity-logs.clear');
    @endphp

    <div class="p-6 lg:p-8 fade-in">
        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold text-ink">
                    <i class='bx bxs-time-five text-cvsu-green-600'></i> Activity Logs
                </h1>
                <ul class="breadcrumb mt-1">
                    <li><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                    <li><i class='bx bx-chevron-right'></i></li>
                    <li><a class="active" href="#">Activity Logs</a></li>
                </ul>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="exportLogs()"
                        class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink transition hover:bg-cvsu-green-50">
                    <i class='bx bxs-file-export text-cvsu-green-600'></i> Export CSV
                </button>
                <button type="button" onclick="promptClearLogs()"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-danger transition hover:bg-red-50">
                    <i class='bx bx-trash'></i> Clear Old Logs
                </button>
            </div>
        </div>

        {{-- Stats --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cvsu-green-600 text-white">
                    <i class='bx bxs-calendar-check text-xl'></i>
                </div>
                <p class="text-2xl font-extrabold text-ink" id="statToday">—</p>
                <p class="mt-0.5 text-xs font-medium text-muted">Today&rsquo;s Activities</p>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cvsu-green-500 text-white">
                    <i class='bx bxs-calendar-week text-xl'></i>
                </div>
                <p class="text-2xl font-extrabold text-ink" id="statWeek">—</p>
                <p class="mt-0.5 text-xs font-medium text-muted">This Week</p>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cvsu-gold-500 text-white">
                    <i class='bx bxs-calendar text-xl'></i>
                </div>
                <p class="text-2xl font-extrabold text-ink" id="statMonth">—</p>
                <p class="mt-0.5 text-xs font-medium text-muted">This Month</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="mb-6 rounded-card border border-line bg-white p-5 shadow-card">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted">User</label>
                    <select id="filterUser" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-cvsu-green-500">
                        <option value="">All Users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->username }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted">Resource Type</label>
                    <select id="filterResource" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-cvsu-green-500">
                        <option value="">All Types</option>
                        @foreach ($resourceTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted">Action / Keyword</label>
                    <input type="text" id="filterSearch" placeholder="Search action or description..."
                           class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-cvsu-green-500">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted">From</label>
                    <input type="date" id="filterFrom" class="rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-cvsu-green-500">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-muted">To</label>
                    <input type="date" id="filterTo" class="rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-cvsu-green-500">
                </div>
                <button type="button" onclick="loadLogs(1)" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cvsu-green-700">
                    <i class='bx bx-search'></i> Filter
                </button>
                <button type="button" onclick="resetFilters()" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink transition hover:bg-muted/10">
                    <i class='bx bx-reset'></i> Reset
                </button>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-card border border-line bg-white shadow-card">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                    <i class='bx bx-list-ul text-cvsu-green-600'></i> Log Entries
                </h3>
                <span class="text-sm text-muted"><strong id="totalRecords" class="text-ink">0</strong> records</span>
            </div>
            <div class="overflow-x-auto" id="logsTableWrap">
                <div class="flex items-center justify-center gap-3 py-16 text-muted">
                    <i class='bx bx-loader-alt spin text-2xl'></i> Loading logs...
                </div>
            </div>
            <div class="flex flex-col items-center justify-between gap-3 border-t border-line px-5 py-4 sm:flex-row">
                <p class="text-sm text-muted" id="pageInfo">Page 1</p>
                <div class="flex gap-2">
                    <button type="button" id="prevPageBtn" onclick="changePage(-1)" class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-semibold text-ink transition hover:bg-muted/10">
                        <i class='bx bx-chevron-left'></i> Prev
                    </button>
                    <button type="button" id="nextPageBtn" onclick="changePage(1)" class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3 py-1.5 text-sm font-semibold text-ink transition hover:bg-muted/10">
                        Next <i class='bx bx-chevron-right'></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Details modal --}}
    <div class="modal-bg" id="logModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink">Log Details</h3>
                <button type="button" class="rounded-lg p-1 text-2xl text-muted hover:bg-muted/10" onclick="closeLogModal()">&times;</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto px-6 py-5" id="logModalBody"></div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const API_BASE = @json($apiBase);
        const EXPORT_URL = @json($exportUrl);
        const STATS_URL = @json($statsUrl);
        const CLEAR_URL = @json($clearUrl);

        let currentPage = 1;
        let totalPages = 1;

        function currentParams(page) {
            const params = new URLSearchParams();
            params.set('page', page || currentPage);
            params.set('limit', '25');
            const user = document.getElementById('filterUser').value;
            if (user) params.set('user_id', user);
            const resource = document.getElementById('filterResource').value;
            if (resource) params.set('resource_type', resource);
            const search = document.getElementById('filterSearch').value.trim();
            if (search) params.set('search', search);
            const from = document.getElementById('filterFrom').value;
            if (from) params.set('date_from', from);
            const to = document.getElementById('filterTo').value;
            if (to) params.set('date_to', to);
            return params;
        }

        function actionBadge(action) {
            let cls = 'badge-primary';
            let label = action.replace(/_/g, ' ');
            if (action.includes('login')) cls = 'badge-info';
            else if (action.includes('delete') || action.includes('remove')) cls = 'badge-danger';
            else if (action.includes('create') || action.includes('upload') || action.includes('store')) cls = 'badge-success';
            else if (action.includes('update') || action.includes('edit')) cls = 'badge-warning';
            return '<span class="rounded-full px-2.5 py-0.5 text-xs font-bold ' + cls + '">' + label + '</span>';
        }

        function resourceIcon(type) {
            const icons = {
                file: 'bx-file', folder: 'bx-folder', user: 'bx-user',
                announcement: 'bxs-megaphone', department: 'bx-building', system: 'bx-cog'
            };
            return '<i class="bx ' + (icons[type] || 'bx-cog') + '"></i>';
        }

        function fmtDate(dt) {
            if (! dt) return '—';
            const d = new Date(dt.replace(' ', 'T'));
            if (isNaN(d)) return dt;
            return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
        }

        async function loadLogs(page) {
            currentPage = page || 1;
            document.getElementById('logsTableWrap').innerHTML =
                '<div class="flex items-center justify-center gap-3 py-16 text-muted"><i class="bx bx-loader-alt spin text-2xl"></i> Loading logs...</div>';

            const resp = await fetch(API_BASE + '?' + currentParams(currentPage).toString());
            const json = await resp.json();
            if (! json.success) return;

            totalPages = json.pagination.total_pages;
            document.getElementById('totalRecords').textContent = json.pagination.total_records.toLocaleString();
            document.getElementById('pageInfo').textContent = 'Page ' + json.pagination.current_page + ' of ' + Math.max(1, totalPages);
            document.getElementById('prevPageBtn').disabled = json.pagination.current_page <= 1;
            document.getElementById('nextPageBtn').disabled = json.pagination.current_page >= totalPages;

            if (! json.data.length) {
                document.getElementById('logsTableWrap').innerHTML =
                    '<div class="px-5 py-16 text-center text-muted"><i class="bx bxs-inbox text-4xl"></i><p class="mt-2 text-sm font-medium">No log entries found.</p></div>';
                return;
            }

            let html = '<table class="w-full min-w-[860px] text-left text-sm">' +
                '<thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">' +
                '<tr><th class="px-5 py-3">User</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Resource</th>' +
                '<th class="px-5 py-3">Description</th><th class="px-5 py-3">Date / Time</th><th class="px-5 py-3">IP Address</th></tr></thead><tbody>';

            json.data.forEach(function (log, index) {
                html += '<tr class="log-row" onclick="openLogModal(' + index + ')">' +
                    '<td class="px-5 py-3.5"><div class="flex items-center gap-3">' +
                    '<span class="flex h-9 w-9 items-center justify-center rounded-full bg-cvsu-green-100 font-bold text-cvsu-green-700">' +
                    (log.user_full_name ? log.user_full_name.charAt(0) : '?') + '</span>' +
                    '<div><p class="font-semibold text-ink">' + (log.user_full_name || 'Unknown') + '</p>' +
                    '<p class="text-xs text-muted">' + (log.department_name || '') + '</p></div></div></td>' +
                    '<td class="px-5 py-3.5">' + actionBadge(log.action) + '</td>' +
                    '<td class="px-5 py-3.5"><span class="inline-flex items-center gap-1.5 text-muted">' +
                    resourceIcon(log.resource_type) + ' ' + (log.resource_type || '').replace(/_/g, ' ') + '</span></td>' +
                    '<td class="px-5 py-3.5 max-w-xs truncate text-muted">' + (log.description || '') + '</td>' +
                    '<td class="px-5 py-3.5 whitespace-nowrap text-muted">' + fmtDate(log.created_at) + '</td>' +
                    '<td class="px-5 py-3.5 text-muted">' + (log.ip_address || '—') + '</td></tr>';
                window.__logs = json.data;
            });

            html += '</tbody></table>';
            document.getElementById('logsTableWrap').innerHTML = html;
        }

        function openLogModal(index) {
            const log = (window.__logs || [])[index];
            if (! log) return;
            const rows = [
                ['User', log.user_full_name || 'System'],
                ['Action', log.action.replace(/_/g, ' ')],
                ['Resource Type', log.resource_type || '—'],
                ['Description', log.description || '—'],
                ['Date / Time', fmtDate(log.created_at)],
                ['IP Address', log.ip_address || '—'],
                ['User Agent', log.user_agent || '—'],
                ['Department', log.department_name || '—'],
                ['Resource ID', log.resource_id || '—'],
            ];
            document.getElementById('logModalBody').innerHTML =
                '<dl class="space-y-3">' + rows.map(function (r) {
                    return '<div class="flex gap-3 border-b border-line pb-2"><dt class="w-32 shrink-0 text-sm font-semibold text-muted">' +
                        r[0] + '</dt><dd class="text-sm font-medium text-ink break-all">' + (r[1] || '—') + '</dd></div>';
                }).join('') + '</dl>';
            document.getElementById('logModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeLogModal() {
            document.getElementById('logModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        function changePage(delta) {
            const next = currentPage + delta;
            if (next < 1 || next > totalPages) return;
            loadLogs(next);
        }

        function resetFilters() {
            ['filterUser', 'filterResource', 'filterSearch', 'filterFrom', 'filterTo'].forEach(function (id) {
                document.getElementById(id).value = '';
            });
            loadLogs(1);
        }

        function exportLogs() {
            const params = currentParams(1);
            params.delete('page');
            params.delete('limit');
            window.location.href = EXPORT_URL + '?' + params.toString();
        }

        function promptClearLogs() {
            const days = window.prompt('Clear activity logs older than how many days? (minimum 1)', '30');
            if (days === null) return;
            const n = parseInt(days, 10);
            if (isNaN(n) || n < 1) {
                window.alert('Please enter a valid number of days (at least 1).');
                return;
            }
            if (! window.confirm('This will permanently delete activity logs older than ' + n + ' day(s). Continue?')) return;

            fetch(CLEAR_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ days: n }).toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    window.alert(json.message || 'Logs cleared.');
                    loadStats();
                    loadLogs(1);
                })
                .catch(function () { window.alert('Failed to clear logs.'); });
        }

        async function loadStats() {
            try {
                const resp = await fetch(STATS_URL);
                const json = await resp.json();
                if (! json.success) return;
                document.getElementById('statToday').textContent = (json.stats.today_count || 0).toLocaleString();
                document.getElementById('statWeek').textContent = (json.stats.week_count || 0).toLocaleString();
                document.getElementById('statMonth').textContent = (json.stats.month_count || 0).toLocaleString();
            } catch (e) { /* stats are best-effort */ }
        }

        loadStats();
        loadLogs(1);
    </script>
@endsection