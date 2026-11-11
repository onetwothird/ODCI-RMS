@extends('layouts.user')

@section('title', 'My Reports - CVSU Naic')
@section('body-class', 'user-my-reports-page')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
    <style>
        .rep-tab { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; border-radius: 10px;
            font-size: 0.85rem; font-weight: 600; color: #6b7280 !important; border: 1px solid #e6ebe7; background: #fff; transition: all .2s ease; }
        .rep-tab:hover { border-color: #6ee7a0; color: #16241c !important; }
        .rep-tab.active { background: #1e7e34; border-color: #1e7e34; color: #fff !important; }
        .status-on-time { background: #dcfce7; color: #166534; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
        .status-late { background: #fee2e2; color: #b91c1c; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
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
        $typeLabels = [
            'file_uploads' => 'Upload Activity',
            'document_submissions' => 'Document Submissions',
            'activity_summary' => 'Activity Summary',
            'storage_usage' => 'Storage Usage',
        ];
        $filters = [
            'type' => $reportType,
            'academic_year' => $academicYear,
            'semester' => $semester,
        ];
    @endphp

    <div class="p-6 lg:p-8 fade-in">
        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-ink">My Reports</h1>
                <ul class="breadcrumb mt-1">
                    <li><a href="{{ route('user.dashboard') }}">Dashboard</a></li>
                    <li><i class='bx bx-chevron-right'></i></li>
                    <li><a class="active" href="#">My Reports</a></li>
                </ul>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($typeLabels as $key => $label)
                    <a href="{{ route('user.my-reports', array_merge($filters, ['type' => $key])) }}"
                       class="rep-tab {{ $reportType === $key ? 'active' : '' }}">
                        <i class='bx {{ match ($key) {
                            'file_uploads' => 'bx-cloud-upload',
                            'document_submissions' => 'bx-file',
                            'activity_summary' => 'bx-bar-chart-alt-2',
                            default => 'bx-hard-drive',
                        } }}'></i>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cvsu-green-600 text-white">
                    <i class='bx bxs-file-blank text-xl'></i>
                </div>
                <p class="text-2xl font-extrabold text-ink">{{ number_format($stats['total_files']) }}</p>
                <p class="mt-0.5 text-xs font-medium text-muted">Total Files Uploaded</p>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cvsu-gold-500 text-white">
                    <i class='bx bxs-hdd text-xl'></i>
                </div>
                <p class="text-2xl font-extrabold text-ink">{{ $fs($stats['total_storage']) }}</p>
                <p class="mt-0.5 text-xs font-medium text-muted">Storage Used</p>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cvsu-green-500 text-white">
                    <i class='bx bxs-file-doc text-xl'></i>
                </div>
                <p class="text-2xl font-extrabold text-ink">{{ number_format($stats['total_submissions']) }}</p>
                <p class="mt-0.5 text-xs font-medium text-muted">Document Submissions</p>
            </div>
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-cvsu-green-700 text-white">
                    <i class='bx bxs-zap text-xl'></i>
                </div>
                <p class="text-2xl font-extrabold text-ink">{{ number_format($stats['recent_activities']) }}</p>
                <p class="mt-0.5 text-xs font-medium text-muted">Activities (7 days)</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('user.my-reports') }}" class="mb-6 flex flex-col gap-4 rounded-card border border-line bg-white p-5 shadow-card md:flex-row md:items-end">
            <input type="hidden" name="type" value="{{ $reportType }}">
            <div class="flex-1">
                <label class="mb-1.5 block text-sm font-semibold text-ink">Academic Year</label>
                <select name="academic_year" class="w-full rounded-lg border border-line bg-white px-4 py-2.5 text-sm text-ink outline-none transition focus:border-cvsu-green-500 focus:ring-2 focus:ring-cvsu-green-100">
                    <option value="">All Years</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year }}" @selected($academicYear === (string) $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <label class="mb-1.5 block text-sm font-semibold text-ink">Semester</label>
                <select name="semester" class="w-full rounded-lg border border-line bg-white px-4 py-2.5 text-sm text-ink outline-none transition focus:border-cvsu-green-500 focus:ring-2 focus:ring-cvsu-green-100">
                    <option value="">All Semesters</option>
                    <option value="first" @selected($semester === 'first')>1st Semester</option>
                    <option value="second" @selected($semester === 'second')>2nd Semester</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-cvsu-green-700">
                    <i class='bx bx-sync'></i> Generate Report
                </button>
                <a href="{{ route('user.my-reports.export', $filters) }}"
                   class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-cvsu-green-50">
                    <i class='bx bxs-file-export text-cvsu-green-600'></i> Export CSV
                </a>
            </div>
        </form>

        {{-- Report table --}}
        <div class="overflow-hidden rounded-card border border-line bg-white shadow-card">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="flex items-center gap-2 text-base font-bold text-ink">
                    <i class='bx {{ match ($reportType) {
                        'file_uploads' => 'bx-cloud-upload',
                        'document_submissions' => 'bx-file',
                        'activity_summary' => 'bx-bar-chart-alt-2',
                        default => 'bx-hard-drive',
                    } }} text-cvsu-green-600'></i>
                    {{ $typeLabels[$reportType] }} Report
                </h3>
                <span class="rounded-full bg-cvsu-green-50 px-3 py-1 text-xs font-semibold text-cvsu-green-700">
                    {{ count($reportsData) }} record(s)
                </span>
            </div>

            <div class="overflow-x-auto">
                @if ($reportType === 'file_uploads')
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-5 py-3">Folder</th>
                                <th class="px-5 py-3">Academic Year</th>
                                <th class="px-5 py-3">Semester</th>
                                <th class="px-5 py-3 text-center">Files</th>
                                <th class="px-5 py-3 text-right">Total Size</th>
                                <th class="px-5 py-3">First Upload</th>
                                <th class="px-5 py-3">Latest Upload</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($reportsData as $row)
                                <tr class="transition hover:bg-cvsu-green-50/50">
                                    <td class="px-5 py-3.5 font-semibold text-ink">{{ $row->folder_name ?? 'No Folder' }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ $row->academic_year ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-full bg-cvsu-green-100 px-2.5 py-0.5 text-xs font-bold text-cvsu-green-700">
                                            {{ $row->semester === 'first' ? '1st Semester' : ($row->semester === 'second' ? '2nd Semester' : ($row->semester ?? '—')) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-semibold text-ink">{{ number_format($row->file_count) }}</td>
                                    <td class="px-5 py-3.5 text-right text-muted">{{ $fs($row->total_size) }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ date('M d, Y', strtotime($row->first_upload)) }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ date('M d, Y', strtotime($row->latest_upload)) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-12 text-center">
                                    <i class='bx bxs-inbox text-4xl text-muted'></i>
                                    <p class="mt-2 text-sm font-medium text-muted">No uploads found for the selected filters.</p>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($reportType === 'document_submissions')
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-5 py-3">Document Type</th>
                                <th class="px-5 py-3">Academic Year</th>
                                <th class="px-5 py-3">Semester</th>
                                <th class="px-5 py-3">Deadline</th>
                                <th class="px-5 py-3">Submitted</th>
                                <th class="px-5 py-3 text-center">Files</th>
                                <th class="px-5 py-3 text-right">Total Size</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($reportsData as $row)
                                @php
                                    $late = $row->deadline_date && strtotime($row->submitted_at) > strtotime($row->deadline_date);
                                @endphp
                                <tr class="transition hover:bg-cvsu-green-50/50">
                                    <td class="px-5 py-3.5 font-semibold text-ink">{{ $row->document_type }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ $row->academic_year }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ $row->semester }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ $row->deadline_date ? date('M d, Y', strtotime($row->deadline_date)) : 'No deadline' }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ date('M d, Y', strtotime($row->submitted_at)) }}</td>
                                    <td class="px-5 py-3.5 text-center font-semibold text-ink">{{ number_format($row->file_count) }}</td>
                                    <td class="px-5 py-3.5 text-right text-muted">{{ $fs($row->total_size) }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="{{ $late ? 'status-late' : 'status-on-time' }}">{{ $late ? 'Late' : 'On Time' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-5 py-12 text-center">
                                    <i class='bx bxs-inbox text-4xl text-muted'></i>
                                    <p class="mt-2 text-sm font-medium text-muted">No submissions found for the selected filters.</p>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($reportType === 'activity_summary')
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-5 py-3">Date</th>
                                <th class="px-5 py-3">Action</th>
                                <th class="px-5 py-3">Resource Type</th>
                                <th class="px-5 py-3 text-center">Count</th>
                                <th class="px-5 py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($reportsData as $row)
                                <tr class="transition hover:bg-cvsu-green-50/50">
                                    <td class="px-5 py-3.5 font-semibold text-ink">{{ date('M d, Y', strtotime($row->activity_date)) }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded-full bg-cvsu-green-100 px-2.5 py-0.5 text-xs font-bold text-cvsu-green-700">{{ ucwords(str_replace('_', ' ', $row->action)) }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted">{{ ucwords((string) $row->resource_type) }}</td>
                                    <td class="px-5 py-3.5 text-center font-semibold text-ink">{{ number_format($row->count) }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ \Illuminate\Support\Str::limit($row->descriptions, 90) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-12 text-center">
                                    <i class='bx bxs-inbox text-4xl text-muted'></i>
                                    <p class="mt-2 text-sm font-medium text-muted">No activity found for the selected filters.</p>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @else
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-line bg-muted/5 text-xs font-bold uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-5 py-3">Folder</th>
                                <th class="px-5 py-3">File Extension</th>
                                <th class="px-5 py-3 text-center">Files</th>
                                <th class="px-5 py-3 text-right">Total Size</th>
                                <th class="px-5 py-3 text-right">Average Size</th>
                                <th class="px-5 py-3">Latest Upload</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($reportsData as $row)
                                <tr class="transition hover:bg-cvsu-green-50/50">
                                    <td class="px-5 py-3.5 font-semibold text-ink">{{ $row->folder_name ?? 'No Folder' }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="rounded bg-cvsu-green-600 px-2 py-0.5 text-xs font-bold uppercase text-white">.{{ $row->file_extension ?? 'unknown' }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-semibold text-ink">{{ number_format($row->file_count) }}</td>
                                    <td class="px-5 py-3.5 text-right text-muted">{{ $fs($row->total_size) }}</td>
                                    <td class="px-5 py-3.5 text-right text-muted">{{ $fs($row->avg_size) }}</td>
                                    <td class="px-5 py-3.5 text-muted">{{ date('M d, Y', strtotime($row->latest_upload)) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-12 text-center">
                                    <i class='bx bxs-inbox text-4xl text-muted'></i>
                                    <p class="mt-2 text-sm font-medium text-muted">No storage data found for the selected filters.</p>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@endsection