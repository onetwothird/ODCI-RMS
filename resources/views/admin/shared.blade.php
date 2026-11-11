@extends('layouts.admin')

@section('title', 'Shared With Me - CVSU Naic')
@section('body-class', 'admin-shared-page')

@section('page-css')
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
    <style>
        .modal-bg { position: fixed; inset: 0; z-index: 60; display: none; align-items: flex-start;
            justify-content: center; padding: 6vh 1rem; background: rgba(11, 61, 30, .5);
            backdrop-filter: blur(3px); overflow-y: auto; }
        .modal-bg.show { display: flex; }
        .modal-panel { width: 100%; max-width: 640px; background: #fff; border-radius: 16px;
            box-shadow: 0 24px 60px rgba(11, 61, 30, .28); }
        .fade-in { animation: sharedFade .35s ease both; }
        @keyframes sharedFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
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
        $shareBadge = function ($type) {
            return match ($type) {
                'public' => ['bg-cvsu-green-100 text-cvsu-green-700', 'bx-globe', 'Public'],
                'department' => ['bg-cvsu-gold-100 text-cvsu-gold-700', 'bx-building', 'Department'],
                default => ['bg-cvsu-green-100 text-cvsu-green-700', 'bx-user', 'User'],
            };
        };
    @endphp

    <div class="p-6 lg:p-8 fade-in">
        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-ink">Shared With Me</h1>
                <ul class="breadcrumb mt-1">
                    <li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li><i class='bx bx-chevron-right'></i></li>
                    <li><a class="active" href="#">Shared With Me</a></li>
                </ul>
            </div>
            <form method="GET" action="{{ route('admin.shared') }}" class="flex gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search shared files..."
                       class="w-64 rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                    <i class='bx bx-search'></i><span>Search</span>
                </button>
                @if ($search !== '')
                    <a href="{{ route('admin.shared') }}" class="inline-flex items-center gap-2 rounded-lg border border-line px-4 py-2 text-sm font-semibold text-muted hover:bg-muted/10">
                        <i class='bx bx-x'></i>
                    </a>
                @endif
            </form>
        </div>

        {{-- Shared files table --}}
        <div class="rounded-card border border-line bg-white shadow-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h3 class="flex items-center gap-2 font-bold text-ink"><i class='bx bxs-share-alt text-xl text-cvsu-green-600'></i> Files Shared With You ({{ number_format($shares->total()) }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-line bg-muted/5 text-xs font-semibold uppercase tracking-wide text-muted">
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">Shared By</th>
                            <th class="px-4 py-3">Share Type</th>
                            <th class="px-4 py-3">Period</th>
                            <th class="px-4 py-3">Size</th>
                            <th class="px-4 py-3">Shared On</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($shares as $share)
                            @php [$badgeBg, $badgeIcon, $badgeLabel] = $shareBadge($share->share_type); @endphp
                            <tr class="transition-colors hover:bg-cvsu-green-50/60">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cvsu-green-100 text-cvsu-green-600">
                                            <i class='bx {{ $share->icon }} text-xl'></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="max-w-[260px] truncate text-sm font-semibold text-ink" title="{{ $share->file_name }}">{{ $share->file_name }}</div>
                                            <div class="text-xs text-muted">{{ $share->file_type ?: 'document' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-ink">{{ trim(($share->sharer_name ?? '').' '.($share->sharer_surname ?? '')) ?: '—' }}</div>
                                    <div class="text-xs text-muted">{{ $share->department_name ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeBg }}">
                                        <i class='bx {{ $badgeIcon }}'></i>{{ $badgeLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-ink">{{ $share->semester_period ?: '—' }}</div>
                                    <div class="text-xs text-muted">{{ $share->academic_year ?: '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $fs($share->file_size) }}</td>
                                <td class="px-4 py-3 text-sm text-muted">{{ \Carbon\Carbon::parse($share->created_at)->format('M d, Y') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" title="Preview" onclick="previewSharedFile({{ $share->file_id }})"
                                                class="rounded-lg p-2 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600"><i class='bx bx-show'></i></button>
                                        <a href="{{ route('files.workspace.download', $share->file_id) }}" title="Download"
                                           class="rounded-lg p-2 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600"><i class='bx bx-download'></i></a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <i class='bx bxs-share-alt text-5xl text-muted/40'></i>
                                    <p class="mt-3 font-semibold text-ink">Nothing shared with you yet</p>
                                    <p class="text-sm text-muted">Files shared with you or your department appear here.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($shares->hasPages())
                <div class="border-t border-line px-5 py-4">{{ $shares->appends(['search' => $search])->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Preview modal --}}
    <div class="modal-bg" id="previewModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink">File Preview</h3>
                <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closePreview()">&times;</button>
            </div>
            <div class="max-h-[70vh] overflow-hidden px-6 py-5" id="previewContent"></div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const filePreviews = {
            preview: @json(route('files.workspace.preview', ['file' => 'FILE_ID'])),
            download: @json(route('files.workspace.download', ['file' => 'FILE_ID'])),
        };

        function closePreview() {
            const el = document.getElementById('previewModal');
            el.classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('previewContent').innerHTML = '';
        }

        function previewSharedFile(fileId) {
            const content = document.getElementById('previewContent');
            content.innerHTML = '<div class="flex items-center justify-center gap-2 py-10 text-muted"><div class="h-5 w-5 animate-spin rounded-full border-2 border-cvsu-green-500 border-t-transparent"></div><span class="text-sm">Loading preview...</span></div>';
            document.getElementById('previewModal').classList.add('show');
            document.body.style.overflow = 'hidden';

            const frame = document.createElement('iframe');
            frame.src = filePreviews.preview.replace('FILE_ID', fileId);
            frame.className = 'h-[65vh] w-full rounded-lg border border-line bg-white';
            frame.onload = () => { content.innerHTML = ''; content.appendChild(frame); };
            frame.onerror = () => {
                content.innerHTML = `
                    <div class="text-center py-10">
                        <i class='bx bxs-file text-5xl text-muted/40'></i>
                        <p class="mt-3 font-semibold text-ink">Preview unavailable</p>
                        <a href="${filePreviews.download.replace('FILE_ID', fileId)}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                            <i class='bx bx-download'></i> Download File
                        </a>
                    </div>`;
            };
        }

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('modal-bg')) closePreview();
        });
    </script>
@endsection