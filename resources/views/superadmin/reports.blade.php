@extends('layouts.superadmin')

@section('title', 'Reports & Analytics - CVSU Naic')
@section('body-class', 'superadmin-reports-page')

@section('page-css')
    <style>
        .rep-tab { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.1rem; border-radius: 10px;
            font-size: 0.85rem; font-weight: 600; color: #6b7280 !important; border: 1px solid #e6ebe7; background: #fff; transition: all .2s ease; }
        .rep-tab:hover { border-color: #6ee7a0; color: #16241c !important; }
        .rep-tab.active { background: #1e7e34; border-color: #1e7e34; color: #fff !important; }
        .bar-row { display: flex; align-items: center; gap: 0.75rem; }
        .bar-track { flex: 1; height: 10px; border-radius: 999px; background: #e6ebe7; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #1e7e34, #6ee7a0); }
        .fade-in { animation: repFade .35s ease both; }
        @keyframes repFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    </style>
@endsection

@section('content')
    @php
        $fs = function ($bytes) {
            $bytes = (int) $bytes;
            if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
            if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
            if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
            return $bytes.' B';
        };
        $tabLabels = ['overview' => 'Overview', 'departments' => 'Departments', 'users' => 'Users', 'activities' => 'Activities'];
        $exportParams = array_filter(['type' => $reportType, 'range' => $dateRange,
            'start_date' => $dateRange === 'custom' ? $startDate->format('Y-m-d') : null,
            'end_date' => $dateRange === 'custom' ? $endDate->format('Y-m-d') : null], fn ($v) => $v !== null);
    @endphp

    <div class="p-6 lg:p-8 fade-in">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="flex items-center gap-2 text-2xl font-bold text-ink">
                <i class='bx bxs-chart text-cvsu-green-600'></i> Reports &amp; Analytics
            </h1>
            <ul class="breadcrumb mt-1">
                <li><a href="{{ route('superadmin.dashboard') }}">Dashboard</a></li>
                <li><i class='bx bx-chevron-right'></i></li>
                <li><a class="active" href="#">Reports &amp; Analytics</a></li>
            </ul>
        </div>

        {{-- Tabs + range filter --}}
        <div class="mb-6 rounded-card border border-line bg-white p-5 shadow-card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    @foreach ($tabLabels as $key => $label)
                        <a href="{{ route('superadmin.reports', ['type' => $key, 'range' => $dateRange]) }}"
                           class="rep-tab {{ $reportType === $key ? 'active' : '' }}">
                            <i class='bx {{ match ($key) {
                                'overview' => 'bx-home-alt',
                                'departments' => 'bx-building',
                                'users' => 'bx-group',
                                default => 'bx-list-ul',
                            } }}'></i>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('superadmin.reports') }}" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="type" value="{{ $reportType }}">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Date Range</label>
                        <select name="range" id="reportsRange" class="rounded-lg border border-line bg-white px-3 py-2 text-sm font-semibold text-ink outline-none transition focus:border-cvsu-green-500">
                            <option value="7" @selected($dateRange === '7')>Last 7 Days</option>
                            <option value="30" @selected($dateRange === '30')>Last 30 Days</option>
                            <option value="90" @selected($dateRange === '90')>Last 90 Days</option>
                            <option value="365" @selected($dateRange === '365')>Last Year</option>
                            <option value="custom" @selected($dateRange === 'custom')>Custom Range</option>
                        </select>
                    </div>
                    <div id="customDateFields" class="flex items-end gap-3 {{ $dateRange === 'custom' ? '' : 'hidden' }}">
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">Start</label>
                            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}"
                                   class="rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-cvsu-green-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-muted">End</label>
                            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}"
                                   class="rounded-lg border border-line bg-white px-3 py-2 text-sm text-ink outline-none transition focus:border-cvsu-green-500">
                        </div>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cvsu-green-700">
                        <i class='bx bx-sync'></i> Apply
                    </button>
                    <a href="{{ route('superadmin.reports.export', $exportParams) }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink transition hover:bg-cvsu-green-50">
                        <i class='bx bxs-file-export text-cvsu-green-600'></i> Export CSV
                    </a>
                </form>
            </div>
        </div>

        {{-- Range banner --}}
        <div class="mb-6 flex items-center gap-3 rounded-card border border-cvsu-green-200 bg-cvsu-green-50 px-5 py-3 text-sm text-cvsu-green-800">
            <i class='bx bxs-calendar-check text-lg'></i>
            <span>
                Showing data for <strong>{{ $rangeLabel }}</strong>
                ({{ $startDate->format('M j, Y') }} &rarr; {{ $endDate->format('M j, Y') }})
            </span>
        </div>

        @if ($reportType === 'overview')
            {{-- Stat cards --}}
            <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-6">
                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <p class="text-2xl font-extrabold text-ink">{{ number_format($data['stats']['files']['total_files']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-muted">Total Files</p>
                </div>
                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <p class="text-2xl font-extrabold text-cvsu-green-600">{{ number_format($data['stats']['files']['new_files']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-muted">New Files (range)</p>
                </div>
                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <p class="text-2xl font-extrabold text-ink">{{ $fs($data['stats']['files']['total_size']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-muted">Storage Used</p>
                </div>
                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <p class="text-2xl font-extrabold text-ink">{{ number_format($data['stats']['folders']['total_folders']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-muted">Total Folders</p>
                </div>
                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <p class="text-2xl font-extrabold text-ink">{{ number_format($data['stats']['users']['total_users']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-muted">Approved Users</p>
                </div>
                <div class="rounded-card border border-line bg-white p-5 shadow-card">
                    <p class="text-2xl font-extrabold text-cvsu-gold-600">{{ number_format($data['stats']['documents']['total_requests']) }}</p>
                    <p class="mt-0.5 text-xs font-medium text-muted">Document Requests</p>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                {{-- File distribution --}}
                <div class="rounded-card border border-line bg-white p-6 shadow-card">
                    <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-ink">
                        <i class='bx bxs-pie-chart-alt-2 text-cvsu-green-600'></i> File Distribution
                    </h3>
                    @php
                        $dist = $data['fileDistribution'];
                        $distTotal = max(1, $dist['files'] + $dist['deleted']);
                        $distPct = round(($dist['files'] / $distTotal) * 100);
                    @endphp
                    <div class="space-y-4">
                        <div>
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <span class="font-semibold text-ink">Active Files</span>
                                <span class="text-muted">{{ number_format($dist['files']) }} ({{ $distPct }}%)</span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted/15">
                                <div class="h-full rounded-full bg-cvsu-green-600" style="width: {{ $distPct }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center justify-between text-sm">
                                <span class="font-semibold text-ink">Deleted Files</span>
                                <span class="text-muted">{{ number_format($dist['deleted']) }} ({{ 100 - $distPct }}%)</span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted/15">
                                <div class="h-full rounded-full bg-red-400" style="width: {{ 100 - $distPct }}%"></div>
                            </div>
                        </div>
                        <div class="mt-2 flex items-center gap-3 rounded-lg bg-cvsu-green-50 px-4 py-3">
                            <i class='bx bxs-hdd text-xl text-cvsu-green-600'></i>
                            <div>
                                <p class="text-sm font-bold text-ink">{{ $fs($dist['size']) }}</p>
                                <p class="text-xs text-muted">Total active file size in range</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Department activity --}}
                <div class="rounded-card border border-line bg-white p-6 shadow-card">
                    <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-ink">
                        <i class='bx bxs-bar-chart-alt-2 text-cvsu-green-600'></i> Department Activity
                    </h3>
                    @php $maxActivity = max(1, (int) ($data['departmentActivity']->max('activity_count') ?? 0)); @endphp
                    @if ($data['departmentActivity']->isEmpty())
                        <p class="py-8 text-center text-sm text-muted">No activity recorded in this range.</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($data['departmentActivity'] as $item)
                                <div>
                                    <div class="mb-1 flex items-center justify-between text-sm">
                                        <span class="truncate font-semibold text-ink">{{ $item->department_name }}</span>
                                        <span class="text-muted">{{ number_format($item->activity_count) }}</span>
                                    </div>
                                    <div class="bar-track"><div class="bar-fill" style="width: {{ round(($item->activity_count / $maxActivity) * 100) }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Recent activities --}}
                <div class="rounded-card border border-line bg-white p-6 shadow-card">
                    <h3 class="mb-4 flex items-center gap-2 text-base font-bold text-ink">
                        <i class='bx bxs-bell-ring text-cvsu-gold-500'></i> Recent Activities
                    </h3>
                    @if (empty($data['activities']))
                        <p class="py-8 text-center text-sm text-muted">No activities in this range.</p>
                    @else
                        <ul class="space-y-4">
                            @foreach (array_slice($data['activities'], 0, 8) as $act)
                                <li class="flex gap-3">
                                    <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600">
                                        <i class='bx bx-{{ match ($act->resource_type) {
                                            'file' => 'file', 'folder' => 'folder', 'user' => 'user',
                                            'announcement' => 'megaphone', 'department' => 'building', default => 'cog',
                                        } }}'></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm text-ink">
                                            <span class="font-bold">{{ $act->full_name }}</span>
                                            <span class="text-muted">{{ ucwords(str_replace('_', ' ', $act->action)) }}</span>
                                        </p>
                                        @if ($act->description)
                                            <p class="truncate text-xs text-muted">{{ $act->description }}</p>
                                        @endif
                                        <p class="text-xs text-muted">{{ \Carbon\Carbon::parse($act->created_at)->diffForHumans() }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @elseif ($reportType === 'departments')
            @include('partials.superadmin.reports-departments', ['data' => $data, 'fs' => $fs])
        @elseif ($reportType === 'users')
            @include('partials.superadmin.reports-users', ['data' => $data])
        @else
            @include('partials.superadmin.reports-activities', ['data' => $data])
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const range = document.getElementById('reportsRange');
            const customFields = document.getElementById('customDateFields');
            if (! range || ! customFields) return;
            range.addEventListener('change', function () {
                customFields.classList.toggle('hidden', this.value !== 'custom');
            });
        })();
    </script>
@endsection