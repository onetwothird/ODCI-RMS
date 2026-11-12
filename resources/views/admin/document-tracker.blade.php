@extends('layouts.admin')

@section('title', 'Document Tracker - CVSU Naic')
@section('body-class', 'admin-document-tracker-page')

@section('page-css')
    <style>
        .dt-table { border-collapse: collapse; width: 100%; }
        .dt-table th, .dt-table td { border-bottom: 1px solid var(--cvsu-line); }
        .dt-table thead th { background: var(--cvsu-green-600); color: #fff; font-size: 11px;
            letter-spacing: .05em; text-transform: uppercase; padding: 10px 8px; }
        .dt-table thead th.rowspan-cell { background: var(--cvsu-green-700); }
        .dt-table tbody tr:hover { background: var(--cvsu-green-50); }
        .dt-table td.doc-cell { cursor: pointer; text-align: center; padding: 12px 8px; }
        .dt-table td.doc-cell.submitted { background: var(--cvsu-green-50); }
        .dt-table td.doc-cell.missing { background: #fff; }
        .vertical-header { min-width: 64px; height: 150px; text-align: center; vertical-align: bottom; padding: 8px 4px; }
        .vertical-header span { display: inline-block; max-height: 134px; overflow: hidden; white-space: nowrap;
            writing-mode: vertical-rl; transform: rotate(180deg); text-overflow: ellipsis; }
        .modal-bg { position: fixed; inset: 0; z-index: 60; display: none; align-items: flex-start;
            justify-content: center; padding: 6vh 1rem; background: rgba(11, 61, 30, .5);
            backdrop-filter: blur(3px); overflow-y: auto; }
        .modal-bg.show { display: flex; }
        .modal-panel { width: 100%; max-width: 640px; background: #fff; border-radius: 16px;
            box-shadow: 0 24px 60px rgba(11, 61, 30, .28); }
        .file-row { border: 1px solid var(--cvsu-line); border-radius: 10px; padding: 12px 14px;
            background: #fff; }
        .file-row:hover { border-color: var(--cvsu-green-300); box-shadow: var(--cvsu-shadow); }
        .fade-in { animation: dtFade .35s ease both; }
        @keyframes dtFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    </style>
@endsection

@section('content')
    <div class="p-6 lg:p-8 fade-in">
        {{-- Header --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-ink">Document Submission Tracker</h1>
                <ul class="breadcrumb mt-1">
                    <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li><i class='bx bx-chevron-right'></i></li>
                    <li><a class="active" href="#">Document Tracker</a></li>
                    <li><i class='bx bx-chevron-right'></i></li>
                    <li><span class="text-cvsu-green-600 font-semibold">{{ $departmentName }}</span></li>
                </ul>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="window.location.reload()"
                        class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10">
                    <i class='bx bx-refresh'></i><span>Refresh</span>
                </button>
                <a href="{{ Route::has('admin.files') ? route('admin.files') : '#' }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                    <i class='bx bxs-cloud-upload'></i><span>Upload File</span>
                </a>
            </div>
        </div>

        {{-- Status overview --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div class="rounded-card border border-line bg-white p-5 shadow-card flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full {{ $totalFaculty > 0 ? 'bg-cvsu-green-100 text-cvsu-green-600' : 'bg-cvsu-gold-100 text-cvsu-gold-600' }}">
                    <i class='bx bxs-user-account text-2xl'></i>
                </div>
                <div>
                    <div class="text-3xl font-extrabold text-ink">{{ number_format($totalFaculty) }}</div>
                    <div class="text-sm text-muted">Total Faculty &middot; in Department</div>
                </div>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full {{ $completeFaculty > 0 ? 'bg-cvsu-green-100 text-cvsu-green-600' : 'bg-cvsu-gold-100 text-cvsu-gold-600' }}">
                    <i class='bx bxs-check-circle text-2xl'></i>
                </div>
                <div>
                    <div class="text-3xl font-extrabold text-ink">{{ number_format($completeFaculty) }}</div>
                    <div class="text-sm text-muted">Complete Submissions &middot;
                        {{ $totalFaculty > 0 ? number_format($completeFaculty).' of '.number_format($totalFaculty).' faculty' : '' }}</div>
                </div>
            </div>
        </div>

        {{-- Stats cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex items-center gap-2 mb-1">
                    <i class='bx bx-cloud-upload text-2xl text-cvsu-green-500'></i>
                    <h3 class="text-2xl font-extrabold text-ink">{{ number_format($submittedCount) }}</h3>
                </div>
                <p class="text-sm text-muted">Documents Submitted</p>
                <p class="mt-1 text-xs text-cvsu-green-600"><i class='bx bx-up-arrow-alt'></i> Out of {{ number_format($totalPossible) }} required</p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-cvsu-green-100">
                    <div class="h-full rounded-full bg-cvsu-green-500 transition-all"
                         style="width: {{ $totalPossible > 0 ? ($submittedCount / $totalPossible) * 100 : 0 }}%"></div>
                </div>
                <div class="mt-1 text-[11px] text-muted">{{ $totalPossible > 0 ? number_format(($submittedCount / $totalPossible) * 100, 1) : 0 }}% of all required documents</div>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex items-center gap-2 mb-1">
                    <i class='bx bxs-pie-chart-alt text-2xl text-cvsu-gold-500'></i>
                    <h3 class="text-2xl font-extrabold text-ink">{{ number_format($completionRate, 1) }}%</h3>
                </div>
                <p class="text-sm text-muted">Overall Completion</p>
                <p class="mt-1 text-xs text-cvsu-gold-600"><i class='bx bx-trending-up'></i> Document submission rate</p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-cvsu-gold-100">
                    <div class="h-full rounded-full bg-cvsu-gold-500 transition-all" style="width: {{ $completionRate }}%"></div>
                </div>
                <div class="mt-1 text-[11px] text-muted">{{ $facultyCompletionRate }}% of faculty have complete submissions</div>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex items-center gap-2 mb-1">
                    <i class='bx bxs-file text-2xl text-cvsu-green-500'></i>
                    <h3 class="text-2xl font-extrabold text-ink">{{ count($documentTypes) }}</h3>
                </div>
                <p class="text-sm text-muted">Document Types Required</p>
                <p class="mt-1 text-xs text-muted"><i class='bx bx-list-ul'></i> Tracked per faculty per period</p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-cvsu-green-100">
                    <div class="h-full w-full rounded-full bg-cvsu-green-600"></div>
                </div>
                <div class="mt-1 text-[11px] text-muted">All {{ count($documentTypes) }} types active for tracking</div>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex items-center gap-2 mb-1">
                    <i class='bx bxs-calendar text-2xl text-cvsu-gold-500'></i>
                    <h3 class="text-lg font-extrabold text-ink truncate">{{ $selectedSemester }}</h3>
                </div>
                <p class="text-sm text-muted">Active Period</p>
                <p class="mt-1 text-xs text-muted"><i class='bx bx-time'></i> Academic Year Tracking</p>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-cvsu-gold-100">
                    <div class="h-full w-full rounded-full bg-cvsu-gold-400"></div>
                </div>
                <div class="mt-1 text-[11px] text-muted">Filter by period above to change view</div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-6 items-start">
            {{-- Matrix table card --}}
            <div class="rounded-card border border-line bg-white shadow-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h3 class="flex items-center gap-2 font-bold text-ink">
                        <i class='bx bxs-spreadsheet text-xl text-cvsu-green-600'></i> Faculty Document Tracker
                    </h3>
                    <div class="flex gap-1">
                        <button type="button" onclick="document.getElementById('periodFilterForm').scrollIntoView({behavior:'smooth'})"
                                title="Filter" class="rounded-lg p-2 text-muted hover:bg-muted/10 hover:text-ink"><i class='bx bx-filter'></i></button>
                        <button type="button" onclick="window.location.reload()" title="Refresh"
                                class="rounded-lg p-2 text-muted hover:bg-muted/10 hover:text-ink"><i class='bx bx-refresh'></i></button>
                        <button type="button" onclick="exportTable('csv')" title="Export"
                                class="rounded-lg p-2 text-muted hover:bg-muted/10 hover:text-ink"><i class='bx bx-download'></i></button>
                    </div>
                </div>

                {{-- Period filter --}}
                <div class="border-b border-line bg-muted/5 px-5 py-4">
                    <form method="GET" id="periodFilterForm" class="flex flex-wrap items-center gap-3">
                        <label class="text-xs font-semibold uppercase tracking-wide text-muted">Academic Period</label>
                        <div class="relative">
                            <select name="semester" onchange="this.form.submit()"
                                    class="appearance-none rounded-lg border border-line bg-white py-2 pl-3 pr-9 text-sm font-medium text-ink focus:border-cvsu-green-500 focus:outline-none">
                                @forelse ($availablePeriods as $period)
                                    @php
                                        $pValue = \App\Services\DocumentPeriodHelper::makePeriodLabel((int) $period->start_year, $period->semester);
                                        $pDisplay = $pValue.' ('.number_format($period->file_count).' files)';
                                    @endphp
                                    <option value="{{ $pValue }}" {{ $selectedSemester === $pValue ? 'selected' : '' }}>{{ $pDisplay }}</option>
                                @empty
                                    <option value="">No data available</option>
                                @endforelse
                            </select>
                            <i class="bx bx-chevron-down pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-muted"></i>
                        </div>
                        <input type="hidden" name="search" value="{{ $search }}">
                        <input type="hidden" name="status" value="{{ $statusFilter }}">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-cvsu-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                            <i class="bx bx-refresh"></i><span>Update</span>
                        </button>
                    </form>
                </div>

                {{-- Table header info --}}
                <div class="border-b border-line px-5 py-4 text-center">
                    <h2 class="text-sm font-extrabold tracking-wide text-ink">CAVITE STATE UNIVERSITY - NAIC CAMPUS</h2>
                    <div class="mt-2 flex flex-wrap justify-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-cvsu-green-100 px-3 py-1 text-xs font-semibold text-cvsu-green-700">
                            <i class="bx bx-building"></i> {{ $departmentName }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-cvsu-gold-100 px-3 py-1 text-xs font-semibold text-cvsu-gold-700">
                            <i class="bx bx-time"></i> {{ $selectedSemester }}
                        </span>
                    </div>
                </div>

                {{-- Matrix --}}
                <div class="overflow-x-auto">
                    @if ($faculty->isEmpty())
                        <div class="empty-state m-6">
                            <i class='bx bx-user-x text-5xl text-muted'></i>
                            <h3>No Faculty Members Found</h3>
                            <p>No faculty members match the selected period or filters.</p>
                            <a href="{{ route('admin.document-tracker') }}" class="btn btn-primary">Reset Filters</a>
                        </div>
                    @else
                        <table class="dt-table" id="documentTable">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="rowspan-cell text-left" style="min-width: 240px;">
                                        FACULTY STAFF<small style="display:block;font-weight:400;text-transform:none;margin-top:4px;">Click to view details</small>
                                    </th>
                                    <th colspan="{{ count($documentTypes) }}" class="text-center">DOCUMENTS (Auto-tracked by File Uploads)</th>
                                    <th rowspan="2" class="rowspan-cell text-center" style="min-width: 110px;">PROGRESS</th>
                                </tr>
                                <tr>
                                    @foreach ($documentTypes as $docType)
                                        <th class="vertical-header" title="{{ $docType }}">
                                            <span>{{ strlen($docType) > 18 ? substr($docType, 0, 18).'...' : $docType }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($faculty as $staff)
                                    @php
                                        $staffSubmitted = 0;
                                        foreach ($documentTypes as $dt) {
                                            if (isset($fileSubmissions[$staff->id][$dt])) { $staffSubmitted++; }
                                        }
                                        $staffCount = count($documentTypes);
                                        $staffProgress = $staffCount > 0 ? (int) round(($staffSubmitted / $staffCount) * 100) : 0;
                                        $ringColor = $staffProgress === 100 ? '#28a745' : ($staffProgress >= 50 ? '#e0a000' : '#dc2626');
                                        $circ = 2 * M_PI * 24;
                                        $firstLetter = strtoupper(substr($staff->name, 0, 1));
                                        $displayName = $staff->surname.', '.$staff->name.($staff->mi ? ' '.$staff->mi.'.' : '');
                                    @endphp
                                    <tr data-faculty-id="{{ $staff->id }}" data-progress="{{ $staffProgress }}">
                                        <td class="cursor-pointer px-3 py-3" onclick="showFacultyDetails({{ $staff->id }})">
                                            <div class="flex items-center">
                                                <div class="relative mr-3 h-11 w-11 shrink-0 overflow-hidden rounded-full border-2 border-line">
                                                    @if ($staff->profile_image_url)
                                                        <img src="{{ $staff->profile_image_url }}" alt="{{ $displayName }}" class="h-full w-full object-cover"
                                                             onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                        <div class="hidden h-full w-full items-center justify-center bg-gradient-to-br from-cvsu-green-500 to-cvsu-green-800 text-base font-bold text-white">{{ $firstLetter }}</div>
                                                    @else
                                                        <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-cvsu-green-500 to-cvsu-green-800 text-base font-bold text-white">{{ $firstLetter }}</div>
                                                    @endif
                                                    <span class="absolute bottom-0.5 right-0.5 h-3 w-3 rounded-full border-2 border-white bg-cvsu-green-500"></span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate text-sm font-semibold text-ink">{{ $displayName }}</div>
                                                    @if ($staff->employee_id)
                                                        <div class="flex items-center gap-1 text-xs text-muted">
                                                            <i class='bx bx-id-card'></i>{{ $staff->employee_id }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <i class='bx bx-chevron-right text-lg text-muted'></i>
                                            </div>
                                        </td>
                                        @foreach ($documentTypes as $docType)
                                            @php
                                                $sub = $fileSubmissions[$staff->id][$docType] ?? null;
                                            @endphp
                                            <td class="doc-cell {{ $sub ? 'submitted' : 'missing' }}"
                                                onclick="{{ $sub
                                                    ? 'viewDetails('.$staff->id.',\''.addslashes($docType).'\',\''.addslashes($selectedSemester).'\','.$selectedYear.')'
                                                    : 'showNotSubmittedDetails('.$staff->id.',\''.addslashes($docType).'\',\''.addslashes($selectedSemester).'\','.$selectedYear.')' }}">
                                                @if ($sub)
                                                    <div class="mx-auto inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-cvsu-green-600 px-1.5 text-xs font-bold text-white" title="{{ $sub['file_count'] }} file(s) uploaded">{{ $sub['file_count'] }}</div>
                                                    <div class="mt-1 text-[11px] font-semibold text-cvsu-green-700"><i class='bx bx-check'></i></div>
                                                    <div class="text-[10px] text-muted">{{ date('m/d/Y', strtotime($sub['latest_upload'])) }}</div>
                                                    @if (($sub['total_size'] ?? 0) > 0)
                                                        <div class="text-[10px] text-muted">{{ formatFileSize($sub['total_size']) }}</div>
                                                    @endif
                                                @else
                                                    <div class="mx-auto inline-flex h-6 w-6 items-center justify-center rounded-full bg-muted/10 text-xs font-bold text-muted"><i class='bx bx-x'></i></div>
                                                    <div class="mt-1 text-[10px] text-muted">Not Submitted</div>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="px-3 py-3 text-center">
                                            <svg width="60" height="60" viewBox="0 0 60 60">
                                                <circle cx="30" cy="30" r="24" fill="none" stroke="#e6ebe7" stroke-width="4"/>
                                                <circle cx="30" cy="30" r="24" fill="none" stroke="{{ $ringColor }}" stroke-width="4"
                                                        stroke-dasharray="{{ $circ }}"
                                                        stroke-dashoffset="{{ $circ * (1 - $staffProgress / 100) }}"
                                                        transform="rotate(-90 30 30)" class="ring-fg"/>
                                                <text x="30" y="30" text-anchor="middle" dy="0.3em" font-size="12" font-weight="bold" fill="#16241c">{{ $staffProgress }}%</text>
                                            </svg>
                                            <div class="mt-1 text-xs font-semibold text-ink">{{ $staffSubmitted }}/{{ $staffCount }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Filters & actions card --}}
            <div class="rounded-card border border-line bg-white shadow-card overflow-hidden xl:sticky xl:top-6">
                <div class="border-b border-line px-5 py-4">
                    <h3 class="flex items-center gap-2 font-bold text-ink">
                        <i class='bx bxs-filter-alt text-xl text-cvsu-green-600'></i> Filters &amp; Actions
                    </h3>
                </div>
                <div class="space-y-5 px-5 py-4">
                    <div>
                        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-ink"><i class='bx bx-search-alt'></i> Search Faculty</h4>
                        <form method="GET">
                            <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                            <div class="flex gap-2">
                                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, ID, email..."
                                       class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                                <button type="submit" class="rounded-lg bg-cvsu-green-600 px-3 text-white hover:bg-cvsu-green-700"><i class='bx bx-search'></i></button>
                            </div>
                            @if ($search !== '')
                                <a href="{{ route('admin.document-tracker', ['semester' => $selectedSemester, 'status' => $statusFilter]) }}"
                                   class="mt-2 flex w-full items-center justify-center gap-1 rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-muted hover:bg-muted/5">
                                    <i class='bx bx-x'></i> Clear Search
                                </a>
                            @endif
                        </form>
                    </div>

                    <div>
                        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-ink"><i class='bx bx-building'></i> Department</h4>
                        <form method="GET">
                            <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                            <select name="department" onchange="this.form.submit()"
                                    class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                                <option value="{{ $departmentId }}" selected>{{ $departmentName }}</option>
                                @foreach ($departments as $dept)
                                    @if ((int) $dept->id !== (int) $departmentId)
                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <div>
                        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-ink"><i class='bx bx-flag'></i> Status Filter</h4>
                        <form method="GET">
                            <input type="hidden" name="semester" value="{{ $selectedSemester }}">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <select name="status" onchange="this.form.submit()"
                                    class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                                <option value="">All Status</option>
                                <option value="complete" {{ $statusFilter === 'complete' ? 'selected' : '' }}>Complete (100%)</option>
                                <option value="partial" {{ $statusFilter === 'partial' ? 'selected' : '' }}>Partial</option>
                                <option value="none" {{ $statusFilter === 'none' ? 'selected' : '' }}>Not Started (0%)</option>
                            </select>
                        </form>
                    </div>

                    <div>
                        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-ink"><i class='bx bx-download'></i> Export Options</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <button onclick="exportTable('csv')"
                                    class="flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-3 py-2.5 text-xs font-semibold text-white hover:bg-cvsu-green-700">
                                <i class='bx bx-file'></i> Export CSV
                            </button>
                            <button onclick="exportTable('excel')"
                                    class="flex items-center gap-2 rounded-lg bg-cvsu-green-800 px-3 py-2.5 text-xs font-semibold text-white hover:bg-cvsu-green-900">
                                <i class='bx bx-export'></i> Export Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Lower cards --}}
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 font-bold text-ink"><i class='bx bxs-calendar-alt text-xl text-cvsu-green-600'></i> Period Summary</h3>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Academic Year</div>
                        <div class="text-xl font-extrabold text-cvsu-green-700">{{ $selectedYear }}-{{ $selectedYear + 1 }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Semester</div>
                        <div class="text-xl font-extrabold text-cvsu-gold-700">{{ $normalizedSemester }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Faculty Tracked</div>
                        <div class="text-xl font-extrabold text-ink">{{ number_format($totalFaculty) }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Total Documents</div>
                        <div class="text-xl font-extrabold text-ink">{{ number_format($totalPossible) }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 font-bold text-ink"><i class='bx bx-bar-chart-alt-2 text-xl text-cvsu-gold-500'></i> Submission Overview</h3>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Submitted</div>
                        <div class="text-2xl font-extrabold text-cvsu-green-700">{{ number_format($submittedCount) }}</div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-cvsu-green-100">
                            <div class="h-full rounded-full bg-cvsu-green-500" style="width: {{ $totalPossible > 0 ? ($submittedCount / $totalPossible) * 100 : 0 }}%"></div>
                        </div>
                        <div class="mt-1 text-[11px] text-muted">{{ $totalPossible > 0 ? number_format(($submittedCount / $totalPossible) * 100, 1) : 0 }}% submitted</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Missing</div>
                        <div class="text-2xl font-extrabold text-cvsu-gold-700">{{ number_format($totalPossible - $submittedCount) }}</div>
                        <div class="mt-2 text-[11px] text-muted">{{ number_format($totalPossible - $submittedCount) }} documents still needed</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Complete Faculty</div>
                        <div class="text-2xl font-extrabold text-cvsu-green-700">{{ number_format($completeFaculty) }}</div>
                        <div class="mt-2 text-[11px] text-muted">{{ $facultyCompletionRate }}% of all faculty</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-muted">Incomplete Faculty</div>
                        <div class="text-2xl font-extrabold text-danger">{{ number_format($totalFaculty - $completeFaculty) }}</div>
                        <div class="mt-2 text-[11px] text-muted">Need follow-up</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Notifications --}}
        <div class="mt-6 rounded-card border border-line bg-white shadow-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="flex items-center gap-2 font-bold text-ink"><i class='bx bxs-bell text-xl text-cvsu-gold-500'></i> Recent Notifications</h3>
            </div>
            <div class="divide-y divide-line">
                <div class="flex gap-3 px-5 py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600"><i class='bx bx-info-circle text-xl'></i></div>
                    <div>
                        <h4 class="text-sm font-semibold text-ink">Document Tracker Active</h4>
                        <p class="text-sm text-muted">Tracking {{ number_format($totalFaculty) }} faculty across {{ count($documentTypes) }} document types for {{ $selectedSemester }}.</p>
                        <span class="text-xs text-muted">Current period</span>
                    </div>
                </div>
                @if ($completeFaculty < $totalFaculty)
                    <div class="flex gap-3 border-l-4 border-cvsu-gold-400 bg-cvsu-gold-50 px-5 py-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cvsu-gold-100 text-cvsu-gold-700"><i class='bx bxs-bell text-xl'></i></div>
                        <div>
                            <h4 class="text-sm font-semibold text-ink">Incomplete Submissions</h4>
                            <p class="text-sm text-muted">{{ number_format($totalFaculty - $completeFaculty) }} faculty member{{ $totalFaculty - $completeFaculty !== 1 ? 's' : '' }} have incomplete submissions. Consider sending reminders.</p>
                            <span class="text-xs text-muted">Action recommended</span>
                        </div>
                    </div>
                @endif
                <div class="flex gap-3 px-5 py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cvsu-green-100 text-cvsu-green-600"><i class='bx bx-check-circle text-xl'></i></div>
                    <div>
                        <h4 class="text-sm font-semibold text-ink">System Status</h4>
                        <p class="text-sm text-muted">All document uploads are being tracked automatically. Files uploaded by faculty appear in real-time.</p>
                        <span class="text-xs text-muted">Auto-tracking enabled</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Faculty details modal --}}
    <div class="modal-bg" id="facultyDetailsModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink">Faculty Details</h3>
                <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeFacultyDetailsModal()">&times;</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto px-6 py-5" id="facultyDetailsContent"></div>
        </div>
    </div>

    {{-- File details modal --}}
    <div class="modal-bg" id="detailsModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink" id="detailsModalTitle">Document Files</h3>
                <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto px-6 py-5" id="detailsContent"></div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        window.facultyData = @json($faculty);
        window.currentPeriod = {
            semester: @json($normalizedSemester),
            academicYear: @json($selectedYear)
        };
        window.fileDetailsUrl = @json(route('document-tracker.file-details'));

        function showModal(id) { const el = document.getElementById(id); if (el) { el.classList.add('show'); document.body.style.overflow = 'hidden'; } }
        function hideModal(id) { const el = document.getElementById(id); if (el) { el.classList.remove('show'); document.body.style.overflow = ''; } }
        function closeFacultyDetailsModal() { hideModal('facultyDetailsModal'); }
        function closeDetailsModal() { hideModal('detailsModal'); }

        function avatarHtml(f, sizeClass) {
            const letter = (f.name || '?').charAt(0).toUpperCase();
            const img = f.profile_image_url
                ? '<img src="'+f.profile_image_url+'" alt="" class="h-full w-full object-cover" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">'
                  + '<div class="hidden h-full w-full items-center justify-center bg-gradient-to-br from-cvsu-green-500 to-cvsu-green-800 font-bold text-white">'+letter+'</div>'
                : '<div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-cvsu-green-500 to-cvsu-green-800 font-bold text-white">'+letter+'</div>';
            return '<div class="relative '+sizeClass+' shrink-0 overflow-hidden rounded-full border-2 border-line">'+img
                 + '<span class="absolute bottom-1 right-1 h-3.5 w-3.5 rounded-full border-2 border-white bg-cvsu-green-500"></span></div>';
        }

        function showFacultyDetails(facultyId) {
            const content = document.getElementById('facultyDetailsContent');
            const faculty = (window.facultyData || []).find(f => String(f.id) === String(facultyId));
            if (!faculty) { content.innerHTML = '<p class="text-sm text-muted">Faculty data not found.</p>'; showModal('facultyDetailsModal'); return; }
            const fullName = (faculty.surname + ', ' + faculty.name + (faculty.mi ? ' ' + faculty.mi + '.' : ''));
            content.innerHTML = `
                <div class="flex items-center gap-4 border-b border-line pb-5 mb-5">
                    ${avatarHtml(faculty, 'h-20 w-20')}
                    <div>
                        <div class="text-xl font-bold text-ink">${fullName}</div>
                        <div class="text-sm text-muted">${faculty.position || 'Faculty Member'}</div>
                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-cvsu-green-100 px-3 py-1 text-xs font-semibold text-cvsu-green-700">
                            <i class="bx bx-building"></i> ${faculty.department_name || 'No Department'}
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    ${faculty.employee_id ? `
                    <div class="rounded-lg border border-line bg-white p-3 border-l-4" style="border-left-color:var(--cvsu-green-500)">
                        <div class="text-xs uppercase tracking-wide text-muted">Employee ID</div>
                        <div class="text-sm font-semibold text-ink">${faculty.employee_id}</div>
                    </div>` : ''}
                    ${faculty.email ? `
                    <div class="rounded-lg border border-line bg-white p-3 border-l-4" style="border-left-color:var(--cvsu-gold-400)">
                        <div class="text-xs uppercase tracking-wide text-muted">Email</div>
                        <div class="text-sm font-semibold text-ink"><a class="text-cvsu-green-600" href="mailto:${faculty.email}">${faculty.email}</a></div>
                    </div>` : ''}
                    <div class="rounded-lg border border-line bg-white p-3 border-l-4" style="border-left-color:var(--cvsu-green-600)">
                        <div class="text-xs uppercase tracking-wide text-muted">Department</div>
                        <div class="text-sm font-semibold text-ink">${faculty.department_name || 'No Department'}</div>
                    </div>
                    <div class="rounded-lg border border-line bg-white p-3 border-l-4" style="border-left-color:var(--cvsu-gold-500)">
                        <div class="text-xs uppercase tracking-wide text-muted">Status</div>
                        <div class="text-sm font-semibold text-cvsu-green-600"><i class='bx bx-check-circle'></i> Active Faculty</div>
                    </div>
                </div>`;
            showModal('facultyDetailsModal');
        }

        function renderFileModalError(message) {
            document.getElementById('detailsContent').innerHTML = `
                <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700 flex items-center gap-2">
                    <i class='bx bx-error-circle text-xl'></i> ${message}
                </div>
                <div class="mt-4 flex justify-end"><button class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10" onclick="closeDetailsModal()"><i class='bx bx-x'></i> Close</button></div>`;
        }

        function renderFileRows(files) {
            const wrap = document.createElement('div');
            wrap.className = 'space-y-3';
            (files || []).forEach(file => {
                const row = document.createElement('div');
                row.className = 'file-row';
                const size = formatFileSize(file.file_size || 0);
                const uploaded = new Date(file.uploaded_at).toLocaleString();
                row.innerHTML = `
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 font-semibold text-ink"><i class='bx bxs-file text-lg text-cvsu-green-600'></i>${file.file_name}</div>
                            <div class="mt-1 text-xs text-muted"><strong>Size:</strong> ${size} &middot; <strong>Uploaded:</strong> ${uploaded}</div>
                            ${file.description ? '<div class="mt-1 text-xs text-muted"><strong>Description:</strong> '+file.description+'</div>' : ''}
                            <div class="mt-1 text-xs text-muted"><strong>Uploaded by:</strong> ${file.uploader_name || '—'} ${file.employee_id ? '('+file.employee_id+')' : ''}</div>
                        </div>
                    </div>`;
                wrap.appendChild(row);
            });
            document.getElementById('detailsContent').innerHTML = '';
            document.getElementById('detailsContent').appendChild(wrap);
        }

        function viewDetails(facultyId, docType, semester, year) {
            const content = document.getElementById('detailsContent');
            const fac = (window.facultyData || []).find(f => String(f.id) === String(facultyId));
            const facName = fac ? (fac.surname + ', ' + fac.name) : 'Faculty member';
            document.getElementById('detailsModalTitle').textContent = 'Files for ' + docType;
            content.innerHTML = '<div class="flex items-center justify-center gap-2 py-10 text-muted"><div class="h-5 w-5 animate-spin rounded-full border-2 border-cvsu-green-500 border-t-transparent"></div><span class="text-sm">Loading file details...</span></div>';
            showModal('detailsModal');
            const params = new URLSearchParams({ faculty_id: facultyId, document_type: docType, semester: semester, academic_year: year });
            fetch(window.fileDetailsUrl + '?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.files && data.files.length > 0) {
                        renderFileRows(data.files);
                    } else {
                        document.getElementById('detailsContent').innerHTML = `
                            <div class="text-center py-10">
                                <i class='bx bx-error-circle text-5xl text-danger'></i>
                                <p class="mt-3 font-semibold text-ink">No files found for ${docType}</p>
                                <p class="text-sm text-muted">${facName} hasn't uploaded any files for this document type in the selected period.</p>
                            </div>`;
                    }
                })
                .catch(err => renderFileModalError('Network error: ' + err.message));
        }

        function showNotSubmittedDetails(facultyId, docType, semester, year) {
            const fac = (window.facultyData || []).find(f => String(f.id) === String(facultyId));
            const facName = fac ? (fac.name + ' ' + fac.surname) : 'Faculty Member';
            document.getElementById('detailsModalTitle').textContent = 'Not Submitted: ' + docType;
            document.getElementById('detailsContent').innerHTML = `
                <div class="text-center py-8">
                    <i class='bx bx-info-circle text-5xl' style="color:#17a2b8"></i>
                    <h4 class="mt-3 font-bold text-ink">Document Not Submitted</h4>
                    <p class="mx-auto mt-2 max-w-md text-sm text-muted"><strong>${facName}</strong> has not submitted any files for <strong>${docType}</strong> in the selected period (${semester} AY ${year}-${parseInt(year,10)+1}).</p>
                </div>`;
            showModal('detailsModal');
        }

        function escapeCsv(v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; }

        function exportTable(format) {
            const table = document.getElementById('documentTable');
            if (!table) return;
            const rows = [];
            table.querySelectorAll('thead tr, tbody tr').forEach(tr => {
                const cells = [];
                tr.querySelectorAll('th, td').forEach(td => {
                    const text = td.innerText.replace(/\s+/g, ' ').trim();
                    cells.push(escapeCsv(text));
                });
                if (cells.length) rows.push(cells.join(','));
            });
            const csv = rows.join('\r\n');
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'document-tracker-' + Date.now() + (format === 'excel' ? '.csv' : '.csv');
            a.click();
            URL.revokeObjectURL(a.href);
            notify('Table exported as ' + (format === 'excel' ? 'Excel-compatible CSV' : 'CSV') + '.', 'success');
        }

        function formatFileSize(bytes) {
            if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            if (bytes > 1) return bytes + ' bytes';
            if (bytes === 1) return '1 byte';
            return '0 bytes';
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

        // Animate the progress rings on load
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.ring-fg').forEach(circle => {
                const offset = circle.getAttribute('stroke-dashoffset');
                const c = circle.getAttribute('stroke-dasharray');
                circle.style.transition = 'stroke-dashoffset 1s ease-in-out';
                circle.setAttribute('stroke-dashoffset', c);
                requestAnimationFrame(() => circle.setAttribute('stroke-dashoffset', offset));
            });
        });
    </script>
@endsection