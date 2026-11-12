@extends('layouts.user')

@section('title', 'Department Folders - CVSU Naic')
@section('body-class', 'user-folders-page')

@section('page-css')
    <style>
        .modal-bg { position: fixed; inset: 0; z-index: 60; display: none; align-items: flex-start;
            justify-content: center; padding: 6vh 1rem; background: rgba(11, 61, 30, .5);
            backdrop-filter: blur(3px); overflow-y: auto; }
        .modal-bg.show { display: flex; }
        .modal-panel { width: 100%; max-width: 620px; background: #fff; border-radius: 16px;
            box-shadow: 0 24px 60px rgba(11, 61, 30, .28); }
        .cat-card { transition: all .2s ease; }
        .cat-card:hover { transform: translateY(-2px); box-shadow: var(--cvsu-shadow); }
        .cat-card.expanded { border-color: var(--cvsu-green-500); }
        .cat-content { display: none; }
        .cat-content.show { display: block; }
        .fade-in { animation: foldersFade .35s ease both; }
        @keyframes foldersFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .file-chip { border: 1px solid var(--cvsu-line); border-radius: 12px; padding: 14px;
            background: #fff; transition: all .2s ease; }
        .file-chip:hover { border-color: var(--cvsu-green-300); box-shadow: var(--cvsu-shadow); }
        .tab-btn.active { background: var(--cvsu-green-600); color: #fff; border-color: var(--cvsu-green-600); }
        .spin { animation: spinF .8s linear infinite; }
        @keyframes spinF { to { transform: rotate(360deg); } }
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
    @endphp

    <div class="p-6 lg:p-8 fade-in">
        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-ink">Department Folders</h1>
                <ul class="breadcrumb mt-1">
                    <li><a href="{{ route('user.dashboard') }}">Dashboard</a></li>
                    <li><i class='bx bx-chevron-right'></i></li>
                    <li><a class="active" href="#">Department Folders</a></li>
                    @if (! $noDepartment && $department)
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><span class="text-cvsu-green-600 font-semibold">{{ $department->department_name }}</span></li>
                    @endif
                </ul>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="openFolderModal()"
                        class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10">
                    <i class='bx bxs-folder-plus'></i><span>New Folder</span>
                </button>
                <button type="button" onclick="openUploadModal(null, 'category')"
                        class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                    <i class='bx bxs-cloud-upload'></i><span>Upload File</span>
                </button>
            </div>
        </div>

        @if ($noDepartment || ! $department)
            {{-- No department assigned --}}
            <div class="rounded-card border border-line bg-white p-10 text-center shadow-card">
                <i class='bx bxs-error-circle text-6xl text-cvsu-gold-500'></i>
                <h2 class="mt-4 text-xl font-bold text-ink">No Department Assigned</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted">
                    Your account has not been assigned to a department yet. Please contact the administrator
                    to assign your department so you can access the department file folders.
                </p>
                <a href="{{ route('user.dashboard') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>
        @else
            {{-- Department banner --}}
            <div class="mb-6 flex items-center gap-4 overflow-hidden rounded-card border border-line bg-gradient-to-r from-cvsu-green-700 to-cvsu-green-800 p-6 text-white shadow-card">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                    <i class='bx bxs-building text-3xl'></i>
                </div>
                <div>
                    <h2 class="text-xl font-extrabold">{{ $department->department_name }}</h2>
                    <p class="text-sm text-cvsu-green-100">{{ $department->department_code }} &middot; Academic documents by category &amp; semester</p>
                </div>
                <div class="ml-auto hidden rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold backdrop-blur sm:block">
                    {{ $categories->count() }} categories available
                </div>
            </div>

            {{-- Category folders --}}
            <h3 class="mb-3 flex items-center gap-2 font-bold text-ink">
                <i class='bx bxs-category text-xl text-cvsu-green-600'></i> Document Categories
            </h3>
            <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($categories as $key => $cat)
                    <div id="catCard-{{ $key }}" class="cat-card cursor-pointer rounded-card border border-line bg-white p-4 shadow-card"
                         onclick="toggleCategory('{{ $key }}')">
                        <div class="flex items-start justify-between">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl text-white" style="background: linear-gradient(135deg, {{ $cat['color'] }}, {{ $cat['color'] }}cc);">
                                <i class='bx {{ $cat['icon'] }} text-2xl'></i>
                            </div>
                            <i id="catIcon-{{ $key }}" class='bx bx-chevron-down text-xl text-muted transition-transform'></i>
                        </div>
                        <h4 class="mt-3 text-sm font-bold leading-snug text-ink">{{ $cat['name'] }}</h4>
                        <div class="mt-1 text-xs text-muted">
                            <span id="catCount-{{ $key }}" data-count="{{ $categoryCounts[$key] ?? 0 }}">
                                {{ ($categoryCounts[$key] ?? 0) }} file{{ ($categoryCounts[$key] ?? 0) !== 1 ? 's' : '' }}
                            </span>
                        </div>
                        <div id="catContent-{{ $key }}" class="cat-content mt-3 rounded-lg border border-line bg-muted/5 p-3">
                            <div class="mb-2 flex gap-2">
                                <button type="button" data-semester="first" onclick="showSemester('{{ $key }}', 'first', event)"
                                        class="tab-btn flex-1 rounded-lg border border-line bg-white px-2 py-1.5 text-xs font-bold text-ink active">First Sem</button>
                                <button type="button" data-semester="second" onclick="showSemester('{{ $key }}', 'second', event)"
                                        class="tab-btn flex-1 rounded-lg border border-line bg-white px-2 py-1.5 text-xs font-bold text-ink">Second Sem</button>
                                <button type="button" onclick="openUploadModal('{{ $key }}', 'category')"
                                        class="inline-flex items-center gap-1 rounded-lg bg-cvsu-green-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-cvsu-green-700" title="Upload to this category">
                                    <i class='bx bx-cloud-upload'></i>Upload
                                </button>
                            </div>
                            <div id="files-{{ $key }}-first" class="space-y-2"></div>
                            <div id="files-{{ $key }}-second" class="space-y-2 hidden"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Custom folders --}}
            <h3 class="mb-3 mt-8 flex items-center gap-2 font-bold text-ink">
                <i class='bx bxs-folder text-xl text-cvsu-gold-500'></i> My Custom Folders
            </h3>
            <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse ($customFolders as $folder)
                    <div id="customCard-{{ $folder->id }}" class="cat-card cursor-pointer rounded-card border border-line bg-white p-4 shadow-card"
                         onclick="toggleCustomFolder({{ $folder->id }})">
                        <div class="flex items-start justify-between">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl text-white" style="background: linear-gradient(135deg, {{ $folder->colour }}, {{ $folder->colour }}cc);">
                                <i class='bx {{ $folder->icon }} text-2xl'></i>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-muted/10" title="Rename"
                                        onclick="renameCustomFolder({{ $folder->id }}, {{ json_encode($folder->folder_name) }}, event)"><i class='bx bx-edit-alt'></i></button>
                                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-red-100 hover:text-red-600" title="Delete"
                                        onclick="deleteCustomFolder({{ $folder->id }}, event)"><i class='bx bx-trash'></i></button>
                            </div>
                        </div>
                        <h4 class="mt-3 text-sm font-bold text-ink">{{ $folder->folder_name }}</h4>
                        <div class="mt-1 text-xs text-muted">{{ $folder->file_count }} file{{ $folder->file_count !== 1 ? 's' : '' }}</div>
                        <div id="customContent-{{ $folder->id }}" class="cat-content mt-3 rounded-lg border border-line bg-muted/5 p-3">
                            <div class="mb-2 flex justify-end">
                                <button type="button" onclick="openUploadModal({{ $folder->id }}, 'custom', event)"
                                        class="inline-flex items-center gap-1 rounded-lg bg-cvsu-gold-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-cvsu-gold-600" title="Upload to this folder">
                                    <i class='bx bx-cloud-upload'></i>Upload
                                </button>
                            </div>
                            <div id="customFiles-{{ $folder->id }}" class="space-y-2"></div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-card border border-dashed border-line bg-white px-5 py-8 text-center text-muted">
                        <i class='bx bxs-folder-open text-4xl text-muted/40'></i>
                        <p class="mt-2 text-sm font-semibold text-ink">No custom folders yet</p>
                        <p class="text-xs">Create a folder to organise your own files.</p>
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- Upload modal --}}
    <div class="modal-bg" id="uploadModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink"><i class='bx bxs-cloud-upload text-cvsu-green-600'></i> Upload Document</h3>
                <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeModal('uploadModal')">&times;</button>
            </div>
            <form id="uploadForm" class="space-y-4 px-6 py-5" enctype="multipart/form-data">
                <input type="hidden" name="semester" id="upSemester" value="first">
                <input type="hidden" name="academic_year" id="upYear" value="{{ $academicYears->first() ?? (date('Y').'-'.(date('Y') + 1)) }}">
                <input type="hidden" name="custom_folder_id" id="upCustomFolder" value="0">
                <input type="hidden" name="category" id="upCategory" value="">

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Destination</label>
                    <div class="flex gap-2">
                        <button type="button" id="destCategoryBtn" onclick="setUploadDestination('category', '')"
                                class="flex-1 rounded-lg border-2 border-cvsu-green-500 bg-cvsu-green-50 px-3 py-2 text-sm font-bold text-cvsu-green-700">Document Category</button>
                        <button type="button" id="destCustomBtn" onclick="setUploadDestination('custom', '')"
                                class="flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm font-semibold text-muted hover:bg-muted/5">My Custom Folder</button>
                    </div>
                </div>

                <div id="upCategoryWrap">
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Category</label>
                    <select name="category_select" id="upCategorySelect" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                        <option value="">Select a category...</option>
                        @foreach ($categories as $key => $cat)
                            <option value="{{ $key }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="upCustomWrap" class="hidden">
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Custom Folder</label>
                    <select name="custom_folder_select" id="upCustomSelect" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                        <option value="">Select a folder...</option>
                        @foreach ($customFolders as $folder)
                            <option value="{{ $folder->id }}">{{ $folder->folder_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Semester</label>
                    <select onchange="document.getElementById('upSemester').value=this.value"
                            class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                        @foreach ($semesters as $sem)
                            <option value="{{ $sem['key'] }}">{{ $sem['label'] }} ({{ $sem['value'] }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Academic Year</label>
                    <select onchange="document.getElementById('upYear').value=this.value"
                            class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                        @foreach ($academicYears as $ay)
                            <option value="{{ $ay }}">{{ $ay }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Description <span class="font-normal text-muted">(optional)</span></label>
                    <textarea name="description" rows="2" placeholder="Short note about these files..."
                              class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none"></textarea>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Files</label>
                    <label for="upFiles" class="!mb-0 !flex cursor-pointer !flex-col items-center gap-1 rounded-xl bg-[#f1f5f2] px-4 py-6 text-center transition hover:bg-cvsu-green-50">
                        <span class="grid h-11 w-11 place-items-center rounded-full bg-cvsu-green-100 text-2xl text-cvsu-green-700"><i class='bx bx-cloud-upload'></i></span>
                        <span class="mt-1 text-sm font-semibold text-ink">Choose files to upload</span>
                        <span class="text-xs text-muted">PDF, Word, Excel or images, up to 50MB each</span>
                    </label>
                    <input id="upFiles" type="file" name="file[]" multiple required class="sr-only"
                           onchange="(function(i){var l=document.getElementById('upFileList');l.innerHTML='';Array.from(i.files).forEach(function(f){var li=document.createElement('li');li.className='flex items-center justify-between gap-3 rounded-lg bg-[#f1f5f2] px-3 py-2 text-sm';var n=document.createElement('span');n.className='min-w-0 truncate font-medium';n.textContent=f.name;var z=document.createElement('span');z.className='flex-none text-xs text-muted';z.textContent=(f.size/1048576).toFixed(2)+' MB';li.append(n,z);l.appendChild(li);});})(this)">
                    <ul id="upFileList" class="mt-2 grid gap-1.5"></ul>
                </div>

                <div id="uploadProgressWrap" class="hidden">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-cvsu-green-100">
                        <div id="uploadProgressBar" class="h-full rounded-full bg-cvsu-green-500 transition-all" style="width:0%"></div>
                    </div>
                    <p id="uploadProgressText" class="mt-1 text-xs text-muted">Uploading...</p>
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10" onclick="closeModal('uploadModal')">Cancel</button>
                    <button type="submit" id="uploadSubmitBtn" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                        <i class='bx bx-cloud-upload'></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Create folder modal --}}
    <div class="modal-bg" id="folderModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink"><i class='bx bxs-folder-plus text-cvsu-gold-500'></i> Create Custom Folder</h3>
                <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeModal('folderModal')">&times;</button>
            </div>
            <form id="folderForm" class="space-y-4 px-6 py-5">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Folder Name</label>
                    <input type="text" name="folder_name" maxlength="100" required placeholder="e.g. My Research Files"
                           class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Description <span class="font-normal text-muted">(optional)</span></label>
                    <textarea name="folder_description" rows="2" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none"></textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Folder Color</label>
                    <div class="flex flex-wrap gap-2">
                        @php $colors = ['#14532d', '#0f6b3d', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316']; @endphp
                        @foreach ($colors as $c)
                            <label class="cursor-pointer">
                                <input type="radio" name="folder_color" value="{{ $c }}" {{ $loop->first ? 'checked' : '' }} class="peer sr-only">
                                <span class="block h-8 w-8 rounded-full border-2 border-transparent peer-checked:ring-2 peer-checked:ring-cvsu-green-600" style="background: {{ $c }};"></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10" onclick="closeModal('folderModal')">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                        <i class='bx bx-check'></i> Create Folder
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Preview modal --}}
    <div class="modal-bg" id="previewModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink">File Preview</h3>
                <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeModal('previewModal')">&times;</button>
            </div>
            <div class="max-h-[70vh] overflow-hidden px-6 py-5" id="previewContent"></div>
        </div>
    </div>

    {{-- Share modal (workspace / files-table files) --}}
    <div class="modal-bg" id="shareModal">
        <div class="modal-panel">
            <div class="flex items-center justify-between border-b border-line px-6 py-4">
                <h3 class="text-lg font-bold text-ink">Share File</h3>
                <button class="rounded-lg p-2 text-muted hover:bg-muted/10" onclick="closeModal('shareModal')">&times;</button>
            </div>
            <div class="px-6 py-5">
                <p class="mb-4 rounded-lg bg-muted/10 px-3 py-2 text-sm text-ink" id="shareFileName"></p>
                <label class="mb-1.5 block text-sm font-semibold text-ink">Share With</label>
                <select id="shareType" onchange="toggleShareUser(this.value)"
                        class="mb-3 w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                    <option value="user">A specific user</option>
                    <option value="department">All users in my department</option>
                    <option value="public">Anyone (public link)</option>
                </select>
                <div id="shareUserWrap">
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Select User</label>
                    <select id="shareUser" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:border-cvsu-green-500 focus:outline-none">
                        <option value="">Choose a user...</option>
                        @foreach ($shareUsers as $su)
                            <option value="{{ $su->id }}">{{ trim($su->name.' '.($su->mi ?? '').' '.$su->surname) }}{{ $su->department_code ? ' ('.$su->department_code.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button class="rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink hover:bg-muted/10" onclick="closeModal('shareModal')">Cancel</button>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700" onclick="saveShare()">
                        <i class='bx bx-share-alt'></i> Share File
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const endpoints = {
            categoryFiles: @json(route('folders.category-files')),
            upload: @json(route('files.upload')),
            createCustom: @json(route('folders.custom.store')),
            customContents: @json(route('folders.custom.contents')),
            deleteCustom: @json(route('folders.custom.delete')),
            manageCustom: @json(route('folders.custom.manage')),
            workspaceDelete: @json(route('files.workspace.delete')),
            share: @json(route('files.share')),
            download: @json(route('files.workspace.download', ['file' => 'FILE_ID'])),
            preview: @json(route('files.workspace.preview', ['file' => 'FILE_ID'])),
        };
        const userDepartmentId = @json($departmentId ?? 0);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const loadedCategories = new Set();
        const loadedCustom = new Set();

        // ---------- Modal helpers ----------
        function closeModal(id) { const el = document.getElementById(id); if (el) { el.classList.remove('show'); document.body.style.overflow = ''; } }
        function openModal(id) { const el = document.getElementById(id); if (el) { el.classList.add('show'); document.body.style.overflow = 'hidden'; } }

        // ---------- Category accordion ----------
        function toggleCategory(key) {
            const card = document.getElementById('catCard-' + key);
            const content = document.getElementById('catContent-' + key);
            const icon = document.getElementById('catIcon-' + key);
            if (!card || !content) return;
            const expanded = content.classList.contains('show');
            content.classList.toggle('show', !expanded);
            card.classList.toggle('expanded', !expanded);
            icon.style.transform = expanded ? '' : 'rotate(180deg)';
            if (!expanded && !loadedCategories.has(key)) loadCategoryFiles(key);
        }

        function showSemester(key, semester, event) {
            if (event) { event.stopPropagation(); }
            const card = document.getElementById('catCard-' + key);
            const tabs = card.querySelectorAll('.tab-btn');
            tabs.forEach(t => t.classList.toggle('active', t.dataset.semester === semester));
            const first = document.getElementById('files-' + key + '-first');
            const second = document.getElementById('files-' + key + '-second');
            if (first) first.classList.toggle('hidden', semester !== 'first');
            if (second) second.classList.toggle('hidden', semester !== 'second');
        }

        function loadCategoryFiles(key) {
            loadedCategories.add(key);
            const first = document.getElementById('files-' + key + '-first');
            const second = document.getElementById('files-' + key + '-second');
            [first, second].forEach(container => {
                if (container) container.innerHTML = '<div class="flex items-center justify-center gap-2 py-4 text-xs text-muted"><i class="bx bx-loader-alt spin text-base"></i> Loading...</div>';
            });
            const params = new URLSearchParams({ department_id: userDepartmentId, category: key });
            fetch(endpoints.categoryFiles + '?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Failed to load files');
                    renderCategoryFiles(first, data.first_semester || []);
                    renderCategoryFiles(second, data.second_semester || []);
                    const countEl = document.getElementById('catCount-' + key);
                    if (countEl) {
                        const n = data.total_files || 0;
                        countEl.textContent = n + ' file' + (n !== 1 ? 's' : '');
                        countEl.dataset.count = n;
                    }
                })
                .catch(err => {
                    [first, second].forEach(container => {
                        if (container) container.innerHTML = '<div class="rounded-lg bg-red-50 px-3 py-3 text-xs font-semibold text-red-700">' + escapeHtml(err.message || 'Network error') + '</div>';
                    });
                });
        }

        function renderCategoryFiles(container, files) {
            if (!container) return;
            if (!files.length) {
                container.innerHTML = '<div class="rounded-lg bg-muted/10 px-3 py-4 text-center text-xs text-muted"><i class="bx bx-folder-open text-lg"></i><div class="mt-1">No files uploaded</div></div>';
                return;
            }
            container.innerHTML = files.map(fileChipHtml).join('');
        }

        function fileChipHtml(f) {
            return `
                <div class="file-chip flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-cvsu-green-100 text-cvsu-green-600"><i class="bx ${f.icon || 'bxs-file'}"></i></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-ink" title="${escapeHtml(f.original_name || f.file_name)}">${escapeHtml(f.original_name || f.file_name)}</div>
                        <div class="text-xs text-muted">${formatSize(f.file_size)} &middot; ${timeAgo(f.uploaded_at)} &middot; ${f.download_count || 0} dl</div>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <button type="button" onclick="previewWorkspaceFile(${f.id})" class="rounded-lg p-1.5 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600" title="Preview"><i class="bx bx-show"></i></button>
                        <a href="${endpoints.download.replace('FILE_ID', f.id)}" class="rounded-lg p-1.5 text-muted hover:bg-cvsu-green-100 hover:text-cvsu-green-600" title="Download"><i class="bx bx-download"></i></a>
                        <button type="button" onclick="openShareModal(${f.id}, '${escapeHtml(f.original_name || f.file_name)}')" class="rounded-lg p-1.5 text-muted hover:bg-cvsu-gold-100 hover:text-cvsu-gold-600" title="Share"><i class="bx bx-share-alt"></i></button>
                        <button type="button" onclick="deleteWorkspaceFile(${f.id})" class="rounded-lg p-1.5 text-muted hover:bg-red-100 hover:text-red-600" title="Delete"><i class="bx bx-trash"></i></button>
                    </div>
                </div>`;
        }

        // ---------- Custom folders ----------
        function toggleCustomFolder(id) {
            const card = document.getElementById('customCard-' + id);
            const content = document.getElementById('customContent-' + id);
            if (!card || !content) return;
            content.classList.toggle('show');
            card.classList.toggle('expanded', content.classList.contains('show'));
            if (content.classList.contains('show') && !loadedCustom.has(id)) loadCustomFolder(id);
        }

        function loadCustomFolder(id) {
            loadedCustom.add(id);
            const container = document.getElementById('customFiles-' + id);
            container.innerHTML = '<div class="flex items-center justify-center gap-2 py-4 text-xs text-muted"><i class="bx bx-loader-alt spin text-base"></i> Loading...</div>';
            fetch(endpoints.customContents + '?' + new URLSearchParams({ folder_id: id }))
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Failed to load');
                    container.innerHTML = data.files && data.files.length
                        ? data.files.map(f => {
                            f.icon = f.icon || 'bxs-file';
                            return fileChipHtml(f);
                        }).join('')
                        : '<div class="rounded-lg bg-muted/10 px-3 py-4 text-center text-xs text-muted">No files in this folder</div>';
                })
                .catch(() => { container.innerHTML = '<div class="rounded-lg bg-red-50 px-3 py-3 text-xs font-semibold text-red-700">Failed to load files</div>'; });
        }

        function renameCustomFolder(id, name, event) {
            if (event) event.stopPropagation();
            const newName = prompt('Rename folder to:', name);
            if (!newName || newName.trim() === '') return;
            fetch(endpoints.manageCustom, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ action: 'rename', folder_id: id, new_name: newName })
            })
            .then(r => r.json())
            .then(data => { if (data.success) { notify('Folder renamed', 'success'); setTimeout(() => location.reload(), 600); } else notify(data.message || 'Rename failed', 'error'); })
            .catch(() => notify('Network error', 'error'));
        }

        function deleteCustomFolder(id, event) {
            if (event) event.stopPropagation();
            const doDelete = (force) => {
                fetch(endpoints.deleteCustom, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ folder_id: id, force_delete: force })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.confirm) {
                        if (confirm(data.message)) doDelete(true);
                        return;
                    }
                    if (data.success) { notify('Folder deleted', 'success'); setTimeout(() => location.reload(), 600); }
                    else notify(data.message || 'Delete failed', 'error');
                })
                .catch(() => notify('Network error', 'error'));
            };
            if (confirm('Delete this folder? Files inside will also be deleted.')) doDelete(false);
        }

        // ---------- Upload ----------
        let uploadTarget = { type: 'category', key: '' };

        function setUploadDestination(type, key) {
            uploadTarget = { type: type, key: key };
            const catWrap = document.getElementById('upCategoryWrap');
            const cusWrap = document.getElementById('upCustomWrap');
            const catBtn = document.getElementById('destCategoryBtn');
            const cusBtn = document.getElementById('destCustomBtn');
            const isCat = type === 'category';
            catWrap.classList.toggle('hidden', !isCat);
            cusWrap.classList.toggle('hidden', isCat);
            catBtn.className = isCat
                ? 'flex-1 rounded-lg border-2 border-cvsu-green-500 bg-cvsu-green-50 px-3 py-2 text-sm font-bold text-cvsu-green-700'
                : 'flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm font-semibold text-muted hover:bg-muted/5';
            cusBtn.className = isCat
                ? 'flex-1 rounded-lg border border-line bg-white px-3 py-2 text-sm font-semibold text-muted hover:bg-muted/5'
                : 'flex-1 rounded-lg border-2 border-cvsu-green-500 bg-cvsu-green-50 px-3 py-2 text-sm font-bold text-cvsu-green-700';
        }

        function openUploadModal(key, type) {
            uploadTarget = { type: type, key: String(key || '') };
            setUploadDestination(type, key);
            if (type === 'category' && key) {
                const select = document.getElementById('upCategorySelect');
                select.value = key;
                document.getElementById('upCategory').value = key;
            }
            if (type === 'custom' && key) {
                const select = document.getElementById('upCustomSelect');
                select.value = key;
                document.getElementById('upCustomFolder').value = key;
            }
            openModal('uploadModal');
        }

        document.getElementById('upCategorySelect').addEventListener('change', function () {
            document.getElementById('upCategory').value = this.value;
            if (this.value) uploadTarget = { type: 'category', key: this.value };
        });
        document.getElementById('upCustomSelect').addEventListener('change', function () {
            document.getElementById('upCustomFolder').value = this.value || 0;
            if (this.value) uploadTarget = { type: 'custom', key: String(this.value) };
        });

        document.getElementById('uploadForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            const isCategory = uploadTarget.type === 'category';
            const category = isCategory ? document.getElementById('upCategory').value : '';
            const customFolderId = isCategory ? 0 : document.getElementById('upCustomFolder').value;
            if (isCategory && !category) { notify('Please select a category', 'error'); return; }
            if (!isCategory && !customFolderId) { notify('Please select a custom folder', 'error'); return; }

            const fd = new FormData(form);
            fd.set('category', category);
            fd.set('custom_folder_id', customFolderId);
            fd.delete('category_select');
            fd.delete('custom_folder_select');
            const files = form.querySelector('input[type="file"]').files;
            if (!files.length) { notify('Choose at least one file', 'error'); return; }

            const wrap = document.getElementById('uploadProgressWrap');
            const bar = document.getElementById('uploadProgressBar');
            const text = document.getElementById('uploadProgressText');
            const btn = document.getElementById('uploadSubmitBtn');
            wrap.classList.remove('hidden');
            btn.disabled = true;
            bar.style.width = '0%';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', endpoints.upload);
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.addEventListener('progress', (ev) => {
                if (ev.lengthComputable) {
                    const pct = Math.round((ev.loaded / ev.total) * 100);
                    bar.style.width = pct + '%';
                    text.textContent = 'Uploading... ' + pct + '%';
                }
            });
            xhr.addEventListener('load', function () {
                btn.disabled = false;
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        notify('Uploaded ' + (data.files || []).length + ' file(s)', 'success');
                        closeModal('uploadModal');
                        form.reset();
                        setTimeout(() => location.reload(), 700);
                    } else {
                        notify(data.message || 'Upload failed', 'error');
                        setTimeout(() => wrap.classList.add('hidden'), 2500);
                    }
                } catch (err) {
                    notify('Unexpected server response', 'error');
                    setTimeout(() => wrap.classList.add('hidden'), 2500);
                }
            });
            xhr.addEventListener('error', function () {
                btn.disabled = false;
                notify('Network error during upload', 'error');
                setTimeout(() => wrap.classList.add('hidden'), 2500);
            });
            xhr.send(fd);
        });

        // ---------- Create folder ----------
        function openFolderModal() { openModal('folderModal'); }

        document.getElementById('folderForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const name = this.folder_name.value.trim();
            if (!name) { notify('Please enter a folder name', 'error'); return; }
            fetch(endpoints.createCustom, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    folder_name: name,
                    folder_description: this.folder_description.value.trim(),
                    folder_color: (this.querySelector('input[name="folder_color"]:checked') || {}).value || '#14532d'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { notify('Folder created', 'success'); closeModal('folderModal'); setTimeout(() => location.reload(), 600); }
                else notify(data.message || 'Create failed', 'error');
            })
            .catch(() => notify('Network error', 'error'));
        });

        // ---------- File actions ----------
        function previewWorkspaceFile(id) {
            const content = document.getElementById('previewContent');
            content.innerHTML = '<div class="flex items-center justify-center gap-2 py-10 text-muted"><div class="h-5 w-5 animate-spin rounded-full border-2 border-cvsu-green-500 border-t-transparent"></div><span class="text-sm">Loading preview...</span></div>';
            openModal('previewModal');
            const frame = document.createElement('iframe');
            frame.src = endpoints.preview.replace('FILE_ID', id);
            frame.className = 'h-[65vh] w-full rounded-lg border border-line bg-white';
            frame.onload = () => { content.innerHTML = ''; content.appendChild(frame); };
            frame.onerror = () => {
                content.innerHTML = `
                    <div class="text-center py-10">
                        <i class='bx bxs-file text-5xl text-muted/40'></i>
                        <p class="mt-3 font-semibold text-ink">Preview unavailable</p>
                        <a href="${endpoints.download.replace('FILE_ID', id)}" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-cvsu-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-cvsu-green-700">
                            <i class='bx bx-download'></i> Download File
                        </a>
                    </div>`;
            };
        }

        function deleteWorkspaceFile(id) {
            if (!confirm('Delete this file permanently?')) return;
            fetch(endpoints.workspaceDelete, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ file_id: id })
            })
            .then(r => r.json())
            .then(data => { if (data.success) { notify('File deleted', 'success'); setTimeout(() => location.reload(), 600); } else notify(data.message || 'Delete failed', 'error'); })
            .catch(() => notify('Network error', 'error'));
        }

        // ---------- Share (files-table / workspace files) ----------
        let sharingFileId = null;
        function openShareModal(fileId, fileName) {
            sharingFileId = fileId;
            const nameEl = document.getElementById('shareFileName');
            if (nameEl) nameEl.innerHTML = '<i class="bx bx-file text-cvsu-green-600"></i> ' + escapeHtml(fileName || 'this file');
            toggleShareUser(document.getElementById('shareType').value);
            openModal('shareModal');
        }
        function toggleShareUser(type) {
            const wrap = document.getElementById('shareUserWrap');
            if (wrap) wrap.style.display = type === 'user' ? '' : 'none';
        }
        function saveShare() {
            if (!sharingFileId) return;
            const type = document.getElementById('shareType').value;
            const userId = document.getElementById('shareUser').value;
            fetch(endpoints.share, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ file_id: sharingFileId, share_type: type, shared_with: userId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { closeModal('shareModal'); notify(data.message, 'success'); }
                else notify(data.message || 'Share failed', 'error');
            })
            .catch(() => notify('Network error while sharing', 'error'));
        }

        // ---------- Helpers ----------
        function formatSize(bytes) {
            bytes = Number(bytes) || 0;
            if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return bytes + ' B';
        }
        function timeAgo(dateStr) {
            const d = new Date(dateStr), now = new Date();
            const sec = Math.floor((now - d) / 1000);
            if (sec < 60) return 'just now';
            if (sec < 3600) return Math.floor(sec / 60) + 'm ago';
            if (sec < 86400) return Math.floor(sec / 3600) + 'h ago';
            return d.toLocaleDateString();
        }
        function escapeHtml(s) {
            if (typeof s !== 'string') return '';
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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
            if (e.target.classList.contains('modal-bg')) { e.target.classList.remove('show'); document.body.style.overflow = ''; }
        });
    
        // Header "Upload File" button lands here with ?upload=1
        if (new URLSearchParams(window.location.search).get('upload') === '1') {
            openUploadModal(null, 'category');
            history.replaceState(null, '', window.location.pathname);
        }
    </script>
@endsection